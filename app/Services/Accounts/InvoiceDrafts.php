<?php

namespace App\Services\Accounts;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Writes a draft invoice from the editor: the header, the customer as billed,
 * and the lines — every amount recomputed here, never taken from the form.
 */
final class InvoiceDrafts
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $data  validated InvoiceRequest data
     */
    public function save(Invoice $invoice, array $data, User $by): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $by) {
            $customer = Customer::findOrFail($data['customer_id']);
            $placeOfSupply = $data['place_of_supply'] ?? $customer->state_code;
            $interstate = InvoiceCalculator::isInterstate((string) $this->settings->get('company.state'), $placeOfSupply);

            $lines = array_values($data['items']);
            $totals = InvoiceCalculator::compute($lines, $interstate);

            $invoice->fill([
                'customer_id' => $customer->id,
                'bill_name' => $customer->name,
                'bill_email' => ($data['bill_email'] ?? null) ?: $customer->email,
                'bill_address' => ($data['bill_address'] ?? null) ?: $customer->address,
                'bill_gstin' => $customer->gstin,
                'place_of_supply' => $placeOfSupply,
                'is_interstate' => $interstate,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);
            $invoice->created_by ??= $by->id;
            $invoice->forceFill(collect($totals)->except('lines')->all())->save();

            // A draft's lines are replaced wholesale: only a draft is ever edited.
            $invoice->items()->delete();

            foreach ($lines as $position => $line) {
                $invoice->items()->create([
                    'product_id' => $line['product_id'] ?? null,
                    'description' => $line['description'],
                    'hsn_sac' => $line['hsn_sac'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'discounted_price' => $line['discounted_price'],
                    'gst_rate' => $line['gst_rate'],
                    ...$totals['lines'][$position],
                    'position' => $position,
                ]);
            }

            return $invoice;
        });
    }
}
