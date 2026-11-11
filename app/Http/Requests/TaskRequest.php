<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\RequirementPoint;
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
            // Optional; any number. Sent by the task form; absent means "leave the links as they are".
            'requirement_point_ids' => ['sometimes', 'array', 'max:50'],
            'requirement_point_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** Only points from the task's own project can be linked. */
    public function after(): array
    {
        return [function ($validator) {
            $ids = array_map('intval', (array) $this->input('requirement_point_ids', []));

            if ($ids === [] || ! $this->filled('project_id')) {
                return;
            }

            $valid = RequirementPoint::query()
                ->whereIn('requirement_points.id', $ids)
                ->whereHas('version', fn ($q) => $q->where('project_id', (int) $this->input('project_id')))
                ->count();

            if ($valid !== count(array_unique($ids))) {
                $validator->errors()->add('requirement_point_ids', 'Pick requirement points from this project only.');
            }
        }];
    }
}
