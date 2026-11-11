<?php

namespace App\Enums;

/**
 * A period of a renewal. Pending until paid in full (Paid), closed with a
 * balance left (Written off), or dropped because the renewal was cancelled
 * (Skipped). "Partly paid" is not a status: it is a pending cycle with
 * something paid, so it can never disagree with the payments.
 */
enum RenewalCycleStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case WrittenOff = 'written_off';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::WrittenOff => 'Written off',
            self::Skipped => 'Skipped',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
