<?php

namespace App\Policies;

use App\Enums\BranchStatus;
use App\Enums\MergeRequestStatus;
use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        return $branch->project->isAccessibleBy($user);
    }

    /** Anyone working on the project can register a branch they made. */
    public function create(User $user, Branch $branch): bool
    {
        return $branch->project->isAccessibleBy($user);
    }

    /**
     * Description and linked work belong to whoever made the branch and the
     * people who review it. Once a request is approved the linked work is
     * frozen — changing what a branch claims after sign-off would make the
     * approval mean something it did not.
     */
    public function update(User $user, Branch $branch): bool
    {
        if ($branch->status !== BranchStatus::Active) {
            return false;
        }

        if ($branch->liveMergeRequest?->status === MergeRequestStatus::Approved) {
            return false;
        }

        return $branch->created_by === $user->id
            || $branch->project->isDevAdmin($user)
            || $user->can('projects.manage');
    }

    public function requestMerge(User $user, Branch $branch): bool
    {
        return $branch->status === BranchStatus::Active
            && $branch->liveMergeRequest === null
            && $branch->project->isAccessibleBy($user);
    }
}
