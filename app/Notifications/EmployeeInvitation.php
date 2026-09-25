<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

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

        $mail = (new MailMessage)
            ->subject("Welcome to {$company} — set up your account")
            ->greeting("Hello {$notifiable->name},")
            ->line("An account has been created for you on the {$company} portal. Your sign-in email is {$notifiable->email}.")
            ->action('Set your password', $this->url)
            ->line("The link works for {$this->expiresInHours} hours. After setting your password you'll be asked to:")
            ->line('• confirm your personal details')
            ->line('• add your bank details for salary')
            ->line('• upload the documents on your checklist');

        if ($this->offerLetterPath !== null) {
            $mail->line('• sign the attached offer letter and upload the signed copy')
                ->attachData(
                    Storage::disk(EmployeeDocument::DISK)->get($this->offerLetterPath),
                    $this->offerLetterName ?? 'offer-letter.pdf',
                );
        }

        return $mail->line('If the link has expired, ask HR to send you a new one.')
            ->salutation("— {$company} HR");
    }
}
