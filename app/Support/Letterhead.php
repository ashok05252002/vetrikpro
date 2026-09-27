<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * What every generated document shares — offer letters, promotion letters,
 * invoices: the company block for the letterhead, and dates and money written
 * the way Settings says, in a form dompdf can draw.
 */
final class Letterhead
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{name: string, legal_name: string, address: string, email: string, phone: string, website: string, tax_id: string, logo: ?string}
     */
    public function company(): array
    {
        $s = $this->settings;

        return [
            'name' => (string) $s->get('company.name'),
            'legal_name' => (string) $s->get('company.legal_name'),
            'address' => (string) $s->get('company.address'),
            'email' => (string) $s->get('company.email'),
            'phone' => (string) $s->get('company.phone'),
            'website' => (string) $s->get('company.website'),
            'tax_id' => (string) $s->get('company.tax_id'),
            'logo' => $this->logoDataUri(),
        ];
    }

    public function date(Carbon $date): string
    {
        return match ($this->settings->get('display.date_format')) {
            'mdy' => $date->format('F j, Y'),
            'ymd' => $date->format('Y-m-d'),
            default => $date->format('j F Y'),
        };
    }

    /**
     * An amount in the company currency. Whole units unless `$decimals` asks
     * for paise/cents (invoices do; letters don't).
     */
    public function money(float $amount, int $decimals = 0): string
    {
        $currency = (string) $this->settings->get('display.currency', 'INR');
        // "Rs." rather than ₹: the PDF's built-in fonts have no rupee glyph.
        $symbol = ['INR' => 'Rs. ', 'USD' => '$', 'EUR' => 'EUR ', 'GBP' => 'GBP ', 'AED' => 'AED ', 'SGD' => 'SGD '][$currency] ?? $currency.' ';
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        if ($currency !== 'INR') {
            return $sign.$symbol.number_format($amount, $decimals);
        }

        $fraction = $decimals > 0 ? '.'.substr(number_format($amount, $decimals, '.', ''), -$decimals) : '';
        $whole = $decimals > 0 ? floor(round($amount, $decimals)) : round($amount);

        return $sign.$symbol.self::indianGrouping((int) $whole).$fraction;
    }

    /** 1234567 -> 12,34,567 */
    public static function indianGrouping(int $whole): string
    {
        $digits = (string) $whole;
        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);

        return $rest === '' ? $last3 : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
    }

    /**
     * The light-background logo as a data URI, for a PDF. dompdf cannot draw
     * SVG reliably; better no logo than a broken one.
     */
    public function logoDataUri(): ?string
    {
        $path = (string) $this->settings->get('company.logo');
        $disk = Storage::disk('uploads');

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return str_contains($mime, 'svg') ? null : 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
