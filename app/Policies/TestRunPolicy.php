<?php

namespace App\Policies;

use App\Models\TestRun;
use App\Models\TestRunResult;
use App\Models\User;

/**
 * Anyone on the project starts a run and reads its results. Recording a
 * result moves the point along its board, so it follows the point's own rule:
 * the creator, the assigned tester, the project owner and administrators.
 */
class TestRunPolicy
{
    public function view(User $user, TestRun $run): bool
    {
        return $run->project->isAccessibleBy($user);
    }

    public function create(User $user, TestRun $run): bool
    {
        return $run->project->isAccessibleBy($user);
    }

    public function record(User $user, TestRun $run, TestRunResult $result): bool
    {
        return $run->isOpen()
            && $result->point !== null
            && $result->point->statusChangeableBy($user);
    }

    /**
     * Closing (and reopening) the round: whoever started it, the project
     * owner, or someone who manages projects.
     */
    public function complete(User $user, TestRun $run): bool
    {
        return $user->can('projects.edit')
            || $run->project->isLedBy($user)
            || ($run->created_by !== null && $run->created_by === $user->id);
    }

    public function delete(User $user, TestRun $run): bool
    {
        return $user->can('projects.edit') || $run->project->isLedBy($user);
    }
}
