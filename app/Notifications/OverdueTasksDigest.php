<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\MailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * The daily overdue email: one per person, listing every overdue task they
 * hold. Owners see their projects' overdue tasks in the daily summary.
 */
class OverdueTasksDigest extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, array{reference: string, title: string, project: string, due: string, days: int, assignee: ?string, url: string}>  $tasks
     */
    /** Fixed, so every day's email lands in the same conversation (see MailThread); the count is in the heading. */
    public const SUBJECT = 'Your overdue tasks';

    public function __construct(public readonly Collection $tasks) {}

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

        return (new MailMessage)
            ->subject(self::SUBJECT)
            ->withSymfonyMessage(fn (Email $message) => MailThread::apply($message, 'overdue-digest', $notifiable->email, self::SUBJECT))
            ->view(['emails.overdue', 'emails.overdue-text'], [
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'count' => $count,
                'noun' => $noun,
                'tasks' => $this->tasks,
                'url' => route('tasks.index', ['overdue' => 1]),
            ]);
    }
}
