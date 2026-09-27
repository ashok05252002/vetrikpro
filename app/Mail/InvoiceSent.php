<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Accounts\InvoicePdf;
use App\Support\Letterhead;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An invoice to a customer: a short branded note with the PDF attached. The
 * customer has no account here, so this is a plain mailable to an address,
 * not a notification to a user.
 */
class InvoiceSent extends Mailable
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice, public readonly ?string $note = null) {}

    public function envelope(): Envelope
    {
        $replyTo = (string) app(Settings::class)->get('company.email');

        // Replies go to the company's address, not the mailer's no-reply sender.
        return new Envelope(
            subject: "Invoice {$this->invoice->reference()} from ".config('app.name'),
            replyTo: $replyTo !== '' ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        $letterhead = app(Letterhead::class);

        return new Content(
            view: 'emails.invoice',
            text: 'emails.invoice-text',
            with: [
                'reference' => $this->invoice->reference(),
                'customer' => $this->invoice->bill_name,
                'total' => $letterhead->money((float) $this->invoice->total, 2),
                'issueDate' => $letterhead->date($this->invoice->issue_date),
                'dueDate' => $this->invoice->due_date ? $letterhead->date($this->invoice->due_date) : null,
                'note' => $this->note,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(InvoicePdf::class);

        return [
            Attachment::fromData(fn () => $pdf->render($this->invoice), $pdf->filename($this->invoice))->withMime('application/pdf'),
        ];
    }
}
