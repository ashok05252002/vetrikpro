<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
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

        return [
            'user_id' => ['required', 'exists:users,id', Rule::unique(Employee::class, 'user_id')->ignore($id)],
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
