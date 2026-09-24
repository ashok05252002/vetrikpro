<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'code' => strtoupper(fake()->unique()->bothify('???-##')),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Active,
            'start_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->addMonths(2)->toDateString(),
        ];
    }
}
