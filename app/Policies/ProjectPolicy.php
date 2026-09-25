<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Anyone signed in may see the project list; the query itself is scoped.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects.view_all')
            || $project->owner_id === $user->id
            || $project->members()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.manage') || $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.manage');
    }
}
