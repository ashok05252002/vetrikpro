<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmployeeProjectTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_project_shows_only_their_tasks_in_it()
    {
        $employee = Employee::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($employee->user_id);
        $theirs = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->user_id]);
        Task::factory()->create(['project_id' => $project->id, 'assigned_to' => User::factory()->create()->id]);
        Task::factory()->create(['assigned_to' => $employee->user_id]); // another project

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.employees.projects', ['employee' => $employee, 'project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.id', $project->id)
                ->has('selected.tasks', 1)
                ->where('selected.tasks.0.id', $theirs->id));
    }

    public function test_a_project_they_are_not_on_opens_nothing()
    {
        $employee = Employee::factory()->create();
        $elsewhere = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.employees.projects', ['employee' => $employee, 'project' => $elsewhere->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('selected', null));
    }
}
