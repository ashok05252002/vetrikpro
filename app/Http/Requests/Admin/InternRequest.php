<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use App\Models\Role;

/**
 * An intern is an employee record with a fixed type: the same fields and
 * rules, but always employment type "intern", a stipend instead of a salary,
 * the Employee role, and an internship letter — with the stipend, or saying
 * there is none — in place of the offer letter.
 */
class InternRequest extends EmployeeRequest
{
    protected function prepareForValidation(): void
    {
        $creating = $this->route('employee') === null;
        $paid = $this->boolean('has_stipend');

        $this->merge([
            'employment_type' => Employee::INTERN,
            'has_stipend' => $paid,
            'stipend' => $paid ? $this->input('stipend') : null,
            'salary' => null,
            ...($creating ? [
                'role_id' => Role::where('slug', Role::EMPLOYEE)->value('id'),
                'offer_letter_mode' => in_array($this->input('offer_letter_mode'), ['internship', 'upload'], true) ? $this->input('offer_letter_mode') : 'none',
            ] : []),
        ]);
    }
}
