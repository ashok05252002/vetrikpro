<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The welcome email: a link to set a password, what to do after signing in,
 * and the offer letter attached when HR has added one. No password is ever
 * written into the email.
 */
class EmployeeInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly int $expiresInHours,
        public readonly ?string $offerLetterPath = null,
        public readonly ?string $offerLetterName = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $company = config('app.name');

        $steps = [
            'Choose your password with the button above.',
            'Confirm your personal details.',
            'Add your bank details for salary.',
            'Upload the documents on your checklist.',
        ];

        if ($this->offerLetterPath !== null) {
            $steps[] = 'Sign the attached offer letter and upload the signed copy.';
        }

        $mail = (new MailMessage)
            ->subject("Welcome to {$company} — set up your account")
            ->view(['emails.invitation', 'emails.invitation-text'], [
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'email' => $notifiable->email,
                'url' => $this->url,
                'expiresInHours' => $this->expiresInHours,
                'steps' => $steps,
                'hasOfferLetter' => $this->offerLetterPath !== null,
            ]);

        if ($this->offerLetterPath !== null) {
            $mail->attachData(
                Storage::disk(EmployeeDocument::DISK)->get($this->offerLetterPath),
                $this->offerLetterName ?? 'offer-letter.pdf',
            );
        }

        return $mail;
    }
}
