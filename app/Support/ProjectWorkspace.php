<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

/**
 * The header every project workspace tab renders: identity, owner, counts and
 * what the viewer may do. Each tab controller adds only its own data.
 */
final class ProjectWorkspace
{
    /**
     * @return array<string, mixed>
     */
    public static function header(Project $project, User $viewer): array
    {
        $project->loadMissing('owner:id,name')->loadCount('members');

        return [
            ...$project->only('id', 'name', 'code', 'description', 'status', 'start_date', 'due_date', 'repository_url', 'default_branch'),
            'owner' => $project->owner?->only('id', 'name'),
            'members_count' => $project->members_count,
            'progress' => $project->progress(),
            'viewer' => [
                'is_dev_admin' => $project->isDevAdmin($viewer),
                'can_update' => $viewer->can('update', $project),
                'can_manage_members' => $viewer->can('manageMembers', $project),
            ],
        ];
    }

    /**
     * Counts for the Testing module's two tabs: testing points and runs.
     *
     * @return array{points: int, runs: int}
     */
    public static function testingCounts(Project $project): array
    {
        return [
            'points' => $project->testPoints()->count(),
            'runs' => $project->testRuns()->count(),
        ];
    }
}
