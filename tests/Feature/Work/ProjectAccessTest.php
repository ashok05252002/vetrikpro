<?php

namespace Tests\Feature\Work;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Can create projects but only sees their own. */
    private function creatorWithoutViewAll(array $extra = []): User
    {
        $role = Role::create(['name' => 'Custom '.uniqid(), 'slug' => 'custom-'.uniqid()]);
        $role->syncPermissions(['projects.create', ...$extra]);

        return User::factory()->create(['role_id' => $role->id]);
    }

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

    public function test_creating_a_project_leads_to_its_members_tab()
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.projects.store'), [
                'name' => 'Attendance Module',
                'code' => 'ATT',
                'status' => 'active',
                'owner_id' => $admin->id,
                'default_branch' => 'main',
            ]);

        $project = Project::where('code', 'ATT')->first();

        $this->assertNotNull($project);
        $response->assertRedirect(route('projects.members.index', $project));
        $this->assertSame('main', $project->default_branch);
    }

    public function test_a_creator_who_cannot_see_every_project_joins_it()
    {
        $creator = $this->creatorWithoutViewAll();

        $this->actingAs($creator)->post(route('admin.projects.store'), [
            'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active', 'default_branch' => 'main',
        ])->assertRedirect();

        $project = Project::where('code', 'PAY')->firstOrFail();

        $this->assertTrue($project->hasMember($creator));
        $this->assertSame('member', $project->members()->whereKey($creator->id)->first()->pivot->role);
        $this->actingAs($creator)->get(route('projects.members.index', $project))->assertOk();
    }

    public function test_creating_projects_does_not_mean_seeing_every_project()
    {
        $creator = $this->creatorWithoutViewAll();
        $theirs = Project::factory()->create();
        $theirs->members()->attach($creator);
        Project::factory()->create();

        $this->assertFalse($creator->can('projects.view'));
        $this->actingAs($creator)->get(route('admin.projects.index'))->assertForbidden();
        $this->actingAs($creator)->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 1)->where('projects.0.id', $theirs->id));
    }

    public function test_a_creator_who_picks_themselves_as_lead_stays_lead()
    {
        $creator = $this->creatorWithoutViewAll(['project_roles.lead']);

        $this->actingAs($creator)->post(route('admin.projects.store'), [
            'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active', 'default_branch' => 'main',
            'lead_ids' => [$creator->id],
        ])->assertRedirect();

        $project = Project::where('code', 'PAY')->firstOrFail();

        $this->assertTrue($project->isLedBy($creator));
        $this->assertSame(1, $project->members()->count());
    }

    public function test_an_admin_who_creates_a_project_is_not_added_to_it()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.projects.store'), [
            'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active', 'default_branch' => 'main',
        ])->assertRedirect();

        $this->assertFalse(Project::where('code', 'PAY')->firstOrFail()->hasMember($admin));
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
