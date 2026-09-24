<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Hr = 'hr';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Hr => 'HR Manager',
            self::Employee => 'Employee',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role) => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}
