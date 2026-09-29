<?php

namespace App\Policies;

use App\Models\MergeRequest;
use App\Models\User;

class MergeRequestPolicy
{
    public function view(User $user, MergeRequest $mergeRequest): bool
    {
        return $mergeRequest->project->isAccessibleBy($user);
    }

    /**
     * Approving, requesting changes and marking merged: the project's dev
     * admins, or anyone who may merge on any project — but never on their own
     * request. A second pair of eyes is the point of asking.
     */
    public function review(User $user, MergeRequest $mergeRequest): bool
    {
        return $mergeRequest->requested_by !== $user->id && self::isReviewer($user, $mergeRequest);
    }

    /** Sending it back for review after making the requested changes. */
    public function resubmit(User $user, MergeRequest $mergeRequest): bool
    {
        return $mergeRequest->requested_by === $user->id;
    }

    public function close(User $user, MergeRequest $mergeRequest): bool
    {
        return $mergeRequest->requested_by === $user->id || self::isReviewer($user, $mergeRequest);
    }

    public function comment(User $user, MergeRequest $mergeRequest): bool
    {
        return $this->view($user, $mergeRequest);
    }

    public static function isReviewer(User $user, MergeRequest $mergeRequest): bool
    {
        return $user->can('merge_requests.review') || $mergeRequest->project->canMerge($user);
    }
}
