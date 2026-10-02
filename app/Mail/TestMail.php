<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A short branded note sent from Settings to prove the mail setup reaches
 * a real inbox. It names the mailer and host it went through, so whoever
 * receives it can tell which server sent it.
 */
class TestMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $sentBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.config('app.name'));
    }

    public function content(): Content
    {
        $mailer = (string) config('mail.default');

        return new Content(
            view: 'emails.test',
            text: 'emails.test-text',
            with: [
                'sentBy' => $this->sentBy,
                'facts' => array_filter([
                    'Sent from' => config('app.url'),
                    'Mailer' => $mailer,
                    'Host' => $mailer === 'smtp' ? config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port') : null,
                    'Sent at' => now()->format('j M Y, H:i:s T'),
                ]),
            ],
        );
    }
}
