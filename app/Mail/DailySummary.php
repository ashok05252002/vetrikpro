<?php

namespace App\Mail;

use App\Support\MailThread;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Symfony\Component\Mime\Email;

/**
 * The daily summary: one section per project with its status, what is still
 * pending and what changed since this address's last summary. A plain
 * mailable to an address, because leadership and outside recipients may have
 * no account here.
 */
class DailySummary extends Mailable
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $projects  sections from ProjectSummary::build()
     * @param  bool  $leadership  sent for every active project (Configuration hub), not as a project recipient
     */
    public function __construct(
        public readonly array $projects,
        public readonly Carbon $since,
        public readonly bool $leadership = false,
        public readonly ?string $name = null,
    ) {}

    /** Fixed, so every day's summary lands in the same conversation (see MailThread). */
    public const SUBJECT = 'Daily project summary';

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::SUBJECT,
            using: [fn (Email $message) => MailThread::apply($message, 'daily-summary', $message->getTo()[0]->getAddress(), self::SUBJECT)],
        );
    }

    public function content(): Content
    {
        $sum = fn (string $key) => array_sum(array_map(fn (array $p) => $p['counts'][$key], $this->projects));

        return new Content(
            view: 'emails.daily-summary',
            text: 'emails.daily-summary-text',
            with: [
                'today' => Carbon::now($this->timezone())->format('j M Y'),
                'greeting' => $this->name ? 'Hi '.(str($this->name)->before(' ')->value() ?: $this->name).',' : 'Hello,',
                'leadership' => $this->leadership,
                'sinceLabel' => $this->since->copy()->timezone($this->timezone())->format('j M Y, g:i A'),
                'totals' => [
                    'projects' => count($this->projects),
                    'open_tasks' => $sum('open_tasks'),
                    'overdue' => $sum('overdue'),
                    'open_bugs' => $sum('open_bugs'),
                    'reviews' => $sum('reviews'),
                    'completed' => $sum('completed'),
                ],
            ],
        );
    }

    private function timezone(): string
    {
        return (string) app(Settings::class)->get('display.timezone') ?: config('app.timezone');
    }
}
