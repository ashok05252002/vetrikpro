<?php

namespace App\Enums;

/**
 * A run is open while people record results, and completed once the round is
 * over — after which its results are frozen as the record of that round.
 */
enum TestRunStatus: string
{
    case Open = 'open';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'In progress',
            self::Completed => 'Completed',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
