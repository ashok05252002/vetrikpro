<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\OnboardingStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Onboarding\OnboardingChecklist;
use App\Support\OnboardingPresenter;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The employee's own "complete your profile" page. Everything here acts on
 * the signed-in user's own employee record and nothing else, so it needs no
 * permission beyond being that person.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $employee = $this->employee($request);

        if ($employee->onboarding_status === null || $employee->onboarding_status === OnboardingStatus::Completed) {
            return to_route('dashboard');
        }

        // Someone who already had a password lands here without the invite step.
        if ($employee->onboarding_status === OnboardingStatus::Invited) {
            $employee->forceFill(['onboarding_status' => OnboardingStatus::InProgress])->save();
        }

        return Inertia::render('onboarding/show', [
            'onboarding' => OnboardingPresenter::state($employee->refresh(), fullAccountNumber: true),
            'person' => [
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'employee_code' => $employee->employee_code,
            ],
        ]);
    }

    public function updateDetails(Request $request): RedirectResponse
    {
        $employee = $this->editable($request);

        $employee->update($request->validate([
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,}$/'],
            'date_of_birth' => ['required', 'date', 'before:-14 years'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address' => ['required', 'string', 'max:2000'],
        ], ['date_of_birth.before' => 'Check the date of birth.', 'phone.regex' => 'Enter a valid phone number.']));

        return back()->with('success', 'Details saved.');
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $employee = $this->editable($request);

        $request->merge(['ifsc' => strtoupper(trim((string) $request->input('ifsc')))]);

        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{6,18}$/', 'confirmed'],
            'ifsc' => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
        ], [
            'account_number.regex' => 'An account number is 6 to 18 digits.',
            'account_number.confirmed' => 'The two account numbers do not match.',
            'ifsc.regex' => 'An IFSC code looks like SBIN0001234: four letters, a zero, then six letters or digits.',
        ]);

        $employee->update([
            'bank_account_name' => $data['account_name'],
            'bank_account_number' => $data['account_number'],
            'bank_ifsc' => $data['ifsc'],
            'bank_name' => $data['bank_name'],
            'bank_branch' => $data['branch'] ?? null,
        ]);

        return back()->with('success', 'Bank details saved.');
    }

    /**
     * One file per document type: uploading again replaces the previous one.
     */
    public function uploadDocument(Request $request): RedirectResponse
    {
        $employee = $this->editable($request);

        $data = $request->validate([
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'file' => Uploads::documentRule(),
        ]);

        $type = DocumentType::findOrFail($data['document_type_id']);
        $file = $request->file('file');

        DB::transaction(function () use ($employee, $type, $file, $request) {
            // Model deletes, so each replaced file leaves the disk too.
            $employee->documents()->where('document_type_id', $type->id)->get()->each->delete();

            $employee->documents()->create([
                'document_type_id' => $type->id,
                'title' => $type->name,
                'file_path' => $file->store(EmployeeDocument::directoryFor($employee->id), EmployeeDocument::DISK),
                ...Uploads::meta($file),
                'uploaded_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', "{$type->name} uploaded.");
    }

    public function deleteDocument(Request $request, EmployeeDocument $document): RedirectResponse
    {
        $employee = $this->editable($request);
        abort_unless($document->employee_id === $employee->id, 404);

        $document->delete();

        return back();
    }

    public function downloadDocument(Request $request, EmployeeDocument $document): StreamedResponse
    {
        abort_unless($document->employee_id === $this->employee($request)->id, 404);

        return Storage::disk(EmployeeDocument::DISK)->download($document->file_path, $document->original_name);
    }

    public function downloadOfferLetter(Request $request): StreamedResponse
    {
        $employee = $this->employee($request);
        abort_if($employee->offer_letter_path === null, 404);

        return Storage::disk(EmployeeDocument::DISK)->download($employee->offer_letter_path, $employee->offer_letter_name);
    }

    public function submit(Request $request): RedirectResponse
    {
        $employee = $this->editable($request);
        $progress = OnboardingChecklist::progress($employee);

        if (! $progress['complete']) {
            return back()->with('error', 'Finish every required item first — '.($progress['total'] - $progress['done']).' still to go.');
        }

        $employee->forceFill([
            'onboarding_status' => OnboardingStatus::Submitted,
            'onboarding_submitted_at' => now(),
        ])->save();

        return to_route('dashboard')->with('success', 'Thanks — your profile has been sent to HR for review.');
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee ?? abort(404);
    }

    /**
     * Only while the employee still has work to do; a submitted profile is
     * HR's to review until they send it back.
     */
    private function editable(Request $request): Employee
    {
        $employee = $this->employee($request);

        abort_unless((bool) $employee->onboarding_status?->isEditable(), 403, 'Your profile has been submitted and is waiting for HR.');

        return $employee;
    }
}
