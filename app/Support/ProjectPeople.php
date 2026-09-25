<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Collection;

final class ProjectPeople
{
    /**
     * Who work on this project can be assigned to: its members and its owner.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function assignable(Project $project): Collection
    {
        return $project->members()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->push($project->owner)
            ->filter()
            ->unique('id')
            ->map->only('id', 'name')
            ->values();
    }
}
