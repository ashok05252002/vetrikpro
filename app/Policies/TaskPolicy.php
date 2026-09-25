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
     * Changing the status — dragging on the board or picking it in the edit
     * dialog — is for the task's creator, its assignee, the project owner and
     * administrators only. Other members can see and edit the wording, but
     * not move the work along.
     */
    public function move(User $user, Task $task): bool
    {
        return $task->statusChangeableBy($user);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('projects.edit') || $task->project->owner_id === $user->id;
    }

    private function onProject(User $user, Task $task): bool
    {
        $project = $task->relationLoaded('project') ? $task->project : $task->project()->first();

        return $project === null ? $user->can('projects.view') : $project->isAccessibleBy($user);
    }
}
