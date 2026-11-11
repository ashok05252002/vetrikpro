<?php

namespace App\Services\Accounts;

use App\Enums\RenewalCycleStatus;
use App\Models\Renewal;
use App\Models\RenewalCycle;
use App\Models\RenewalPayment;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a renewal's periods and payments goes through here, under a
 * lock on the renewal row, so two people recording payments at once cannot
 * both close a cycle and open two next ones.
 *
 * The rules:
 * - an active renewal has exactly one open (pending) cycle;
 * - a cycle closes as Paid when its payments reach its amount, and the next
 *   one opens `cycle_months` after the old due date — never after the payment
 *   date, so a late payment does not shift the renewal date;
 * - a cancelled renewal has no open cycle, so nothing chases it.
 */
class RenewalService
{
    /**
     * @param  array<string, mixed>  $data  renewal fields, plus `due_date` and optional `amount` for the first cycle
     */
    public function create(array $data, User $by): Renewal
    {
        return DB::transaction(function () use ($data, $by) {
            $renewal = new Renewal($data);
            $renewal->created_by = $by->id;
            $renewal->status = Renewal::ACTIVE;
            $renewal->save();
            $renewal->owners()->sync($data['owner_ids'] ?? []);

            $this->open($renewal, Carbon::parse($data['due_date']), $data['amount'] ?? null);

            return $renewal;
        });
    }

    /**
     * Edit the renewal, and the open cycle's due date and amount with it.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Renewal $renewal, array $data): Renewal
    {
        return DB::transaction(function () use ($renewal, $data) {
            $renewal = Renewal::lockForUpdate()->findOrFail($renewal->id);
            $renewal->update($data);

            if (array_key_exists('owner_ids', $data)) {
                $renewal->owners()->sync($data['owner_ids']);
            }

            $cycle = $renewal->openCycle()->first();

            if ($cycle && isset($data['due_date'])) {
                $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : (float) $cycle->amount;

                if ($amount < (float) $cycle->paid_total) {
                    throw ValidationException::withMessages([
                        'amount' => 'More than this has already been paid for this period ('.number_format((float) $cycle->paid_total, 2).').',
                    ]);
                }

                $cycle->update(['due_date' => $data['due_date'], 'amount' => $amount]);
                $this->settle($renewal, $cycle, null);
            }

            return $renewal;
        });
    }

    /**
     * Record a payment. Paying the balance (or more) closes the cycle and
     * opens the next one.
     *
     * @param  array{paid_on: string, amount: float|string, mode: string, reference?: ?string, note?: ?string}  $data
     */
    public function pay(RenewalCycle $cycle, array $data, User $by): RenewalPayment
    {
        return DB::transaction(function () use ($cycle, $data, $by) {
            $renewal = Renewal::lockForUpdate()->findOrFail($cycle->renewal_id);
            $cycle = RenewalCycle::findOrFail($cycle->id);

            if (! $cycle->status->isOpen()) {
                throw ValidationException::withMessages(['amount' => 'This period is already closed.']);
            }

            $payment = $cycle->payments()->create([...$data, 'recorded_by' => $by->id]);

            $this->settle($renewal, $cycle, $by);

            return $payment;
        });
    }

    /**
     * Remove a payment entered by mistake. On the open cycle this just lowers
     * what is paid. On the last closed cycle it reopens that cycle and removes
     * the next one it opened — allowed only while that next one is untouched.
     */
    public function deletePayment(RenewalPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $cycle = RenewalCycle::findOrFail($payment->renewal_cycle_id);
            $renewal = Renewal::lockForUpdate()->findOrFail($cycle->renewal_id);
            $cycle->refresh();

            if (! $cycle->status->isOpen()) {
                $this->reopen($renewal, $cycle);
            }

            $payment->delete();
            $this->settle($renewal, $cycle->refresh(), null);
        });
    }

    /** Close the open cycle with its balance unpaid, and move on to the next. */
    public function writeOff(RenewalCycle $cycle, User $by): void
    {
        DB::transaction(function () use ($cycle, $by) {
            $renewal = Renewal::lockForUpdate()->findOrFail($cycle->renewal_id);
            $cycle = RenewalCycle::findOrFail($cycle->id);

            if (! $cycle->status->isOpen()) {
                throw ValidationException::withMessages(['cycle' => 'This period is already closed.']);
            }

            $this->close($renewal, $cycle, RenewalCycleStatus::WrittenOff, $by);
        });
    }

    /** Stop tracking: the open cycle is skipped and nothing is chased any more. */
    public function cancel(Renewal $renewal, User $by): void
    {
        DB::transaction(function () use ($renewal, $by) {
            $renewal = Renewal::lockForUpdate()->findOrFail($renewal->id);

            $renewal->openCycle()->get()->each(fn (RenewalCycle $c) => $c->forceFill([
                'status' => RenewalCycleStatus::Skipped,
                'closed_on' => Clock::today(),
                'closed_by' => $by->id,
            ])->save());

            $renewal->forceFill(['status' => Renewal::CANCELLED, 'snoozed_until' => null])->save();
        });
    }

    /** Track a cancelled renewal again, from a new due date. */
    public function reactivate(Renewal $renewal, string $dueDate, float|string|null $amount): void
    {
        DB::transaction(function () use ($renewal, $dueDate, $amount) {
            $renewal = Renewal::lockForUpdate()->findOrFail($renewal->id);

            if ($renewal->isActive()) {
                return;
            }

            $renewal->forceFill(['status' => Renewal::ACTIVE])->save();
            $this->open($renewal, Carbon::parse($dueDate), $amount);
        });
    }

    public function snooze(Renewal $renewal, ?string $until): void
    {
        $renewal->forceFill(['snoozed_until' => $until])->save();
    }

    /** When the cycle after one due on $due falls due. 31 Jan + 1 month = 28/29 Feb. */
    public function nextDue(Renewal $renewal, Carbon $due): Carbon
    {
        return $due->copy()->addMonthsNoOverflow($renewal->cycle_months);
    }

    private function open(Renewal $renewal, Carbon $due, float|string|null $amount = null): RenewalCycle
    {
        $cycle = new RenewalCycle([
            'due_date' => $due->toDateString(),
            'amount' => $amount !== null && $amount !== '' ? round((float) $amount, 2) : $renewal->cycleAmount(),
        ]);
        $cycle->renewal()->associate($renewal);
        $cycle->status = RenewalCycleStatus::Pending;
        $cycle->save();

        return $cycle;
    }

    /** Recount what is paid; close the cycle if that covers it. */
    private function settle(Renewal $renewal, RenewalCycle $cycle, ?User $by): void
    {
        $paid = round((float) $cycle->payments()->sum('amount'), 2);
        $cycle->forceFill(['paid_total' => $paid])->save();

        if ($cycle->status->isOpen() && $paid > 0 && $paid >= (float) $cycle->amount) {
            $this->close($renewal, $cycle, RenewalCycleStatus::Paid, $by);
        }
    }

    private function close(Renewal $renewal, RenewalCycle $cycle, RenewalCycleStatus $status, ?User $by): void
    {
        $cycle->forceFill(['status' => $status, 'closed_on' => Clock::today(), 'closed_by' => $by?->id])->save();

        // A paid period ends any snooze that was waiting on it.
        $renewal->forceFill(['snoozed_until' => null])->save();

        if ($renewal->isActive()) {
            $this->open($renewal, $this->nextDue($renewal, $cycle->due_date));
        }
    }

    private function reopen(Renewal $renewal, RenewalCycle $cycle): void
    {
        $latestClosed = RenewalCycle::where('renewal_id', $renewal->id)
            ->where('status', '!=', RenewalCycleStatus::Pending)
            ->orderByDesc('due_date')->orderByDesc('id')->first();

        if ($cycle->status === RenewalCycleStatus::Skipped || $latestClosed?->id !== $cycle->id) {
            throw ValidationException::withMessages(['payment' => 'Only payments on the open period or the last closed one can be removed.']);
        }

        $next = RenewalCycle::where('renewal_id', $renewal->id)->open()->first();

        if ($next && (float) $next->paid_total > 0) {
            throw ValidationException::withMessages(['payment' => 'The next period already has payments. Remove those first.']);
        }

        $next?->delete();
        $cycle->forceFill(['status' => RenewalCycleStatus::Pending, 'closed_on' => null, 'closed_by' => null])->save();
    }
}
