<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use App\Support\WorkCard;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Tells the assignee and the reporter that a task or bug moved to a new status. */
class WorkStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly Task|TestPoint $card, public readonly User $actor, public readonly string $from) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $card = WorkCard::describe($this->card);
        $to = $this->card->status->label();

        return (new MailMessage)
            ->subject("{$card['reference']} is now {$to}: {$card['title']}")
            ->view(['emails.work', 'emails.work-text'], [
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'eyebrow' => "{$this->from} → {$to}",
                'lead' => "{$this->actor->name} moved this ".($card['kind'] === 'task' ? 'task' : 'bug')." from {$this->from} to {$to}.",
                'accent' => '#0369a1',
                'card' => $card,
                'button' => $card['kind'] === 'task' ? 'Open the task' : 'Open the bug',
            ]);
    }
}
