<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TestPointStatus;
use App\Support\ProjectPeople;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestPointRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:255'],
            'steps' => ['nullable', 'string', 'max:10000'],
            'expected_result' => ['nullable', 'string', 'max:5000'],
            'actual_result' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(TestPointStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id', ProjectPeople::notArchivedRule($this->route('testPoint')?->assigned_to)],
            // A point can only verify a task on its own project.
            'task_id' => ['nullable', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
        ];
    }

    public function messages(): array
    {
        return ['task_id.exists' => 'That task is not on this project.'];
    }
}
