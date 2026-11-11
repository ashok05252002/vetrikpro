<?php

namespace App\Services\Accounts;

use App\Enums\RenewalCycleStatus;
use App\Mail\RenewalStarted;
use App\Models\Renewal;
use App\Models\RenewalCycle;
use App\Models\RenewalReminder;
use App\Support\Clock;
use App\Support\Letterhead;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Decides who is reminded about which renewal today. Used by the daily
 * `renewals:remind` command and by the previews, so what you preview is what
 * would be sent.
 *
 * A cycle is in its reminder window from `remind_days_before` days before its
 * due date until it is paid — overdue cycles stay in it. Within the window:
 *
 * - the team (everyone responsible + reminder addresses) is emailed every day, or, with
 *   "repeat daily" off, on the first day, on the due day and weekly after;
 * - the customer is emailed only when the master switch and the renewal's own
 *   switch are on, on the first day, on the due day and every N days.
 *
 * Nothing is sent twice in a day (the log has a unique key), and a missed day
 * is not caught up — the next run simply sends that day's reminder.
 */
class RenewalReminders
{
    public function __construct(private readonly Settings $settings, private readonly Letterhead $letterhead) {}

    /**
     * Open cycles in their window today, not snoozed, with their renewal.
     *
     * @return Collection<int, RenewalCycle>
     */
    public function inWindow(?Carbon $today = null): Collection
    {
        $today ??= Clock::today();

        return RenewalCycle::query()
            ->where('status', RenewalCycleStatus::Pending)
            ->whereHas('renewal', fn ($q) => $q->where('status', Renewal::ACTIVE)
                ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhereDate('snoozed_until', '<=', $today)))
            ->with(['renewal.customer', 'renewal.owners', 'renewal.type', 'reminders'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (RenewalCycle $cycle) => $today->gte($cycle->remindFrom($cycle->renewal)))
            ->values();
    }

    public function teamDue(RenewalCycle $cycle, Carbon $today): bool
    {
        $last = $this->lastSent($cycle, RenewalReminder::TEAM);

        if ($last?->isSameDay($today)) {
            return false;
        }

        return $cycle->renewal->repeat_daily
            || $last === null
            || $today->isSameDay($cycle->due_date)
            || $last->diffInDays($today) >= 7;
    }

    public function customerDue(RenewalCycle $cycle, Carbon $today): bool
    {
        $renewal = $cycle->renewal;

        if (! $this->settings->get('notify.renewal.customer') || ! $renewal->notify_customer || $this->customerEmail($renewal) === null) {
            return false;
        }

        $last = $this->lastSent($cycle, RenewalReminder::CUSTOMER);

        if ($last?->isSameDay($today)) {
            return false;
        }

        $every = max(1, (int) $this->settings->get('notify.renewal.customer_every', 3));

        return $last === null
            || $today->isSameDay($cycle->due_date)
            || $last->diffInDays($today) >= $every;
    }

    public function customerEmail(Renewal $renewal): ?string
    {
        $customer = $renewal->customer;

        return $customer && $customer->is_active && filled($customer->email) ? strtolower(trim($customer->email)) : null;
    }

    /**
     * Who on our side hears about a renewal: everyone responsible for it and
     * the reminder addresses; the company address if that leaves nobody.
     *
     * @return list<string>
     */
    public function teamRecipients(Renewal $renewal): array
    {
        $owners = $renewal->owners
            ->filter(fn ($u) => $u->is_active && filled($u->email))
            ->map(fn ($u) => strtolower($u->email))
            ->all();

        $extra = array_map(fn ($e) => strtolower(trim((string) $e)), (array) $this->settings->get('notify.renewal.emails', []));

        $emails = array_values(array_unique(array_filter([...$owners, ...$extra])));

        if ($emails === [] && filled($company = (string) $this->settings->get('company.email'))) {
            $emails = [strtolower($company)];
        }

        return $emails;
    }

    /**
     * One line of a reminder email.
     *
     * @return array{id: int, cycle_id: int, title: string, identifier: ?string, label: string, category: string, customer: ?string, provider: ?string, due: string, days_left: int, amount: string, paid: ?string, balance: string, url: string}
     */
    public function row(RenewalCycle $cycle): array
    {
        $renewal = $cycle->renewal;

        return [
            'id' => $renewal->id,
            'cycle_id' => $cycle->id,
            'title' => $renewal->title,
            'identifier' => $renewal->identifier,
            // What the customer sees: "Domain clientx.com", or the title.
            'label' => $renewal->customerLabel(),
            'category' => (string) $renewal->type?->name,
            'customer' => $renewal->customer?->name,
            'provider' => $renewal->provider,
            'due' => $this->letterhead->date($cycle->due_date),
            'days_left' => $cycle->daysLeft(),
            'amount' => $this->letterhead->money((float) $cycle->amount, 2),
            'paid' => (float) $cycle->paid_total > 0 ? $this->letterhead->money((float) $cycle->paid_total, 2) : null,
            'balance' => $this->letterhead->money($cycle->balance(), 2),
            'url' => route('accounts.renewals.show', $renewal),
        ];
    }

    /**
     * Why the start confirmation cannot go out now, or null when it can.
     */
    public function startBlocker(Renewal $renewal): ?string
    {
        return match (true) {
            ! $this->settings->get('notify.renewal.start') => 'Start emails are switched off in Configuration hub → Email notifications.',
            $renewal->customer === null => 'This renewal has no customer.',
            $this->customerEmail($renewal) === null => 'The customer has no billing email, or is inactive.',
            $renewal->openCycle === null => 'This renewal has no open period.',
            default => null,
        };
    }

    /**
     * What the start confirmation says.
     *
     * @return array{label: string, category: string, customer: string, start: ?string, cycle: string, due: string, amount: string}
     */
    public function startPlan(Renewal $renewal): array
    {
        $cycle = $renewal->openCycle;

        return [
            'label' => $renewal->customerLabel(),
            'category' => (string) $renewal->type?->name,
            'customer' => (string) $renewal->customer?->name,
            'start' => $renewal->start_date ? $this->letterhead->date($renewal->start_date) : null,
            'cycle' => $renewal->cycleLabel(),
            'due' => $cycle ? $this->letterhead->date($cycle->due_date) : '—',
            'amount' => $this->letterhead->money((float) ($cycle?->amount ?? $renewal->cycleAmount()), 2),
        ];
    }

    /**
     * Email the customer the start confirmation and log it. Returns null when
     * sent, or why it was not.
     */
    public function sendStart(Renewal $renewal): ?string
    {
        $renewal->loadMissing(['customer', 'openCycle']);

        if ($reason = $this->startBlocker($renewal)) {
            return $reason;
        }

        $email = $this->customerEmail($renewal);
        Mail::to($email)->send(new RenewalStarted($this->startPlan($renewal)));

        $renewal->forceFill(['start_mail_sent_at' => now()])->save();
        RenewalReminder::query()->insertOrIgnore([
            'renewal_cycle_id' => $renewal->openCycle->id,
            'audience' => RenewalReminder::START,
            'sent_on' => Clock::today()->toDateString(),
            'recipients' => json_encode([$email]),
            'days_left' => $renewal->openCycle->daysLeft(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return null;
    }

    private function lastSent(RenewalCycle $cycle, string $audience): ?Carbon
    {
        return $cycle->reminders->where('audience', $audience)->sortByDesc('sent_on')->first()?->sent_on;
    }
}
