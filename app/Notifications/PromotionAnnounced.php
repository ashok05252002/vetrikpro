<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Models\User;
use App\Support\Letterhead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Congratulates someone on a promotion or salary revision, with the letter
 * attached. The amounts are in the letter and the email alike: the person is
 * the only recipient.
 */
class PromotionAnnounced extends Notification
{
    use Queueable;

    public function __construct(public readonly Promotion $promotion) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $promotion = $this->promotion;
        $letterhead = app(Letterhead::class);
        $promoted = $promotion->isDesignationChange();
        $from = $promotion->from_salary !== null ? (float) $promotion->from_salary : null;
        $to = (float) $promotion->to_salary;
        $percent = $promotion->incrementPercent();

        $mail = (new MailMessage)
            ->subject($promoted ? "Congratulations on your promotion to {$promotion->to_designation_name}" : 'Your salary has been revised')
            ->view(['emails.promotion', 'emails.promotion-text'], [
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'promoted' => $promoted,
                'designation' => $promotion->to_designation_name,
                'effective' => $letterhead->date($promotion->effective_date),
                'facts' => array_filter([
                    'New designation' => $promoted ? $promotion->to_designation_name : null,
                    'Previous designation' => $promoted ? $promotion->from_designation_name : null,
                    'New monthly salary' => $letterhead->money($to),
                    'Increase' => $from !== null && $to > $from ? $letterhead->money($to - $from).($percent !== null ? " ({$percent}%)" : '') : null,
                    'Effective from' => $letterhead->date($promotion->effective_date),
                ]),
                'hasLetter' => $promotion->letter_path !== null,
                'url' => route('dashboard'),
            ]);

        if ($promotion->letter_path !== null) {
            $mail->attachData(
                Storage::disk(EmployeeDocument::DISK)->get($promotion->letter_path),
                ($promoted ? 'Promotion letter' : 'Salary revision').' - '.$notifiable->name.'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }
}
