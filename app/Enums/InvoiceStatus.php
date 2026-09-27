<?php

namespace App\Enums;

/**
 * An invoice is a Draft until it is issued — emailed, or marked sent by hand —
 * which gives it its number. Then it is Paid, or Cancelled. Only drafts can
 * be edited or deleted; an issued invoice is a record and stays one.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isIssued(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
