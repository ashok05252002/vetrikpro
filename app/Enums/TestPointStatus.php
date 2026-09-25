<?php

namespace App\Enums;

/**
 * Board columns for testing. Passed and Failed are outcomes, not further
 * steps along a scale, so the board colours them with the status palette.
 */
enum TestPointStatus: string
{
    case ToTest = 'to_test';
    case Testing = 'testing';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::ToTest => 'To test',
            self::Testing => 'In testing',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }

    public function isOutcome(): bool
    {
        return $this === self::Passed || $this === self::Failed;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
