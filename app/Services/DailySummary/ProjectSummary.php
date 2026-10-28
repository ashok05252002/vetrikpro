<?php

namespace App\Services\DailySummary;

use App\Enums\MergeRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Models\MergeRequest;
use App\Models\Project;
use App\Models\StatusChange;
use App\Models\Task;
use App\Models\TestPoint;
use App\Support\Clock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One project's section of the daily summary email: where it stands, what is
 * still pending, and what changed since the recipient's last email.
 *
 * The changes come from the status log (`status_changes`), which every task
 * and bug writes from its model events, so nothing that moved is missed
 * however it was moved.
 */
final class ProjectSummary
{
    /** Rows shown per list; the rest are counted as "and N more". */
    public const LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project, Carbon $since): array
    {
        $project->loadMissing('owner:id,name');
        $today = Clock::today();

        $tasks = $project->tasks()->with('assignee:id,name')->get();
        $open = $tasks->where('status', '!=', TaskStatus::Done);

        $overdue = $open->filter(fn (Task $t) => $t->isOverdue())->sortBy('due_date');
        $dueSoon = $open->filter(fn (Task $t) => $t->due_date !== null && ! $t->isOverdue() && $t->due_date->lte($today->copy()->addWeek()))->sortBy('due_date');

        $bugs = $project->testPoints()->with('assignee:id,name')
            ->where('status', '!=', TestPointStatus::Closed)
            ->orderBy('number')
            ->get();

        $reviews = $project->mergeRequests()->with(['requester:id,name', 'reviewer:id,name'])
            ->where('status', MergeRequestStatus::Open)
            ->orderBy('number')
            ->get();

        $taskChanges = $this->changes($project, Task::class, $since, fn (string $s) => TaskStatus::from($s)->label(), 'tasks.show');
        $bugChanges = $this->changes($project, TestPoint::class, $since, fn (string $s) => TestPointStatus::from($s)->label(), 'testing.points.show');

        $merged = $project->mergeRequests()->with('mergedBy:id,name')
            ->where('merged_at', '>', $since)
            ->orderBy('merged_at')
            ->get();

        $stages = collect(TaskStatus::cases())->map(fn (TaskStatus $s) => [
            'label' => $s->label(),
            'count' => $tasks->where('status', $s)->count(),
        ])->all();

        return [
            'name' => $project->name,
            'code' => $project->code,
            'owner' => $project->owner?->name,
            'url' => route('projects.show', $project),
            'progress' => $tasks->isEmpty() ? 0 : (int) round($tasks->where('status', TaskStatus::Done)->count() / $tasks->count() * 100),
            'due' => $project->due_date?->format('j M Y'),
            'stages' => $stages,
            'counts' => [
                'open_tasks' => $open->count(),
                'overdue' => $overdue->count(),
                'open_bugs' => $bugs->count(),
                'reviews' => $reviews->count(),
                'completed' => $taskChanges['rows']->where('to', TaskStatus::Done->label())->count(),
                'created' => $taskChanges['rows']->where('created', true)->count(),
                'bugs_reported' => $bugChanges['rows']->where('created', true)->count(),
                'bugs_closed' => $bugChanges['rows']->where('to', TestPointStatus::Closed->label())->count(),
                'merged' => $merged->count(),
            ],
            'pending' => [
                'overdue' => $this->limit($overdue->map(fn (Task $t) => [
                    ...$this->taskRow($t),
                    'days' => (int) abs($t->due_date->diffInDays($today)),
                ])),
                'due_soon' => $this->limit($dueSoon->map(fn (Task $t) => $this->taskRow($t))),
                'bugs' => $this->limit($bugs->map(fn (TestPoint $b) => [
                    'reference' => $b->reference(),
                    'title' => $b->title,
                    'status' => $b->status->label(),
                    'assignee' => $b->assignee?->name,
                    'url' => route('testing.points.show', [$project, $b]),
                ])),
                'reviews' => $this->limit($reviews->map(fn (MergeRequest $m) => [
                    'reference' => $m->reference(),
                    'title' => $m->title,
                    'requester' => $m->requester?->name,
                    'reviewer' => $m->reviewer?->name,
                    'url' => route('projects.merge-requests.show', [$project, $m]),
                ])),
            ],
            'changes' => [
                'tasks' => $this->limit($taskChanges['rows']),
                'bugs' => $this->limit($bugChanges['rows']),
                'merged' => $this->limit($merged->map(fn (MergeRequest $m) => [
                    'reference' => $m->reference(),
                    'title' => $m->title,
                    'by' => $m->mergedBy?->name,
                    'url' => route('projects.merge-requests.show', [$project, $m]),
                ])),
            ],
            'changed' => $taskChanges['rows']->isNotEmpty() || $bugChanges['rows']->isNotEmpty() || $merged->isNotEmpty(),
        ];
    }

    /**
     * Status changes since `$since`, one row per task or bug: where it was
     * before the window and where it is now, and who moved it last. A row the
     * window saw created reads "New".
     *
     * @param  class-string<Task|TestPoint>  $model
     * @return array{rows: Collection<int, array<string, mixed>>}
     */
    private function changes(Project $project, string $model, Carbon $since, callable $label, string $route): array
    {
        $subjects = $model::query()->where('project_id', $project->id)->with('assignee:id,name')->get()->keyBy('id');

        $rows = StatusChange::query()
            ->where('subject_type', (new $model)->getMorphClass())
            ->whereIn('subject_id', $subjects->keys())
            ->where('created_at', '>', $since)
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id')
            ->map(function (Collection $log, int $id) use ($subjects, $label, $route, $project) {
                $subject = $subjects->get($id);
                $first = $log->first();
                $last = $log->last();
                $created = $first->from_status === null;

                // Moved there and back inside the window: nothing to report.
                if (! $created && $first->from_status === $last->to_status) {
                    return null;
                }

                return [
                    'reference' => $subject->reference(),
                    'title' => $subject->title,
                    'created' => $created,
                    'from' => $created ? null : $label($first->from_status),
                    'to' => $label($last->to_status),
                    'by' => $last->user?->name,
                    'assignee' => $subject->assignee?->name,
                    'url' => $subject instanceof Task ? route($route, $subject) : route($route, [$project, $subject]),
                ];
            })
            ->filter()
            ->values();

        return ['rows' => $rows];
    }

    /**
     * @return array{reference: string, title: string, assignee: ?string, due: string, url: string}
     */
    private function taskRow(Task $task): array
    {
        return [
            'reference' => $task->reference(),
            'title' => $task->title,
            'assignee' => $task->assignee?->name,
            'due' => $task->due_date->format('j M Y'),
            'url' => route('tasks.show', $task),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, more: int}
     */
    private function limit(Collection $rows): array
    {
        return [
            'rows' => $rows->take(self::LIMIT)->values()->all(),
            'more' => max(0, $rows->count() - self::LIMIT),
        ];
    }
}
