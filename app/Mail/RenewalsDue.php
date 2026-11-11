<?php

namespace App\Mail;

use App\Support\MailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use Symfony\Component\Mime\Email;

/**
 * The daily renewal reminder to our side: one email per address listing
 * every renewal due or overdue for them, most urgent first. Sent to an
 * address, not a user, because reminder addresses need no account.
 */
class RenewalsDue extends Mailable
{
    use Queueable;

    /** Fixed, so each day's reminder lands in the same conversation (MailThread). */
    public const SUBJECT = 'Renewals due';

    /**
     * @param  Collection<int, array<string, mixed>>  $items  rows from RenewalReminders::row()
     */
    public function __construct(public readonly Collection $items, public readonly string $recipient) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECT);
    }

    public function build(): static
    {
        return $this->withSymfonyMessage(fn (Email $message) => MailThread::apply($message, 'renewals-due', $this->recipient, self::SUBJECT));
    }

    public function content(): Content
    {
        $overdue = $this->items->where('days_left', '<', 0)->count();
        $count = $this->items->count();

        return new Content(
            view: 'emails.renewals-due',
            text: 'emails.renewals-due-text',
            with: [
                'items' => $this->items,
                'count' => $count,
                'overdue' => $overdue,
                'heading' => $overdue > 0
                    ? "{$overdue} overdue, ".($count - $overdue).' coming up'
                    : "{$count} renewal".($count === 1 ? '' : 's').' coming up',
                'url' => route('accounts.renewals.index', ['due' => 'reminding']),
            ],
        );
    }
}
