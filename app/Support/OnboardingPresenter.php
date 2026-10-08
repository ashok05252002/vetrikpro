<?php

namespace App\Support;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Onboarding\OnboardingChecklist;

/**
 * The onboarding state as both sides see it: the employee on their own
 * onboarding page, HR on the profile's Onboarding tab.
 */
final class OnboardingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function state(Employee $employee, bool $fullAccountNumber = false): array
    {
        $items = OnboardingChecklist::for($employee);

        return [
            'status' => $employee->onboarding_status?->value,
            'status_label' => $employee->onboarding_status?->label(),
            'editable' => (bool) $employee->onboarding_status?->isEditable(),
            'invited_at' => $employee->invited_at,
            'submitted_at' => $employee->onboarding_submitted_at,
            'completed_at' => $employee->onboarding_completed_at,
            'note' => $employee->onboarding_note,
            'checklist' => $items,
            'progress' => OnboardingChecklist::progress($employee, $items),
            'details' => [
                ...$employee->only('phone', 'gender', 'address'),
                // Plain Y-m-d: a Carbon reaches the page as a UTC ISO string, a day early in Indian time.
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
            ],
            'bank' => [
                'account_name' => $employee->bank_account_name,
                'account_number' => $fullAccountNumber ? $employee->bank_account_number : $employee->maskedAccountNumber(),
                'ifsc' => $employee->bank_ifsc,
                'bank_name' => $employee->bank_name,
                'branch' => $employee->bank_branch,
            ],
            'offer_letter' => $employee->offer_letter_path ? ['name' => $employee->offer_letter_name, 'kind' => $employee->offer_letter_kind] : null,
            'slots' => self::slots($employee),
        ];
    }

    /**
     * One upload slot per active document type, with whatever is in it.
     *
     * @return list<array<string, mixed>>
     */
    public static function slots(Employee $employee): array
    {
        $documents = $employee->documents()->get()->groupBy('document_type_id');

        return DocumentType::active()->ordered()->get()
            ->reject(fn (DocumentType $type) => $type->code === DocumentType::SIGNED_OFFER_LETTER && $employee->offer_letter_path === null)
            ->map(fn (DocumentType $type) => [
                'type_id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'required' => $type->is_required,
                'is_offer_letter' => $type->code === DocumentType::SIGNED_OFFER_LETTER,
                'document' => ($doc = $documents->get($type->id)?->first()) ? self::document($doc) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(EmployeeDocument $document): array
    {
        return [
            ...$document->only('id', 'title', 'original_name', 'size'),
            'uploaded_at' => $document->created_at,
        ];
    }
}
