<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Onboarding\EmployeeInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Opening the welcome email's link: choose a password, and go straight on to
 * completing the profile.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $user = User::where('email', $request->string('email')->value())->first();
        $broker = Password::broker(EmployeeInvitations::BROKER);

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'email' => $request->string('email')->value(),
            'name' => $user?->name,
            // Checked up front, so an expired link says so before anyone types a password.
            'valid' => $user !== null && $broker->tokenExists($user, $token),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $accepted = null;

        $status = Password::broker(EmployeeInvitations::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$accepted) {
                $user->forceFill([
                    'password' => $password,
                    // Opening the link from their inbox proves the address.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                $accepted = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || $accepted === null) {
            throw ValidationException::withMessages(['email' => 'This invite link has expired or was replaced by a newer one. Ask HR to send a new invite.']);
        }

        if (! $accepted->is_active) {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated. Contact your administrator.']);
        }

        $employee = $accepted->employee;

        if ($employee?->onboarding_status === OnboardingStatus::Invited) {
            $employee->forceFill(['onboarding_status' => OnboardingStatus::InProgress])->save();
        }

        Auth::login($accepted);
        $request->session()->regenerate();

        return $employee?->onboarding_status?->needsEmployee()
            ? to_route('onboarding.show')->with('success', 'Your password is set. Now complete your profile.')
            : to_route('dashboard');
    }
}
