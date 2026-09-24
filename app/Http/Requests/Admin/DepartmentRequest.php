<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('department')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Department::class)->ignore($id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Department::class)->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
