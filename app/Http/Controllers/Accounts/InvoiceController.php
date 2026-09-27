<?php

namespace App\Http\Controllers\Accounts;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\InvoiceRequest;
use App\Mail\InvoiceSent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Accounts\InvoiceDrafts;
use App\Services\Accounts\InvoicePdf;
use App\Support\Clock;
use App\Support\IndianStates;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Invoices: drafted, issued (by email or by hand), then paid or cancelled.
 * Only a draft is edited or deleted; once issued, an invoice is a record.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->with('customer:id,name')
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $number = (int) preg_replace('/\D/', '', $search);
                $query->where(fn ($q) => $q->where('bill_name', 'like', "%{$search}%")->when($number > 0, fn ($q) => $q->orWhere('number', $number)));
            })
            ->when($request->string('status')->value(), function ($query, string $status) {
                $status === 'overdue'
                    ? $query->where('status', InvoiceStatus::Sent)->whereNotNull('due_date')->whereDate('due_date', '<', Clock::today())
                    : $query->where('status', $status);
            })
            ->when($request->integer('customer'), fn ($query, int $id) => $query->where('customer_id', $id))
            // Drafts first (they need finishing), then newest issued.
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('number')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                ...$invoice->only('id', 'number', 'status', 'bill_name', 'issue_date', 'due_date', 'total', 'issued_at', 'sent_at', 'sent_to', 'paid_at'),
                'reference' => $invoice->reference(),
                'is_overdue' => $invoice->isOverdue(),
            ]);

        $issued = Invoice::query()->where('status', '!=', InvoiceStatus::Draft);

        return Inertia::render('accounts/invoices/index', [
            'invoices' => $invoices,
            'summary' => [
                'outstanding' => (float) (clone $issued)->where('status', InvoiceStatus::Sent)->sum('total'),
                'overdue' => (float) (clone $issued)->where('status', InvoiceStatus::Sent)->whereNotNull('due_date')->whereDate('due_date', '<', Clock::today())->sum('total'),
                'overdue_count' => (clone $issued)->where('status', InvoiceStatus::Sent)->whereNotNull('due_date')->whereDate('due_date', '<', Clock::today())->count(),
                'paid_this_month' => (float) Invoice::query()->where('status', InvoiceStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->sum('total'),
                'drafts' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
            ],
            'statuses' => [...InvoiceStatus::options(), ['value' => 'overdue', 'label' => 'Overdue']],
            'customers' => Customer::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name]),
            'filters' => $request->only('search', 'status', 'customer'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('accounts/invoices/edit', [
            'invoice' => null,
            'initial' => [
                'customer_id' => $request->integer('customer') ?: null,
                'issue_date' => Clock::today()->toDateString(),
                'due_date' => Clock::today()->addDays((int) $this->settings->get('invoice.due_days', 15))->toDateString(),
                'terms' => (string) $this->settings->get('invoice.terms'),
                'charge_tax' => true,
            ],
            ...$this->editorOptions(),
        ]);
    }

    public function store(InvoiceRequest $request, InvoiceDrafts $drafts): RedirectResponse
    {
        $invoice = $drafts->save(new Invoice, $request->validated(), $request->user());

        return to_route('accounts.invoices.show', $invoice)->with('success', 'Draft saved. Check it over, then send it.');
    }

    public function show(Invoice $invoice, InvoicePdf $pdf): Response
    {
        $invoice->load(['items', 'customer:id,name,email', 'creator:id,name']);

        return Inertia::render('accounts/invoices/show', [
            'invoice' => [
                ...$invoice->toArray(),
                'reference' => $invoice->reference(),
                'is_overdue' => $invoice->isOverdue(),
                'place_of_supply_name' => IndianStates::name($invoice->place_of_supply),
            ],
            // The same HTML the PDF is drawn from, so what is shown is what is sent —
            // with the print-margin tricks undone for a screen.
            'preview' => str_replace('</head>', '<style>.accent{margin-top:0}.foot{position:static;margin-top:28px}</style></head>', $pdf->html($invoice)),
            'companyStateSet' => (string) $this->settings->get('company.state') !== '',
        ]);
    }

    public function edit(Invoice $invoice): Response|RedirectResponse
    {
        if (! $invoice->isDraft()) {
            return to_route('accounts.invoices.show', $invoice)->with('error', "{$invoice->reference()} has been issued and can no longer be edited. Cancel it and raise a new one instead.");
        }

        $invoice->load('items');

        return Inertia::render('accounts/invoices/edit', [
            'invoice' => ['id' => $invoice->id, 'reference' => $invoice->reference()],
            'initial' => [
                ...$invoice->only('customer_id', 'bill_email', 'bill_address', 'place_of_supply', 'charge_tax', 'notes', 'terms'),
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'items' => $invoice->items->map->only('product_id', 'description', 'hsn_sac', 'quantity', 'unit', 'unit_price', 'discounted_price', 'gst_rate'),
            ],
            ...$this->editorOptions($invoice),
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice, InvoiceDrafts $drafts): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 409, 'Only a draft can be edited.');

        $drafts->save($invoice, $request->validated(), $request->user());

        return to_route('accounts.invoices.show', $invoice)->with('success', 'Draft saved.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if (! $invoice->isDraft()) {
            return back()->with('error', "{$invoice->reference()} has been issued. Cancel it instead — issued invoices are kept.");
        }

        $invoice->delete();

        return to_route('accounts.invoices.index')->with('success', 'Draft deleted.');
    }

    /**
     * Email the invoice. A draft is issued first, taking its number; sending an
     * issued invoice again just resends it.
     */
    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'array', 'max:5'],
            'cc.*' => ['email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($invoice->status === InvoiceStatus::Cancelled) {
            return back()->with('error', 'A cancelled invoice cannot be sent.');
        }

        if ($invoice->isDraft()) {
            $invoice->issue();
        }

        try {
            $mail = Mail::to($data['to']);

            if (! empty($data['cc'])) {
                $mail->cc($data['cc']);
            }

            $mail->send(new InvoiceSent($invoice->fresh('items'), $data['message'] ?? null));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "{$invoice->reference()} is issued, but the email could not be sent. Download the PDF and send it by hand, or try again.");
        }

        $invoice->forceFill(['sent_at' => now(), 'sent_to' => $data['to']])->save();

        return back()->with('success', "{$invoice->reference()} was emailed to {$data['to']}.");
    }

    /** Issue without emailing — for an invoice handed over some other way. */
    public function markSent(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 409, 'Already issued.');

        $invoice->issue();

        return back()->with('success', "Issued as {$invoice->reference()}.");
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['paid_on' => ['required', 'date', 'before_or_equal:today']]);

        abort_unless($invoice->status === InvoiceStatus::Sent, 409, 'Only a sent invoice can be marked paid.');

        $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => $data['paid_on']])->save();

        return back()->with('success', "{$invoice->reference()} marked paid.");
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Sent, 409, 'Only an unpaid, issued invoice can be cancelled.');

        $invoice->forceFill(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now()])->save();

        return back()->with('success', "{$invoice->reference()} cancelled. Its number stays used.");
    }

    public function pdf(Request $request, Invoice $invoice, InvoicePdf $pdf): HttpResponse
    {
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($pdf->filename($invoice)).'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function editorOptions(?Invoice $invoice = null): array
    {
        $onInvoice = $invoice?->items->pluck('product_id')->filter()->all() ?? [];

        return [
            'customers' => Customer::query()->selectable($invoice?->customer_id)->orderBy('name')
                ->get(['id', 'name', 'email', 'address', 'state_code', 'gstin']),
            'products' => Product::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $onInvoice))->orderBy('name')
                ->get(['id', 'name', 'type', 'hsn_sac', 'unit', 'price', 'gst_rate', 'description']),
            'states' => IndianStates::options(),
            'gstRates' => Product::GST_RATES,
            'companyState' => (string) $this->settings->get('company.state') ?: null,
        ];
    }
}
