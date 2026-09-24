<?php

namespace Tests\Feature\Work;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_project_list_is_scoped_to_what_you_belong_to()
    {
        $user = User::factory()->create();

        $mine = Project::factory()->create(['name' => 'Mine']);
        $mine->members()->attach($user);
        Project::factory()->create(['name' => 'Not mine']);
        $owned = Project::factory()->create(['name' => 'Owned', 'owner_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(function (AssertableInertia $page) use ($mine, $owned) {
                $ids = collect($page->toArray()['props']['projects'])->pluck('id');

                $this->assertEqualsCanonicalizing([$mine->id, $owned->id], $ids->all());
            });
    }

    public function test_admins_see_every_project()
    {
        Project::factory()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 3));
    }

    public function test_only_admins_and_hr_can_create_projects()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.projects.create'))
            ->assertForbidden();

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.projects.create'))
            ->assertOk();
    }

    public function test_an_admin_can_create_a_project_with_members()
    {
        $admin = User::factory()->admin()->create();
        $members = User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->post(route('admin.projects.store'), [
                'name' => 'Attendance Module',
                'code' => 'ATT',
                'status' => 'active',
                'owner_id' => $admin->id,
                'members' => $members->pluck('id')->all(),
            ])
            ->assertRedirect(route('admin.projects.index'));

        $project = Project::where('code', 'ATT')->first();

        $this->assertNotNull($project);
        $this->assertEqualsCanonicalizing($members->pluck('id')->all(), $project->members()->pluck('users.id')->all());
    }

    public function test_project_codes_are_unique()
    {
        Project::factory()->create(['code' => 'ATT']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.projects.store'), ['name' => 'Another', 'code' => 'ATT', 'status' => 'active'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_due_date_cannot_precede_the_start_date()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.projects.store'), [
                'name' => 'Backwards',
                'code' => 'BACK',
                'status' => 'active',
                'start_date' => '2026-06-01',
                'due_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors('due_date');
    }
}
