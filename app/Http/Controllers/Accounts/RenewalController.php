<?php

namespace App\Http\Controllers\Accounts;

use App\Enums\PaymentMode;
use App\Enums\RenewalCycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\RenewalRequest;
use App\Mail\RenewalReminderToCustomer;
use App\Mail\RenewalsDue;
use App\Mail\RenewalStarted;
use App\Models\Customer;
use App\Models\Renewal;
use App\Models\RenewalCycle;
use App\Models\RenewalPayment;
use App\Models\RenewalReminder;
use App\Models\RenewalType;
use App\Models\User;
use App\Services\Accounts\RenewalReminders;
use App\Services\Accounts\RenewalService;
use App\Support\Clock;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Accounts → Renewals: domains, servers, AMCs and anything else paid again
 * every cycle. The list is ordered by the open period's due date, so what is
 * most urgent is always on top; the page per renewal holds its periods,
 * payments and the reminder log.
 */
class RenewalController extends Controller
{
    public function __construct(private readonly RenewalService $service, private readonly RenewalReminders $reminders) {}

    public function index(Request $request): Response
    {
        $today = Clock::today();
        $canCost = $request->user()->can('renewals.cost');
        $due = $request->string('due')->value();

        $renewals = $this->withOpenCycle(Renewal::query())
            ->with(['customer:id,name', 'owners:id,name', 'type:id,name'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($q) => $q
                ->where('renewals.title', 'like', "%{$search}%")
                ->orWhere('renewals.identifier', 'like', "%{$search}%")
                ->orWhere('renewals.provider', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))))
            ->when($request->integer('type'), fn ($query, int $type) => $query->where('renewals.renewal_type_id', $type))
            ->when($request->string('customer')->value(), fn ($query, string $customer) => $customer === 'own'
                ? $query->whereNull('renewals.customer_id')
                : $query->where('renewals.customer_id', (int) $customer))
            ->when($request->string('status')->value() ?: 'active', fn ($query, string $status) => $status === 'all' ? $query : $query->where('renewals.status', $status))
            ->when($due, fn ($query) => match ($due) {
                'overdue' => $query->whereDate('oc.due_date', '<', $today),
                'week' => $query->whereDate('oc.due_date', '>=', $today)->whereDate('oc.due_date', '<=', $today->copy()->addDays(7)),
                'month' => $query->whereDate('oc.due_date', '>=', $today)->whereDate('oc.due_date', '<=', $today->copy()->addDays(30)),
                'partly' => $query->where('oc.paid_total', '>', 0),
                'reminding' => $query->whereIn('renewals.id', $this->reminders->inWindow($today)->pluck('renewal_id')),
                default => $query,
            })
            ->orderByRaw('CASE WHEN oc.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('oc.due_date')
            ->orderBy('renewals.title')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Renewal $renewal) => $this->listRow($renewal, $canCost, $today));

        $open = RenewalCycle::query()->open()->whereHas('renewal', fn ($q) => $q->where('status', Renewal::ACTIVE));
        $overdue = (clone $open)->whereDate('due_date', '<', $today)->get(['amount', 'paid_total']);

        return Inertia::render('accounts/renewals/index', [
            'renewals' => $renewals,
            'summary' => [
                'overdue_count' => $overdue->count(),
                'overdue_balance' => round($overdue->sum(fn ($c) => max(0, $c->amount - $c->paid_total)), 2),
                'week' => (clone $open)->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $today->copy()->addDays(7))->count(),
                'month' => (clone $open)->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $today->copy()->addDays(30))->count(),
                'collected_this_month' => (float) RenewalPayment::query()->whereDate('paid_on', '>=', $today->copy()->startOfMonth())->sum('amount'),
            ],
            'filters' => $request->only('search', 'type', 'customer', 'status', 'due'),
            ...$this->formOptions(null),
        ]);
    }

    public function store(RenewalRequest $request): RedirectResponse
    {
        $renewal = $this->service->create($this->fields($request), $request->user());
        $message = "“{$renewal->title}” added. Reminders start {$renewal->remind_days_before} days before it is due.";

        if ($renewal->send_start_mail) {
            $message .= ' '.$this->startMail($renewal);
        }

        return to_route('accounts.renewals.show', $renewal)->with('success', $message);
    }

    /** Send (or send again) the start confirmation to the customer. */
    public function sendStart(Renewal $renewal): RedirectResponse
    {
        return back()->with('success', $this->startMail($renewal));
    }

    private function startMail(Renewal $renewal): string
    {
        try {
            $blocked = $this->reminders->sendStart($renewal);
        } catch (Throwable $e) {
            report($e);

            return 'The start email could not be sent (mail server error). Try “Send start email” again later.';
        }

        return $blocked === null
            ? 'Start email sent to '.$this->reminders->customerEmail($renewal).'.'
            : "Start email not sent: {$blocked}";
    }

    public function show(Request $request, Renewal $renewal): Response
    {
        $canCost = $request->user()->can('renewals.cost');
        $renewal->load(['customer:id,name,email,is_active', 'owners:id,name,email,is_active', 'creator:id,name', 'openCycle', 'type']);

        $cycles = $renewal->cycles()->with(['payments.recorder:id,name', 'closer:id,name'])->get();
        $reminders = RenewalReminder::query()
            ->whereIn('renewal_cycle_id', $cycles->pluck('id'))
            ->orderByDesc('sent_on')->orderBy('audience')
            ->limit(60)
            ->get();

        $open = $renewal->openCycle;

        return Inertia::render('accounts/renewals/show', [
            'renewal' => [
                ...$this->fieldsFor($renewal, $canCost),
                'id' => $renewal->id,
                'type_label' => (string) $renewal->type?->name,
                'cycle_label' => $renewal->cycleLabel(),
                'status' => $renewal->status,
                'snoozed_until' => $renewal->snoozed_until?->toDateString(),
                'is_snoozed' => $renewal->isSnoozed(),
                'customer' => $renewal->customer?->only('id', 'name', 'email', 'is_active'),
                'owners' => $renewal->owners->map(fn (User $u) => $u->only('id', 'name', 'email'))->values(),
                'start_mail_sent_at' => $renewal->start_mail_sent_at?->toIso8601String(),
                'start_blocker' => $this->reminders->startBlocker($renewal),
                'creator' => $renewal->creator?->only('id', 'name'),
                'created_at' => $renewal->created_at?->toIso8601String(),
                'open' => $open ? $this->cycleSummary($open, $renewal) : null,
                'team_recipients' => $this->reminders->teamRecipients($renewal),
                'customer_email' => $this->reminders->customerEmail($renewal),
                'has_payments' => $cycles->contains(fn ($c) => (float) $c->paid_total > 0),
            ],
            'periods' => $cycles->map(fn (RenewalCycle $c) => [
                'id' => $c->id,
                'due_date' => $c->due_date->toDateString(),
                'amount' => (float) $c->amount,
                'paid' => (float) $c->paid_total,
                'balance' => $c->balance(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'closed_on' => $c->closed_on?->toDateString(),
                'closer' => $c->closer?->name,
                'payments' => $c->payments->map(fn (RenewalPayment $p) => [
                    'id' => $p->id,
                    'paid_on' => $p->paid_on->toDateString(),
                    'amount' => (float) $p->amount,
                    'mode' => $p->mode->label(),
                    'reference' => $p->reference,
                    'note' => $p->note,
                    'recorder' => $p->recorder?->name,
                ])->values(),
            ])->values(),
            'reminders' => $reminders->map(fn (RenewalReminder $r) => [
                'id' => $r->id,
                'sent_on' => $r->sent_on->toDateString(),
                'audience' => $r->audience,
                'recipients' => $r->recipients,
                'days_left' => $r->days_left,
            ])->values(),
            'customerRemindersOn' => (bool) app(Settings::class)->get('notify.renewal.customer'),
            ...$this->formOptions($renewal),
        ]);
    }

    public function update(RenewalRequest $request, Renewal $renewal): RedirectResponse
    {
        $this->service->update($renewal, $this->fields($request));

        return back()->with('success', "“{$renewal->title}” updated.");
    }

    public function destroy(Renewal $renewal): RedirectResponse
    {
        if ($renewal->cycles()->where('paid_total', '>', 0)->exists()) {
            return back()->with('error', "“{$renewal->title}” has payments recorded. Cancel it instead, so the history stays.");
        }

        $renewal->delete();

        return to_route('accounts.renewals.index')->with('success', "“{$renewal->title}” deleted.");
    }

    public function pay(Request $request, Renewal $renewal): RedirectResponse
    {
        $cycle = $renewal->openCycle()->firstOrFail();

        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:'.Clock::today()->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'mode' => ['required', Rule::enum(PaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['paid_on.before_or_equal' => 'A payment cannot be dated in the future.']);

        $balance = $cycle->balance();
        $this->service->pay($cycle, $data, $request->user());
        $cycle->refresh();

        $message = $cycle->status === RenewalCycleStatus::Paid
            ? 'Paid in full. The next period is due on '.$renewal->openCycle()->first()?->due_date->format('j M Y').'; reminders for this one have stopped.'
            : 'Payment recorded. Balance '.number_format($cycle->balance(), 2).' still due; reminders continue.';

        if ((float) $data['amount'] > $balance && $balance > 0) {
            $message .= ' (More than the balance was entered.)';
        }

        return back()->with('success', $message);
    }

    public function destroyPayment(Renewal $renewal, RenewalPayment $payment): RedirectResponse
    {
        abort_unless($payment->cycle?->renewal_id === $renewal->id, 404);

        $this->service->deletePayment($payment);

        return back()->with('success', 'Payment removed.');
    }

    public function writeOff(Request $request, Renewal $renewal): RedirectResponse
    {
        $cycle = $renewal->openCycle()->firstOrFail();
        $this->service->writeOff($cycle, $request->user());

        return back()->with('success', 'Balance written off. This period is closed and the next one is open.');
    }

    public function snooze(Request $request, Renewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'until' => ['nullable', 'date', 'after:'.Clock::today()->toDateString()],
        ], ['until.after' => 'Pick a date after today.']);

        $this->service->snooze($renewal, $data['until'] ?? null);

        return back()->with('success', isset($data['until'])
            ? 'Reminders paused until '.Carbon::parse($data['until'])->format('j M Y').'.'
            : 'Reminders resumed.');
    }

    public function cancel(Request $request, Renewal $renewal): RedirectResponse
    {
        $this->service->cancel($renewal, $request->user());

        return back()->with('success', "“{$renewal->title}” cancelled. No more reminders.");
    }

    public function reactivate(Request $request, Renewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'due_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $this->service->reactivate($renewal, $data['due_date'], $data['amount'] ?? null);

        return back()->with('success', "“{$renewal->title}” is active again.");
    }

    /** The reminder this renewal would send today, rendered. Nothing is sent. */
    public function preview(Renewal $renewal, string $audience): RenewalsDue|RenewalReminderToCustomer|RenewalStarted
    {
        $cycle = $renewal->openCycle()->with('renewal.customer')->firstOrFail();
        $rows = collect([$this->reminders->row($cycle)]);

        if ($audience === RenewalReminder::START) {
            abort_unless($renewal->customer, 404);
            $renewal->setRelation('openCycle', $cycle);

            return new RenewalStarted($this->reminders->startPlan($renewal));
        }

        if ($audience === RenewalReminder::CUSTOMER) {
            abort_unless($renewal->customer, 404);

            return new RenewalReminderToCustomer($rows, $renewal->customer->name);
        }

        return new RenewalsDue($rows, $renewal->owners()->value('email') ?? 'preview');
    }

    /** Renewals joined to their open period, as `oc`. */
    private function withOpenCycle(Builder $query): Builder
    {
        return $query->select('renewals.*')
            ->leftJoin('renewal_cycles as oc', fn ($join) => $join->on('oc.renewal_id', '=', 'renewals.id')
                ->where('oc.status', RenewalCycleStatus::Pending->value))
            ->addSelect([
                'oc.id as open_id', 'oc.due_date as open_due', 'oc.amount as open_amount', 'oc.paid_total as open_paid',
            ]);
    }

    private function listRow(Renewal $renewal, bool $canCost, $today): array
    {
        $open = null;

        if ($renewal->open_id) {
            $due = Carbon::parse($renewal->open_due);
            $amount = (float) $renewal->open_amount;
            $paid = (float) $renewal->open_paid;
            $daysLeft = (int) $today->diffInDays($due, false);

            $open = [
                'id' => (int) $renewal->open_id,
                'due_date' => $due->toDateString(),
                'amount' => $amount,
                'paid' => $paid,
                'balance' => max(0, round($amount - $paid, 2)),
                'days_left' => $daysLeft,
                'in_window' => $daysLeft <= $renewal->remind_days_before,
            ];
        }

        return [
            'id' => $renewal->id,
            'title' => $renewal->title,
            'identifier' => $renewal->identifier,
            'provider' => $renewal->provider,
            'renewal_type_id' => $renewal->renewal_type_id,
            'type_label' => (string) $renewal->type?->name,
            'cycle_label' => $renewal->cycleLabel(),
            'customer' => $renewal->customer?->only('id', 'name'),
            'owners' => $renewal->owners->pluck('name')->all(),
            'status' => $renewal->status,
            'is_snoozed' => $renewal->isSnoozed(),
            'snoozed_until' => $renewal->snoozed_until?->toDateString(),
            'notify_customer' => $renewal->notify_customer,
            'cost_price' => $canCost && $renewal->cost_price !== null ? (float) $renewal->cost_price : null,
            'open' => $open,
        ];
    }

    private function cycleSummary(RenewalCycle $cycle, Renewal $renewal): array
    {
        return [
            'id' => $cycle->id,
            'due_date' => $cycle->due_date->toDateString(),
            'amount' => (float) $cycle->amount,
            'paid' => (float) $cycle->paid_total,
            'balance' => $cycle->balance(),
            'days_left' => $cycle->daysLeft(),
            'remind_from' => $cycle->remindFrom($renewal)->toDateString(),
        ];
    }

    /** The edit form's values for a renewal. */
    private function fieldsFor(Renewal $renewal, bool $canCost): array
    {
        $open = $renewal->openCycle;

        return [
            'customer_id' => $renewal->customer_id,
            'renewal_type_id' => $renewal->renewal_type_id,
            'title' => $renewal->title,
            'identifier' => $renewal->identifier,
            'provider' => $renewal->provider,
            'cycle_months' => $renewal->cycle_months,
            'cost_price' => $canCost && $renewal->cost_price !== null ? (float) $renewal->cost_price : null,
            'sell_price' => $renewal->sell_price !== null ? (float) $renewal->sell_price : null,
            'remind_days_before' => $renewal->remind_days_before,
            'repeat_daily' => $renewal->repeat_daily,
            'notify_customer' => $renewal->notify_customer,
            'auto_renew' => $renewal->auto_renew,
            'owner_ids' => $renewal->owners->pluck('id')->all(),
            'start_date' => $renewal->start_date?->toDateString(),
            'send_start_mail' => $renewal->send_start_mail,
            'notes' => $renewal->notes,
            'due_date' => $open?->due_date->toDateString(),
            'amount' => $open ? (float) $open->amount : null,
        ];
    }

    /** Validated fields; the cost stays untouched for someone who may not see it. */
    private function fields(RenewalRequest $request): array
    {
        $data = $request->validated();

        if (! $request->user()->can('renewals.cost')) {
            unset($data['cost_price']);
        }

        return $data;
    }

    private function formOptions(?Renewal $renewal): array
    {
        return [
            'types' => RenewalType::options($renewal?->renewal_type_id),
            'cycles' => RenewalRequest::cycleOptions(),
            'customers' => Customer::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($renewal?->customer_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name]),
            'people' => User::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($renewal?->owners->pluck('id')->all(), fn ($q, $ids) => $q->orWhereIn('id', $ids)))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name]),
            'modes' => PaymentMode::options(),
        ];
    }
}
