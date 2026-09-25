<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Hands out per-project numbers: T-1, T-2… for tasks, TP-1… for test points,
 * REQ-1… for requirement documents.
 *
 * The project row is locked while the next number is read, so two people
 * creating at the same moment are served one after the other rather than both
 * getting the same number. This only holds inside a transaction, which is why
 * every creating code path runs in one; the unique (project_id, number) index
 * is the backstop that turns any missed case into an error, never a duplicate.
 */
final class ProjectSequence
{
    public static function next(int $projectId, string $table): int
    {
        Project::query()->whereKey($projectId)->lockForUpdate()->value('id');

        return (int) DB::table($table)->where('project_id', $projectId)->max('number') + 1;
    }
}
