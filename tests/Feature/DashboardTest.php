<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    /** Stage value => count, from the dashboard's pipeline prop. */
    private function pipelineFor(User $user): array
    {
        $props = $this->actingAs($user)->get(route('dashboard'))->viewData('page')['props'];

        return collect($props['taskPipeline'])->pluck('count', 'value')->all();
    }

    private function seedTasks(User $assignee): void
    {
        Task::factory()->count(2)->create(['assigned_to' => $assignee->id, 'status' => TaskStatus::Todo]);
        Task::factory()->create(['assigned_to' => $assignee->id, 'status' => TaskStatus::Done]);
        Task::factory()->count(3)->create(['status' => TaskStatus::Todo]);
    }

    public function test_seeing_every_project_still_shows_only_your_own_pipeline()
    {
        $role = Role::create(['name' => 'Custom '.uniqid(), 'slug' => 'custom-'.uniqid()]);
        $role->syncPermissions(['projects.view']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->seedTasks($user);

        $pipeline = $this->pipelineFor($user);

        $this->assertSame(2, $pipeline[TaskStatus::Todo->value]);
        $this->assertSame(1, $pipeline[TaskStatus::Done->value]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pipelineOrgWide', false));
    }

    public function test_hr_sees_only_their_own_pipeline()
    {
        $hr = User::factory()->hr()->create();
        $this->seedTasks($hr);

        $this->assertSame(2, $this->pipelineFor($hr)[TaskStatus::Todo->value]);
    }

    public function test_the_administrator_sees_everyones_pipeline()
    {
        $admin = User::factory()->admin()->create();
        $this->seedTasks(User::factory()->create());

        $this->assertSame(5, $this->pipelineFor($admin)[TaskStatus::Todo->value]);
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pipelineOrgWide', true));
    }

    public function test_an_employee_sees_their_own_pipeline_as_before()
    {
        $user = User::factory()->create();
        $this->seedTasks($user);

        $this->assertSame(2, $this->pipelineFor($user)[TaskStatus::Todo->value]);
    }
}
