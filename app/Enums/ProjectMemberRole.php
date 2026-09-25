<?php

namespace App\Enums;

/**
 * A person's role inside one project. Separate from their organisation role:
 * someone is a dev admin on the projects they review, and nowhere else.
 */
enum ProjectMemberRole: string
{
    case Member = 'member';
    case DevAdmin = 'dev_admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::DevAdmin => 'Dev admin',
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
