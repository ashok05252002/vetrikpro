<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Models\Designation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('designation')?->id;

        return [
            'department_id' => ['nullable', 'exists:departments,id', Department::selectableRule($this->route('designation')?->department_id)],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(Designation::class)
                    ->where('department_id', $this->input('department_id'))
                    ->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
