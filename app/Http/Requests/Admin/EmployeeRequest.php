<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    /**
     * Nobody edits a person who holds access they lack — otherwise HR could
     * change the administrator's email and take over the account.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee === null || $this->user()->canGrant($employee->user->permissions());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $id = $employee?->id;
        $creating = $employee === null;

        return [
            // Each employee is also their login, edited together.
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($employee?->user_id)],
            // Role and invite are chosen once, at creation; later the role is
            // changed on the Access tab under its own permission.
            'role_id' => $creating ? ['nullable', 'integer', 'exists:roles,id'] : ['prohibited'],
            'send_invite' => $creating ? ['boolean'] : ['prohibited'],
            'offer_letter' => $creating ? ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx'] : ['prohibited'],
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

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $role = $this->filled('role_id') ? Role::find($this->integer('role_id')) : null;

            if ($role !== null && ! $this->user()->canAssignRole($role)) {
                $validator->errors()->add('role_id', 'You cannot assign a role with access you do not have yourself.');
            }
        }];
    }
}
