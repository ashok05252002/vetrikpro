<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the assignee a task needs them now: it was created urgent, marked
 * urgent, or an urgent task was handed to them.
 */
class TaskMarkedUrgent extends Notification
{
    use Queueable;

    public const CREATED = 'created';

    public const ESCALATED = 'escalated';

    public const REASSIGNED = 'reassigned';

    public function __construct(public readonly Task $task, public readonly ?User $actor, public readonly string $why) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $task = $this->task->loadMissing(['project:id,name,code', 'creator:id,name']);
        $actor = $this->actor?->name ?? 'Someone';

        return (new MailMessage)
            ->subject("⚡ Urgent: {$task->reference()} {$task->title}")
            ->view(['emails.urgent-task', 'emails.urgent-task-text'], [
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'actor' => $actor,
                'reason' => match ($this->why) {
                    self::CREATED => 'assigned you a new task and marked it urgent',
                    self::REASSIGNED => 'assigned you this urgent task',
                    default => 'marked your task as urgent',
                },
                'task' => [
                    'reference' => $task->reference(),
                    'title' => $task->title,
                    'description' => $task->description,
                ],
                'facts' => array_filter([
                    'Project' => $task->project ? "{$task->project->name} ({$task->project->code})" : null,
                    'Stage' => $task->status->label(),
                    'Due' => $task->due_date ? $task->due_date->format('j M Y').($task->isOverdue() ? ' — overdue' : '') : 'No due date',
                    'Created by' => $task->creator?->name,
                ]),
                'url' => route('tasks.show', $task),
            ]);
    }
}
