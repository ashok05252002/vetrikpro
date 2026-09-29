<?php

namespace App\Enums;

/**
 * A person's role inside one project. Separate from their organisation role:
 * someone has merge access on the projects they review, and nowhere else.
 *
 * - Member: works on the project.
 * - Merge access (stored as dev_admin): also reviews and merges its merge requests.
 * - Project lead: everything the project's owner can do, on this project.
 *
 * Who may be given merge access or made a lead is decided in Roles & access
 * (project_roles.merge / project_roles.lead).
 */
enum ProjectMemberRole: string
{
    case Member = 'member';
    case DevAdmin = 'dev_admin';
    case Lead = 'lead';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::DevAdmin => 'Merge access',
            self::Lead => 'Project lead',
        };
    }

    /** The permission that makes someone eligible for this role, if any. */
    public function eligibility(): ?string
    {
        return match ($this) {
            self::Member => null,
            self::DevAdmin => 'project_roles.merge',
            self::Lead => 'project_roles.lead',
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
