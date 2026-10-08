<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\ProjectPeople;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id', ProjectPeople::notArchivedRule($this->route('task')?->assigned_to)],
            // Y-m-d only: anything with a time or zone would be refused by MySQL as a 500.
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
