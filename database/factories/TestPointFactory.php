<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TestPointStatus;
use App\Models\Project;
use App\Models\TestPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestPoint>
 */
class TestPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => rtrim(fake()->sentence(5), '.'),
            'steps' => "1. Open the page\n2. Fill the form\n3. Submit",
            'expected_result' => 'The record is saved and a confirmation appears.',
            'status' => TestPointStatus::ToTest,
            'priority' => TaskPriority::Medium,
            'position' => 0,
        ];
    }
}
