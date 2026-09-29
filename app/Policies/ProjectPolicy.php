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
        return $project->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.edit') || $project->isLedBy($user);
    }

    /**
     * Adding, removing and re-roling members. The owner runs their own team.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->can('projects.edit') || $project->isLedBy($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.delete');
    }
}
