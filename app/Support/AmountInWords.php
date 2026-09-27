<?php

namespace App\Support;

/**
 * "Rupees Twelve Lakh Thirty-Four Thousand Five Hundred Sixty-Seven and
 * Eighty Paise Only" — the Indian system (lakh, crore), as invoices print it.
 */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function inr(float $amount): string
    {
        $rupees = (int) floor(round($amount, 2));
        $paise = (int) round((round($amount, 2) - $rupees) * 100);

        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::indian($rupees));

        if ($paise > 0) {
            $words .= ' and '.self::belowHundred($paise).' Paise';
        }

        return $words.' Only';
    }

    private static function indian(int $n): string
    {
        $parts = [];

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $size => $name) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                // Above a crore the count itself can run into lakhs.
                $parts[] = ($size === 10000000 ? self::indian($count) : self::belowHundred($count)).' '.$name;
                $n %= $size;
            }
        }

        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return self::TENS[intdiv($n, 10)].($n % 10 ? '-'.self::ONES[$n % 10] : '');
    }
}
