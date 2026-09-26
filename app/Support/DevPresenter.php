<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Models\Branch;
use App\Models\MergeRequest;

/**
 * Shapes for branches and merge requests, shared by the Git tab, the branch
 * and merge-request pages, and the cross-project inbox.
 */
final class DevPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function branch(Branch $branch): array
    {
        return [
            ...$branch->only('id', 'project_id', 'name', 'base_branch', 'status', 'merged_at', 'created_at'),
            'creator' => $branch->creator?->only('id', 'name'),
            'tasks_count' => $branch->tasks_count ?? null,
            'test_points_count' => $branch->test_points_count ?? null,
            'live_merge_request' => $branch->liveMergeRequest ? self::mergeRequestRow($branch->liveMergeRequest) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mergeRequestRow(MergeRequest $mr): array
    {
        return [
            ...$mr->only('id', 'project_id', 'number', 'title', 'status', 'target_branch', 'created_at', 'merged_at'),
            'reference' => $mr->reference(),
            'branch' => $mr->relationLoaded('branch') ? $mr->branch?->only('id', 'name') : null,
            'project' => $mr->relationLoaded('project') ? $mr->project?->only('id', 'name', 'code') : null,
            'requester' => $mr->relationLoaded('requester') ? $mr->requester?->only('id', 'name') : null,
            'reviewer' => $mr->relationLoaded('reviewer') ? $mr->reviewer?->only('id', 'name') : null,
        ];
    }

    /**
     * What a reviewer wants to know before merging: is the linked work
     * actually finished and tested?
     *
     * @return array{tasks_open: int, tests_not_passed: int, tests_failed: int, ready: bool}
     */
    public static function readiness(Branch $branch): array
    {
        $tasksOpen = $branch->tasks->where('status', '!=', TaskStatus::Done)->count();
        $notPassed = $branch->testPoints->where('status', '!=', TestPointStatus::Closed)->count();
        $failed = $branch->testPoints->where('status', TestPointStatus::Repeated)->count();

        return [
            'tasks_open' => $tasksOpen,
            'tests_not_passed' => $notPassed,
            'tests_failed' => $failed,
            'ready' => $tasksOpen === 0 && $notPassed === 0,
        ];
    }
}
