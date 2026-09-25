<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->onProject($user, $task) || $task->assigned_to === $user->id;
    }

    public function create(User $user, Task $task): bool
    {
        return $this->onProject($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->onProject($user, $task);
    }

    /**
     * Moving a card between board columns is deliberately looser than a full
     * edit: whoever the task is assigned to may advance their own work.
     */
    public function move(User $user, Task $task): bool
    {
        return $this->update($user, $task) || $task->assigned_to === $user->id;
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('projects.manage') || $task->project->owner_id === $user->id;
    }

    private function onProject(User $user, Task $task): bool
    {
        $project = $task->relationLoaded('project') ? $task->project : $task->project()->first();

        return $project === null ? $user->can('projects.view_all') : $project->isAccessibleBy($user);
    }
}
