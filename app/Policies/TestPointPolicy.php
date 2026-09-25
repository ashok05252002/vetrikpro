<?php

namespace App\Policies;

use App\Models\TestPoint;
use App\Models\User;

/**
 * Mirrors TaskPolicy: anyone on the project works the testing board, and the
 * tester a point is assigned to may move their own card.
 */
class TestPointPolicy
{
    public function view(User $user, TestPoint $point): bool
    {
        return $point->project->isAccessibleBy($user) || $point->assigned_to === $user->id;
    }

    public function create(User $user, TestPoint $point): bool
    {
        return $point->project->isAccessibleBy($user);
    }

    public function update(User $user, TestPoint $point): bool
    {
        return $point->project->isAccessibleBy($user);
    }

    public function move(User $user, TestPoint $point): bool
    {
        return $this->update($user, $point) || $point->assigned_to === $user->id;
    }

    public function delete(User $user, TestPoint $point): bool
    {
        return $user->can('projects.edit') || $point->project->owner_id === $user->id;
    }
}
