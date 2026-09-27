<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Notifications\PromotionAnnounced;
use App\Services\PromotionLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Promotions and salary revisions. Recording one updates the employee's
 * designation, department and salary at once, keeps a row of what changed,
 * and — unless HR says not to — emails the person their letter.
 */
class PromotionController extends Controller
{
    public function store(PromotionRequest $request, Employee $employee, PromotionLetter $letter): RedirectResponse
    {
        $data = $request->validated();
        $to = Designation::findOrFail($data['to_designation_id']);
        // A designation that belongs to a department brings them into it, unless HR chose otherwise.
        $toDepartment = $data['to_department_id'] ?? $to->department_id ?? $employee->department_id;

        $employee->loadMissing('designation:id,name');

        $promotion = DB::transaction(function () use ($employee, $data, $to, $toDepartment, $request, $letter) {
            $promotion = Promotion::create([
                'employee_id' => $employee->id,
                'from_designation_id' => $employee->designation_id,
                'to_designation_id' => $to->id,
                'from_designation_name' => $employee->designation?->name,
                'to_designation_name' => $to->name,
                'from_department_id' => $employee->department_id,
                'to_department_id' => $toDepartment,
                'from_salary' => $employee->salary,
                'to_salary' => $data['to_salary'],
                'effective_date' => $data['effective_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $employee->update([
                'designation_id' => $to->id,
                'department_id' => $toDepartment,
                'salary' => $data['to_salary'],
            ]);

            $letter->generateFor($promotion);

            return $promotion;
        });

        $name = $employee->user->name;
        $what = $promotion->isDesignationChange() ? "{$name} was promoted to {$to->name}" : "{$name}'s salary was revised";

        if (! $request->boolean('send_email', true)) {
            return back()->with('success', "{$what}. No email was sent; the letter is on their profile.");
        }

        // After commit: the promotion stands whether or not the mail goes through.
        try {
            $employee->user->notify(new PromotionAnnounced($promotion));
            $promotion->forceFill(['emailed_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "{$what}, but the email could not be sent. Download the letter from their profile and send it by hand.");
        }

        return back()->with('success', "{$what}. The letter was emailed to {$employee->user->email}.");
    }

    public function letter(Employee $employee, Promotion $promotion): StreamedResponse
    {
        abort_if($promotion->letter_path === null, 404);

        $kind = $promotion->isDesignationChange() ? 'Promotion letter' : 'Salary revision';

        return Storage::disk(EmployeeDocument::DISK)->download($promotion->letter_path, "{$kind} - {$employee->user->name} - {$promotion->effective_date->format('Y-m-d')}.pdf");
    }
}
