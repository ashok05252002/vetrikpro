<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TestPoint;

/**
 * How a task or a bug is described in an email: what kind it is, its
 * reference and title, its facts and its link. One place, so every email
 * about work reads the same.
 */
final class WorkCard
{
    /**
     * @return array{kind: string, reference: string, title: string, url: string, facts: array<string, string>, project: ?string}
     */
    public static function describe(Task|TestPoint $card): array
    {
        $card->loadMissing(['project:id,name,code', 'assignee:id,name', 'creator:id,name']);
        $isTask = $card instanceof Task;

        return [
            'kind' => $isTask ? 'task' : 'bug',
            'reference' => $card->reference(),
            'title' => $card->title,
            'project' => $card->project ? "{$card->project->name} ({$card->project->code})" : null,
            'url' => $isTask ? route('tasks.show', $card) : route('testing.points.show', [$card->project_id, $card]),
            'facts' => array_filter([
                'Project' => $card->project ? "{$card->project->name} ({$card->project->code})" : null,
                'Status' => $card->status->label(),
                'Priority' => $card->priority?->label(),
                'Due' => $isTask && $card->due_date ? $card->due_date->format('j M Y').($card->isOverdue() ? ' — overdue' : '') : null,
                'Assigned to' => $card->assignee?->name,
                $isTask ? 'Created by' : 'Reported by' => $card->creator?->name,
            ]),
        ];
    }
}
