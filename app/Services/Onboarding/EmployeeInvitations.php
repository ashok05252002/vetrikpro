<?php

namespace App\Services\Onboarding;

use App\Models\Employee;
use App\Notifications\EmployeeInvitation;
use Illuminate\Support\Facades\Password;

/**
 * Sends (or re-sends) the welcome email. Each send issues a fresh token, so
 * an older link stops working the moment a new one goes out.
 */
final class EmployeeInvitations
{
    public const BROKER = 'invites';

    /**
     * @return string The link that was emailed, for showing to HR while no real mailer is set up.
     */
    public function send(Employee $employee): string
    {
        $user = $employee->user;
        $token = Password::broker(self::BROKER)->createToken($user);
        $url = route('invitation.show', ['token' => $token, 'email' => $user->email]);

        $user->notify(new EmployeeInvitation(
            url: $url,
            expiresInHours: intdiv((int) config('auth.passwords.invites.expire'), 60),
            offerLetterPath: $employee->offer_letter_path,
            offerLetterName: $employee->offer_letter_name,
            offerLetterKind: $employee->offer_letter_kind,
        ));

        $employee->forceFill(['invited_at' => now()])->save();

        return $url;
    }

    /**
     * Mail is only written to the log (or an array in tests), so nobody would
     * receive the link: HR is shown it to pass on by hand.
     */
    public static function mailIsLocal(): bool
    {
        return in_array(config('mail.default'), ['log', 'array'], true);
    }
}
