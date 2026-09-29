<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('project')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique(Project::class)->ignore($id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'owner_id' => ['nullable', 'exists:users,id'],
            // One or more leads, each eligible (Roles & access → Project roles).
            'lead_ids' => ['nullable', 'array', 'max:20'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:users,id', ProjectRoles::eligibleRule(ProjectMemberRole::Lead)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'repository_url' => ['nullable', 'url', 'max:255'],
            'default_branch' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date must fall on or after the start date.',
        ];
    }
}
