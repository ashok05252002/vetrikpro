<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('employee')?->id;
        // Creating offers two ways in: a brand-new person (an account is made
        // and invited) or an account that already exists. Editing is always
        // the latter.
        $new = $id === null && $this->input('mode') === 'new';

        return [
            'mode' => [$id === null ? 'required' : 'prohibited', Rule::in(['new', 'existing'])],
            'name' => [Rule::requiredIf($new), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf($new), 'nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'send_invite' => ['boolean'],
            'offer_letter' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
            'user_id' => [Rule::requiredIf(! $new), 'nullable', 'exists:users,id', Rule::unique(Employee::class, 'user_id')->ignore($id)],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'employee_code')->ignore($id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_joining' => ['nullable', 'date'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'probation', 'on_leave', 'resigned', 'terminated'])],
        ];
    }
}
