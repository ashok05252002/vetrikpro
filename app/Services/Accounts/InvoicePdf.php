<?php

namespace App\Services\Accounts;

use App\Models\Invoice;
use App\Support\AmountInWords;
use App\Support\IndianStates;
use App\Support\Letterhead;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * A GST tax invoice as a PDF, on the company letterhead. Built on demand from
 * the stored invoice — nothing is cached — so a download and the emailed copy
 * are always the same document.
 */
final class InvoicePdf
{
    public function __construct(private readonly Settings $settings, private readonly Letterhead $letterhead) {}

    public function render(Invoice $invoice): string
    {
        return Pdf::loadHTML($this->html($invoice))->setPaper('a4')->output();
    }

    public function filename(Invoice $invoice): string
    {
        return ($invoice->number ? $invoice->reference() : 'Draft invoice').' - '.$invoice->bill_name.'.pdf';
    }

    public function html(Invoice $invoice): string
    {
        $invoice->loadMissing('items');
        $money = fn ($amount) => $this->letterhead->money((float) $amount, 2);
        $companyState = (string) $this->settings->get('company.state');

        return view('pdf.invoice', [
            'company' => [...$this->letterhead->company(), 'state' => IndianStates::name($companyState), 'state_code' => $companyState],
            'invoice' => $invoice,
            'reference' => $invoice->reference(),
            'draft' => $invoice->isDraft(),
            'cancelled' => $invoice->status->value === 'cancelled',
            'issueDate' => $this->letterhead->date($invoice->issue_date),
            'dueDate' => $invoice->due_date ? $this->letterhead->date($invoice->due_date) : null,
            'tax' => $invoice->charge_tax,
            'placeOfSupply' => $invoice->charge_tax && $invoice->place_of_supply ? IndianStates::name($invoice->place_of_supply)." ({$invoice->place_of_supply})" : null,
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'hsn_sac' => $item->hsn_sac,
                'quantity' => rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.').' '.$item->unit,
                'unit_price' => $money($item->unit_price),
                'discounted' => (float) $item->discounted_price < (float) $item->unit_price ? $money($item->discounted_price) : null,
                'taxable' => $money($item->taxable),
                'rate' => rtrim(rtrim(number_format((float) $item->gst_rate, 2), '0'), '.').'%',
                'tax' => $money($item->tax),
                'amount' => $money($item->amount),
            ]),
            'hasDiscount' => (float) $invoice->discount_total > 0,
            'totals' => array_filter([
                'Subtotal' => $money($invoice->subtotal),
                'Discount' => (float) $invoice->discount_total > 0 ? '− '.$money($invoice->discount_total) : null,
                'Taxable value' => $invoice->charge_tax ? $money($invoice->taxable_total) : null,
                'CGST' => $invoice->charge_tax && ! $invoice->is_interstate ? $money($invoice->cgst_total) : null,
                'SGST' => $invoice->charge_tax && ! $invoice->is_interstate ? $money($invoice->sgst_total) : null,
                'IGST' => $invoice->charge_tax && $invoice->is_interstate ? $money($invoice->igst_total) : null,
            ]),
            'total' => $money($invoice->total),
            'words' => $this->settings->get('display.currency') === 'INR' ? AmountInWords::inr((float) $invoice->total) : null,
            'bank' => (string) $this->settings->get('invoice.bank_details'),
            'signatory' => ['name' => (string) $this->settings->get('offer.signatory_name')],
        ])->render();
    }
}
