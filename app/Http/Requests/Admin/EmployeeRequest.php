<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Models\Designation;
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
        // Pay is set only by those who may see it. For anyone else the fields
        // are dropped, so an edit leaves the amount as it was.
        $salary = $this->user()->can('employees.salary');
        $stipend = $this->user()->can('interns.stipend');

        return [
            // Each employee is also their login, edited together.
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($employee?->user_id)],
            // The role is chosen at creation; on an edit, only by someone who
            // may change access (the same permission as the Access tab).
            'role_id' => $creating || $this->user()->can('roles.edit') ? ['nullable', 'integer', 'exists:roles,id'] : ['prohibited'],
            'send_invite' => $creating ? ['boolean'] : ['prohibited'],
            // Generate the offer letter (the default), generate the welcome letter
            // (no salary — e.g. contract staff), upload a file, or send none.
            // Interns get the internship letter instead (see InternRequest).
            // The offer letter states the salary, so only those who may set it generate one.
            'offer_letter_mode' => $creating ? ['nullable', Rule::in($salary ? ['generate', 'welcome', 'internship', 'upload', 'none'] : ['welcome', 'internship', 'upload', 'none'])] : ['prohibited'],
            'offer_letter' => $creating ? ['nullable', 'required_if:offer_letter_mode,upload', 'file', 'max:10240', 'mimes:pdf,doc,docx'] : ['prohibited'],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'employee_code')->ignore($id)],
            'department_id' => ['nullable', 'exists:departments,id', Department::selectableRule($employee?->department_id)],
            'designation_id' => ['nullable', 'exists:designations,id', Designation::selectableRule($employee?->designation_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            // A generated letter states the joining date, so it must be known;
            // only the offer letter states the salary.
            'date_of_joining' => [Rule::requiredIf($creating && in_array($this->input('offer_letter_mode'), ['generate', 'welcome', 'internship'], true)), 'nullable', 'date'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'salary' => $salary ? [Rule::requiredIf($creating && $this->input('offer_letter_mode') === 'generate'), 'nullable', 'numeric', 'min:0', 'max:99999999.99'] : ['exclude'],
            // Interns: a stipend, or none.
            'has_stipend' => $stipend ? ['boolean'] : ['exclude'],
            'stipend' => $stipend ? ['nullable', 'required_if_accepted:has_stipend', 'numeric', 'gt:0', 'max:99999999.99'] : ['exclude'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'probation', 'on_leave', 'resigned', 'terminated'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_joining.required' => 'The offer letter states a joining date — add one, or choose not to generate a letter.',
            'salary.required' => 'The offer letter states the salary — add it, or send a welcome letter without one.',
            'offer_letter.required_if' => 'Choose the offer letter file to upload.',
            'stipend.required_if_accepted' => 'Enter the monthly stipend, or choose "No stipend".',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $role = $this->filled('role_id') ? Role::find($this->integer('role_id')) : null;

            $employee = $this->route('employee');

            // Keeping someone's current role is always allowed; changing it to
            // one with access you lack is not.
            if ($role !== null && $role->id !== $employee?->user->role_id && ! $this->user()->canAssignRole($role)) {
                $validator->errors()->add('role_id', 'You cannot assign a role with access you do not have yourself.');
            }

            if ($role !== null && $employee?->user->is($this->user()) && $this->user()->isSuper() && ! $role->is_super) {
                $validator->errors()->add('role_id', 'You cannot remove your own administrator role.');
            }
        }];
    }
}
