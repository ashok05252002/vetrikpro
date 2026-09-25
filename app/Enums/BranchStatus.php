<?php

namespace App\Enums;

enum BranchStatus: string
{
    case Active = 'active';
    case Merged = 'merged';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Merged => 'Merged',
            self::Closed => 'Closed',
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
