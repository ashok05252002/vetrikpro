<?php

namespace App\Services\Accounts;

/**
 * The arithmetic of a GST invoice, in one place. The server always recomputes
 * from the lines it is given — totals are never taken from the browser — and
 * the invoice editor mirrors this so the figures don't jump on save.
 *
 * Per line: taxable = quantity × discounted price; tax = taxable × rate.
 * Tax is split half CGST, half SGST within the company's state, or charged
 * whole as IGST across states. Everything is rounded to paise per line, and
 * SGST takes whatever CGST's rounding left, so the halves always add up.
 */
final class InvoiceCalculator
{
    /**
     * @param  list<array{quantity: float|string, unit_price: float|string, discounted_price: float|string, gst_rate: float|string}>  $lines
     * @return array{lines: list<array{taxable: float, tax: float, amount: float}>, subtotal: float, discount_total: float, taxable_total: float, cgst_total: float, sgst_total: float, igst_total: float, total: float}
     */
    public static function compute(array $lines, bool $interstate): array
    {
        $out = [];
        $subtotal = $discount = $taxable = $cgst = $sgst = $igst = 0.0;

        foreach ($lines as $line) {
            $qty = (float) $line['quantity'];
            $cost = (float) $line['unit_price'];
            $price = (float) $line['discounted_price'];
            $rate = (float) $line['gst_rate'];

            $lineGross = round($qty * $cost, 2);
            $lineTaxable = round($qty * $price, 2);
            $lineTax = round($lineTaxable * $rate / 100, 2);

            if ($interstate) {
                $igst += $lineTax;
            } else {
                $half = round($lineTax / 2, 2);
                $cgst += $half;
                $sgst += round($lineTax - $half, 2);
            }

            $subtotal += $lineGross;
            $discount += round($lineGross - $lineTaxable, 2);
            $taxable += $lineTaxable;

            $out[] = ['taxable' => $lineTaxable, 'tax' => $lineTax, 'amount' => round($lineTaxable + $lineTax, 2)];
        }

        return [
            'lines' => $out,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'taxable_total' => round($taxable, 2),
            'cgst_total' => round($cgst, 2),
            'sgst_total' => round($sgst, 2),
            'igst_total' => round($igst, 2),
            'total' => round($taxable + $cgst + $sgst + $igst, 2),
        ];
    }

    /**
     * Across states when both ends are known and differ. An unknown customer
     * state is treated as local — the usual case for an unregistered buyer.
     */
    public static function isInterstate(?string $companyState, ?string $placeOfSupply): bool
    {
        return $companyState !== null && $companyState !== '' && $placeOfSupply !== null && $placeOfSupply !== '' && $companyState !== $placeOfSupply;
    }
}
