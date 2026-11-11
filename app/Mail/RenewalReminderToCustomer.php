<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * A renewal reminder to a customer: their own items only, with the selling
 * price and balance — never our cost, our notes or the vendor. Replies go to
 * the company address.
 */
class RenewalReminderToCustomer extends Mailable
{
    use Queueable;

    /**
     * @param  Collection<int, array<string, mixed>>  $items  rows from RenewalReminders::row()
     */
    public function __construct(public readonly Collection $items, public readonly string $customerName) {}

    public function envelope(): Envelope
    {
        $replyTo = (string) app(Settings::class)->get('company.email');
        $overdue = $this->items->contains(fn ($i) => $i['days_left'] < 0);

        return new Envelope(
            subject: ($overdue ? 'Overdue: renewal' : 'Renewal reminder').($this->items->count() === 1 ? " for {$this->items->first()['label']}" : 's due').' — '.config('app.name'),
            replyTo: $replyTo !== '' ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.renewal-customer',
            text: 'emails.renewal-customer-text',
            with: ['items' => $this->items, 'customer' => $this->customerName],
        );
    }
}
