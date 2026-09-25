<?php

namespace App\Services\Onboarding;

use App\Models\DocumentType;
use App\Models\Employee;

/**
 * What an employee still has to do before they can submit their profile.
 *
 * One definition, read by the employee's onboarding page, the submit
 * validation and HR's review tab, so all three always agree on what
 * "complete" means. Required documents come from Configuration → Document
 * types; the signed offer letter is only asked for once HR has attached one.
 */
final class OnboardingChecklist
{
    /**
     * @return list<array{key: string, label: string, done: bool, required: bool, section: string}>
     */
    public static function for(Employee $employee): array
    {
        $uploaded = $employee->documents()->pluck('document_type_id')->unique()->all();

        $items = [
            ['key' => 'details', 'label' => 'Personal details', 'section' => 'details', 'required' => true,
                'done' => filled($employee->phone) && filled($employee->date_of_birth) && filled($employee->address)],
            ['key' => 'bank', 'label' => 'Bank details', 'section' => 'bank', 'required' => true,
                'done' => filled($employee->bank_account_name) && filled($employee->bank_account_number) && filled($employee->bank_ifsc) && filled($employee->bank_name)],
        ];

        foreach (DocumentType::active()->ordered()->get() as $type) {
            $isSigned = $type->code === DocumentType::SIGNED_OFFER_LETTER;

            if ($isSigned && $employee->offer_letter_path === null) {
                continue;
            }

            $items[] = [
                'key' => "document:{$type->id}",
                'label' => $type->name,
                'section' => $isSigned ? 'offer' : 'documents',
                'required' => $type->is_required,
                'done' => in_array($type->id, $uploaded, true),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{required: bool, done: bool}>|null  $items
     * @return array{done: int, total: int, percent: int, complete: bool}
     */
    public static function progress(Employee $employee, ?array $items = null): array
    {
        $required = array_filter($items ?? self::for($employee), fn (array $item) => $item['required']);
        $done = count(array_filter($required, fn (array $item) => $item['done']));
        $total = count($required);

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total === 0 ? 100 : (int) floor($done / $total * 100),
            'complete' => $done === $total,
        ];
    }
}
