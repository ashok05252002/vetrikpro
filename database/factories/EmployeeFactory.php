<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => Department::factory(),
            'designation_id' => null,
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'phone' => fake()->phoneNumber(),
            'date_of_joining' => fake()->date(),
            'employment_type' => 'full_time',
            'status' => 'active',
        ];
    }
}
