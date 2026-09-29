<?php

namespace App\Support;

use App\Enums\ProjectMemberRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may be given a role inside a project. Eligibility is set in Roles &
 * access (project_roles.lead, project_roles.merge) — by role, or for one
 * person as extra access — and the pickers only offer eligible people.
 */
final class ProjectRoles
{
    public static function isEligible(User $user, ProjectMemberRole $role): bool
    {
        $permission = $role->eligibility();

        return $permission === null || $user->hasPermission($permission);
    }

    /**
     * Active, not-archived people eligible for the role, optionally only among
     * the given user ids (a project's members).
     *
     * @param  list<int>|null  $among
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    public static function eligible(ProjectMemberRole $role, ?array $among = null): Collection
    {
        return User::query()
            ->with(['role', 'permissionOverrides'])
            ->where('is_active', true)
            ->whereDoesntHave('employee', fn ($e) => $e->whereNotNull('archived_at'))
            ->when($among !== null, fn ($q) => $q->whereIn('id', $among))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => self::isEligible($user, $role))
            ->map(fn (User $user) => $user->only('id', 'name', 'email'))
            ->values();
    }

    /**
     * Validation: everyone given the role must be eligible for it.
     */
    public static function eligibleRule(ProjectMemberRole|string|null $role): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($role) {
            $role = is_string($role) ? ProjectMemberRole::tryFrom($role) : $role;
            $user = User::find($value);

            if ($role !== null && $user !== null && ! self::isEligible($user, $role)) {
                $fail("{$user->name} can't be given “{$role->label()}”: their role doesn't allow it. Change that in Roles & access.");
            }
        };
    }
}
