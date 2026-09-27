<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Collection;

final class ProjectPeople
{
    /**
     * Who work on this project can be assigned to: its members and its owner —
     * never someone archived, who has left and cannot sign in.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function assignable(Project $project): Collection
    {
        $current = fn ($query) => $query->whereDoesntHave('employee', fn ($e) => $e->whereNotNull('archived_at'));

        return $project->members()
            ->tap($current)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->push($project->owner()->tap($current)->first(['users.id', 'users.name']))
            ->filter()
            ->unique('id')
            ->map->only('id', 'name')
            ->values();
    }

    /**
     * Validation for an assignee field: nobody archived may be given work,
     * though a card already assigned to them may keep saving unchanged.
     */
    public static function notArchivedRule(?int $current): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($current) {
            if ($value === null || $value === '' || (int) $value === $current) {
                return;
            }

            if (Employee::where('user_id', $value)->whereNotNull('archived_at')->exists()) {
                $fail('That person has been archived and cannot be given work.');
            }
        };
    }
}
