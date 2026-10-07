<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\OfferLetter;
use App\Services\Onboarding\EmployeeInvitations;
use App\Support\EmployeeProfile;
use App\Support\OnboardingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR's side of onboarding, on the staff profile's Onboarding tab.
 */
class EmployeeOnboardingController extends Controller
{
    public function show(Request $request, Employee $employee): Response
    {
        $viewer = $request->user();
        $canSeePay = $viewer->canSeePayOf($employee);

        return Inertia::render('admin/employees/onboarding', [
            'employee' => EmployeeProfile::header($employee, $viewer),
            'onboarding' => $employee->onboarding_status === null ? null : OnboardingPresenter::state($employee),
            'mailIsLocal' => EmployeeInvitations::mailIsLocal(),
            // The letters HR can generate here: internship letters for interns, offer and welcome letters for staff.
            // Letters that state pay only for those who may see it.
            'letterKinds' => collect($employee->isIntern()
                ? [['kind' => OfferLetter::INTERNSHIP, 'label' => 'internship letter (with stipend)'], ['kind' => OfferLetter::INTERNSHIP_UNPAID, 'label' => 'internship letter (no stipend)']]
                : [['kind' => OfferLetter::OFFER, 'label' => 'offer letter'], ['kind' => OfferLetter::WELCOME, 'label' => 'welcome letter (no salary)']])
                ->filter(fn (array $letter) => $canSeePay || ! OfferLetter::statesPay($letter['kind']))
                ->values(),
            'can' => [
                'manage' => $viewer->can('employees.onboard'),
                'documents' => $viewer->can('documents.view'),
                'offer_letter' => $viewer->can('documents.view') && ($canSeePay || ! OfferLetter::statesPay($employee->offer_letter_kind)),
            ],
        ]);
    }

    public function invite(Employee $employee, EmployeeInvitations $invitations): RedirectResponse
    {
        if ($employee->onboarding_status === null || $employee->onboarding_status === OnboardingStatus::Completed) {
            return back()->with('error', 'This person is not being onboarded.');
        }

        $link = $invitations->send($employee);

        return back()
            ->with('success', "Invite sent to {$employee->user->email}. Any earlier link no longer works.")
            ->with('invite_link', EmployeeInvitations::mailIsLocal() ? $link : null);
    }

    public function uploadOfferLetter(Request $request, Employee $employee): RedirectResponse
    {
        $request->validate(['offer_letter' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx']]);

        EmployeeController::storeOfferLetter($employee, $request->file('offer_letter'));

        return back()->with('success', 'Offer letter attached. Resend the invite to email it.');
    }

    /**
     * Rebuild the letter from the current template and this person's details,
     * e.g. after correcting their salary — or switch between the offer letter
     * and the welcome letter (no salary). Resend the invite to email it.
     */
    public function generateOfferLetter(Request $request, Employee $employee, OfferLetter $offerLetter): RedirectResponse
    {
        $kind = $request->validate(['kind' => ['nullable', Rule::in(OfferLetter::KINDS)]])['kind'] ?? OfferLetter::kindFor($employee);

        abort_if(OfferLetter::statesPay($kind) && ! $request->user()->canSeePayOf($employee), 403, 'This letter states their pay, which you do not have access to.');

        if (! $employee->onboarding_status?->isEditable()) {
            return back()->with('error', 'The offer letter can only change before the profile is submitted.');
        }

        if ($kind === OfferLetter::OFFER && $employee->salary === null) {
            return back()->with('error', 'No salary is recorded, so the offer letter would have none to state. Add it on the profile, or generate the welcome letter.');
        }

        $offerLetter->generateFor($employee, $kind);

        if ($kind === OfferLetter::INTERNSHIP && ! ($employee->has_stipend && $employee->stipend !== null)) {
            return back()->with('error', 'No stipend is recorded, so the letter would have none to state. Add it on the intern, or generate the letter without a stipend.');
        }

        $what = OfferLetter::label($kind);

        return back()->with('success', "{$what} generated from the template. Resend the invite to email it.");
    }

    public function downloadOfferLetter(Request $request, Employee $employee): StreamedResponse
    {
        abort_if($employee->offer_letter_path === null, 404);
        abort_if(OfferLetter::statesPay($employee->offer_letter_kind) && ! $request->user()->canSeePayOf($employee), 403, 'This letter states their pay, which you do not have access to.');

        return Storage::disk(EmployeeDocument::DISK)->download($employee->offer_letter_path, $employee->offer_letter_name);
    }

    public function approve(Request $request, Employee $employee): RedirectResponse
    {
        if ($employee->onboarding_status !== OnboardingStatus::Submitted) {
            return back()->with('error', 'Only a submitted profile can be approved.');
        }

        $employee->forceFill([
            'onboarding_status' => OnboardingStatus::Completed,
            'onboarding_completed_at' => now(),
            'onboarding_reviewed_by' => $request->user()->id,
            'onboarding_note' => null,
        ])->save();

        return back()->with('success', "{$employee->user->name}'s onboarding is complete.");
    }

    public function sendBack(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Say what needs fixing.']);

        if ($employee->onboarding_status !== OnboardingStatus::Submitted) {
            return back()->with('error', 'Only a submitted profile can be sent back.');
        }

        $employee->forceFill([
            'onboarding_status' => OnboardingStatus::Returned,
            'onboarding_reviewed_by' => $request->user()->id,
            'onboarding_note' => $data['note'],
        ])->save();

        return back()->with('success', "Sent back to {$employee->user->name}. They'll see your note the next time they sign in.");
    }
}
