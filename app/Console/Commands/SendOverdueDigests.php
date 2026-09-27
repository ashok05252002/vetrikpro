<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\OverdueTasksDigest;
use App\Support\Clock;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The daily overdue email, scheduled at the time set in Configuration hub →
 * Email notifications. Each person with overdue tasks gets one email listing
 * them; each project owner gets one summary of their projects' overdue tasks.
 */
class SendOverdueDigests extends Command
{
    protected $signature = 'tasks:overdue-digest';

    protected $description = 'Email everyone their overdue tasks, and project owners a summary';

    public function handle(Settings $settings): int
    {
        if (! $settings->get('notify.overdue.enabled')) {
            $this->info('Overdue emails are switched off.');

            return self::SUCCESS;
        }

        // Only live work: a project on hold, completed or archived is not chased daily.
        $tasks = Task::query()->overdue()
            ->whereHas('project', fn ($q) => $q->where('status', ProjectStatus::Active))
            ->with(['project:id,name,code,owner_id', 'assignee:id,name'])
            ->orderBy('due_date')
            ->get();

        $row = fn (Task $task) => [
            'reference' => $task->reference(),
            'title' => $task->title,
            'project' => $task->project?->name ?? '—',
            'due' => $task->due_date->format('j M Y'),
            'days' => (int) abs($task->due_date->diffInDays(Clock::today())),
            'assignee' => $task->assignee?->name,
            'url' => route('tasks.show', $task),
        ];

        $sent = $this->send($tasks->whereNotNull('assigned_to')->groupBy('assigned_to'), $row, OverdueTasksDigest::MINE);

        if ($settings->get('notify.overdue.owners')) {
            // An owner's own overdue tasks are already in their personal email; the
            // summary lists everyone else's, so nothing arrives twice.
            $owned = $tasks->filter(fn (Task $t) => $t->project?->owner_id && $t->assigned_to !== $t->project->owner_id);
            $sent += $this->send($owned->groupBy(fn (Task $t) => $t->project->owner_id), $row, OverdueTasksDigest::OWNED);
        }

        $this->info("{$tasks->count()} overdue tasks; {$sent} emails sent.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int|string, Collection<int, Task>>  $groups  keyed by user id
     */
    private function send(Collection $groups, callable $row, string $scope): int
    {
        $people = User::whereIn('id', $groups->keys())->where('is_active', true)->get();
        $sent = 0;

        foreach ($people as $person) {
            try {
                $person->notify(new OverdueTasksDigest($groups->get($person->id)->map($row)->values(), $scope));
                $sent++;
            } catch (Throwable $e) {
                // One bad address must not stop everyone else's email.
                report($e);
            }
        }

        return $sent;
    }
}
