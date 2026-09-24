<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assigned_to' => null,
            'created_by' => null,
            'title' => rtrim(fake()->sentence(4), '.'),
            'description' => fake()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'due_date' => null,
            'position' => 0,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => TaskStatus::Todo,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::Done]);
    }
}
