<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ErrorAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_address_shows_the_404_page_even_signed_out()
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 404));
    }

    public function test_a_forbidden_page_shows_the_403_page()
    {
        $this->actingAs(User::factory()->create())->get(route('accounts.invoices.index'))
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 403));
    }

    public function test_json_requests_keep_json_errors()
    {
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_an_employee_dashboard_counts_only_their_own_tasks()
    {
        $me = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($me);
        Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $me->id, 'status' => TaskStatus::Todo]);
        Task::factory()->count(3)->create(['project_id' => $project->id, 'assigned_to' => User::factory()->create()->id, 'status' => TaskStatus::Todo]);

        $this->actingAs($me)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.openTasks', 1)
                ->where('stats.mine.open', 1)
                ->where('projects.0.my_open_tasks_count', 1));
    }

    public function test_an_admin_dashboard_counts_the_organisation_and_their_own()
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->count(2)->create(['status' => TaskStatus::Todo]);
        Task::factory()->create(['assigned_to' => $admin->id, 'status' => TaskStatus::Todo, 'due_date' => Clock::today()->addDays(2)]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.openTasks', 3)
                ->where('stats.mine.open', 1)
                ->where('stats.dueThisWeek', Task::dueThisWeek()->count()));

        // The "Due this week" tile's list: the same filter on the task list.
        $this->actingAs($admin)->get(route('tasks.index', ['scope' => 'all', 'due' => 'week']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tasks.total', Task::dueThisWeek()->count()));
    }

    public function test_the_project_testing_tab_shows_its_bugs_in_the_project()
    {
        $admin = User::factory()->admin()->create();
        $point = TestPoint::factory()->create();

        $this->actingAs($admin)->get(route('projects.testing', $point->project_id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('projects/testing')
                ->has('columns', 5)
                ->where('project.id', $point->project_id));
    }
}
