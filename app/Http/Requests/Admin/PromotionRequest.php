<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Models\Designation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PromotionRequest extends FormRequest
{
    /**
     * Same guard as editing: nobody changes the pay of someone who holds
     * access they lack.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        // Nobody sets their own pay.
        return ! $this->user()->is($employee->user) && $this->user()->canGrant($employee->user->permissions());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'to_designation_id' => ['required', 'integer', 'exists:designations,id', Designation::selectableRule($employee->designation_id)],
            'to_department_id' => ['nullable', 'integer', 'exists:departments,id', Department::selectableRule($employee->department_id)],
            'to_salary' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'effective_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'send_email' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $employee = $this->route('employee');
            $sameTitle = (int) $this->input('to_designation_id') === (int) $employee->designation_id;
            $samePay = $employee->salary !== null && (float) $this->input('to_salary') === (float) $employee->salary;

            if ($sameTitle && $samePay) {
                $validator->errors()->add('to_salary', 'Nothing changes — choose a new designation or a new salary.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'to_designation_id.required' => 'Choose the designation they move to (it can be their current one for a salary revision).',
            'to_salary.required' => 'Enter the new monthly salary.',
        ];
    }
}
