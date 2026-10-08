<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;

/**
 * The compact shape a task or test point takes on a board card, in a list row
 * and wherever one is referenced (a merge request's linked work). One shape
 * per kind, so every screen shows the same thing.
 */
final class Cards
{
    /**
     * A status history, oldest first, as the task and testing pages show it.
     *
     * @return list<array<string, mixed>>
     */
    public static function history(Task|TestPoint $card): array
    {
        return $card->statusChanges()->with('user:id,name')->get()
            ->map(fn ($change) => [
                ...$change->only('id', 'from_status', 'to_status', 'created_at'),
                'user' => $change->user?->only('id', 'name'),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function task(Task $task, ?User $viewer = null): array
    {
        return [
            'can_move' => $viewer !== null && $task->statusChangeableBy($viewer),
            ...$task->only('id', 'project_id', 'number', 'title', 'status', 'priority', 'due_date', 'position'),
            // Plain Y-m-d: a Carbon reaches the page as a UTC ISO string, a day early in Indian time.
            'due_date' => $task->due_date?->toDateString(),
            'reference' => $task->reference(),
            'assignee' => $task->assignee?->only('id', 'name'),
            ...self::people($task),
            'comments_count' => $task->comments_count ?? null,
            'is_overdue' => $task->isOverdue(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function testPoint(TestPoint $point, ?User $viewer = null): array
    {
        return [
            'can_move' => $viewer !== null && $point->statusChangeableBy($viewer),
            ...$point->only('id', 'project_id', 'number', 'title', 'status', 'priority', 'position'),
            'reference' => $point->reference(),
            'assignee' => $point->assignee?->only('id', 'name'),
            'reporter' => $point->creator?->only('id', 'name'),
            ...self::people($point),
            'task' => $point->task ? ['id' => $point->task->id, 'reference' => $point->task->reference(), 'title' => $point->task->title] : null,
            'last_tested_at' => $point->last_tested_at,
        ];
    }

    /**
     * Who added it and who assigned it — only where the caller loaded them, so
     * a screen that doesn't show them pays no query for them.
     *
     * @return array<string, mixed>
     */
    private static function people(Task|TestPoint $card): array
    {
        return [
            ...($card->relationLoaded('creator') ? ['creator' => $card->creator?->only('id', 'name')] : []),
            ...($card->relationLoaded('assigner') ? ['assigner' => $card->assigner?->only('id', 'name')] : []),
        ];
    }
}
