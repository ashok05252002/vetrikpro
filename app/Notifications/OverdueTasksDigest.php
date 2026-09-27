<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The daily overdue email: one per person, listing every overdue task they
 * hold — or, for a project owner, the overdue tasks across their projects.
 */
class OverdueTasksDigest extends Notification
{
    use Queueable;

    public const MINE = 'mine';

    public const OWNED = 'owned';

    /**
     * @param  Collection<int, array{reference: string, title: string, project: string, due: string, days: int, assignee: ?string, url: string}>  $tasks
     */
    public function __construct(public readonly Collection $tasks, public readonly string $scope = self::MINE) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $count = $this->tasks->count();
        $noun = $count === 1 ? 'task' : 'tasks';
        $mine = $this->scope === self::MINE;

        return (new MailMessage)
            ->subject($mine ? "You have {$count} overdue {$noun}" : "{$count} overdue {$noun} on your projects")
            ->view(['emails.overdue', 'emails.overdue-text'], [
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'mine' => $mine,
                'count' => $count,
                'noun' => $noun,
                'tasks' => $this->tasks,
                'url' => $mine ? route('tasks.index', ['overdue' => 1]) : route('tasks.index', ['overdue' => 1, 'scope' => 'all']),
            ]);
    }
}
