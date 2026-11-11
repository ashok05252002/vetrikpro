<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The start confirmation to a customer: "your plan started on …, it renews
 * yearly, and the next renewal is due on …". Selling price only — never our
 * cost, the provider or our notes. Replies go to the company address.
 */
class RenewalStarted extends Mailable
{
    use Queueable;

    /**
     * @param  array{label: string, category: string, customer: string, start: ?string, cycle: string, due: string, amount: string}  $plan
     */
    public function __construct(public readonly array $plan) {}

    public function envelope(): Envelope
    {
        $replyTo = (string) app(Settings::class)->get('company.email');

        return new Envelope(
            subject: "Your {$this->plan['label']} is active — next renewal {$this->plan['due']}",
            replyTo: $replyTo !== '' ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.renewal-started', text: 'emails.renewal-started-text', with: ['plan' => $this->plan]);
    }
}
