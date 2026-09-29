<?php

namespace Tests\Feature\Work;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectMembersTest extends TestCase
{
    use RefreshDatabase;

    private function project(?User $owner = null): Project
    {
        return Project::factory()->create(['owner_id' => $owner?->id]);
    }

    public function test_members_see_the_members_tab_and_outsiders_do_not()
    {
        $project = $this->project();
        $member = User::factory()->create();
        $project->members()->attach($member);

        $this->actingAs($member)
            ->get(route('projects.members.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/members')
                ->where('project.viewer.can_manage_members', false)
                ->has('members.data', 1));

        $this->actingAs(User::factory()->create())
            ->get(route('projects.members.index', $project))
            ->assertForbidden();
    }

    public function test_the_member_list_is_paginated()
    {
        $project = $this->project();
        $project->members()->attach(User::factory()->count(20)->create());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.members.index', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 15)
                ->where('members.total', 20));
    }

    public function test_the_member_list_filters_by_search_department_and_role()
    {
        $project = $this->project();
        $engineering = Department::factory()->create(['name' => 'Engineering']);
        $arun = Employee::factory()->create(['department_id' => $engineering->id])->user;
        $arun->update(['name' => 'Arun Kumar']);
        $meera = User::factory()->create(['name' => 'Meera Nair']);
        $project->members()->attach($arun, ['role' => 'dev_admin']);
        $project->members()->attach($meera);

        $admin = User::factory()->admin()->create();
        $names = fn (array $query) => fn (Assert $page) => $page->where('members.data', fn ($rows) => collect($rows)->pluck('name')->all() === $query);

        $this->actingAs($admin)->get(route('projects.members.index', [$project, 'search' => 'meera']))->assertInertia($names(['Meera Nair']));
        $this->actingAs($admin)->get(route('projects.members.index', [$project, 'department' => $engineering->id]))->assertInertia($names(['Arun Kumar']));
        $this->actingAs($admin)->get(route('projects.members.index', [$project, 'role' => 'dev_admin']))->assertInertia($names(['Arun Kumar']));
        // Dev admins list first.
        $this->actingAs($admin)->get(route('projects.members.index', $project))->assertInertia($names(['Arun Kumar', 'Meera Nair']));
    }

    public function test_the_owner_can_add_members_and_a_plain_member_cannot()
    {
        $owner = User::factory()->create();
        $project = $this->project($owner);
        $member = User::factory()->create();
        $project->members()->attach($member);
        $newcomers = User::factory()->count(2)->create();

        $this->actingAs($member)
            ->post(route('projects.members.store', $project), ['user_ids' => $newcomers->pluck('id')->all(), 'role' => 'member'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('projects.members.store', $project), ['user_ids' => $newcomers->pluck('id')->all(), 'role' => 'member'])
            ->assertSessionHas('success', '2 members added.');

        $this->assertSame(3, $project->members()->count());
    }

    public function test_separate_additions_never_overwrite_each_other()
    {
        $project = $this->project();
        [$a, $b] = User::factory()->count(2)->create();
        // Merge access needs eligibility (Roles & access → Project roles).
        $a->syncPermissionOverrides(['project_roles.merge' => true]);
        $admin = User::factory()->admin()->create();

        // Two people adding from two open dialogs: the second must not drop the first.
        $this->actingAs($admin)->post(route('projects.members.store', $project), ['user_ids' => [$a->id], 'role' => 'dev_admin']);
        $this->actingAs($admin)->post(route('projects.members.store', $project), ['user_ids' => [$a->id, $b->id], 'role' => 'member']);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $project->members()->pluck('users.id')->all());
        // Re-adding someone keeps the role they already had.
        $this->assertTrue($project->isDevAdmin($a));
    }

    public function test_a_member_can_be_made_dev_admin()
    {
        $project = $this->project();
        $member = User::factory()->create();
        $member->syncPermissionOverrides(['project_roles.merge' => true]);
        $project->members()->attach($member);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('projects.members.update', [$project, $member]), ['role' => 'dev_admin'])
            ->assertSessionHas('success');

        $this->assertTrue($project->isDevAdmin($member));

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertInertia(fn (Assert $page) => $page->where('project.viewer.is_dev_admin', true));
    }

    public function test_re_roling_someone_who_is_not_a_member_is_not_found()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('projects.members.update', [$this->project(), User::factory()->create()]), ['role' => 'dev_admin'])
            ->assertNotFound();
    }

    public function test_an_unknown_project_role_is_rejected()
    {
        $project = $this->project();
        $member = User::factory()->create();
        $project->members()->attach($member);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('projects.members.update', [$project, $member]), ['role' => 'overlord'])
            ->assertSessionHasErrors('role');
    }

    public function test_removing_a_member_keeps_their_task_assignments()
    {
        $project = $this->project();
        $member = User::factory()->create();
        $project->members()->attach($member);
        $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $member->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('projects.members.destroy', [$project, $member]))
            ->assertSessionHas('success');

        $this->assertFalse($project->hasMember($member));
        $this->assertSame($member->id, $task->fresh()->assigned_to);
    }

    public function test_candidates_exclude_current_members_and_disabled_accounts()
    {
        $project = $this->project();
        $already = User::factory()->create(['name' => 'Already Here']);
        $project->members()->attach($already);
        User::factory()->create(['name' => 'Gone Away', 'is_active' => false]);
        $free = User::factory()->create(['name' => 'Free Agent']);
        $admin = User::factory()->admin()->create();

        $ids = collect($this->actingAs($admin)->getJson(route('projects.members.candidates', $project))->json('data'))->pluck('id');

        $this->assertContains($free->id, $ids);
        $this->assertNotContains($already->id, $ids);
        $this->assertCount(0, collect($this->actingAs($admin)->getJson(route('projects.members.candidates', [$project, 'search' => 'Gone']))->json('data')));
    }

    public function test_candidates_are_only_for_those_who_manage_members()
    {
        $project = $this->project();
        $member = User::factory()->create();
        $project->members()->attach($member);

        $this->actingAs($member)->getJson(route('projects.members.candidates', $project))->assertForbidden();
    }

    public function test_the_owner_lookup_needs_project_management()
    {
        User::factory()->create(['name' => 'Findable Person']);

        $this->actingAs(User::factory()->create())->getJson(route('admin.lookups.users'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.lookups.users', ['search' => 'Findable']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Findable Person');
    }

    public function test_merge_access_and_lead_need_eligibility()
    {
        $project = $this->project();
        $member = User::factory()->create();
        $project->members()->attach($member, ['role' => 'member']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('projects.members.update', [$project, $member]), ['role' => 'dev_admin'])->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('projects.members.update', [$project, $member]), ['role' => 'lead'])->assertSessionHas('error');
        $this->assertFalse($project->fresh()->isDevAdmin($member));

        $member->syncPermissionOverrides(['project_roles.lead' => true]);
        $this->actingAs($admin)->patch(route('projects.members.update', [$project, $member->fresh()]), ['role' => 'lead'])->assertSessionHas('success');
        $this->assertTrue($project->fresh()->isLedBy($member));
    }

    public function test_a_lead_runs_the_project_like_its_owner()
    {
        $project = $this->project();
        $lead = User::factory()->create();
        $lead->syncPermissionOverrides(['project_roles.lead' => true]);
        $project->members()->attach($lead, ['role' => 'lead']);
        $newcomer = User::factory()->create();

        $this->assertTrue($lead->can('update', $project));
        $this->assertTrue($lead->can('manageMembers', $project));
        $this->assertTrue($project->canMerge($lead));

        $this->actingAs($lead)->post(route('projects.members.store', $project), ['user_ids' => [$newcomer->id], 'role' => 'member'])->assertSessionHas('success');
    }

    public function test_leads_are_set_from_the_project_form_and_only_eligible_people_count()
    {
        $admin = User::factory()->admin()->create();
        $eligible = User::factory()->create();
        $eligible->syncPermissionOverrides(['project_roles.lead' => true]);
        $plain = User::factory()->create();
        $base = ['name' => 'Chef2Comply', 'code' => 'C2C', 'status' => 'active', 'default_branch' => 'main'];

        $this->actingAs($admin)->post(route('admin.projects.store'), [...$base, 'lead_ids' => [$plain->id]])->assertSessionHasErrors('lead_ids.0');

        $this->actingAs($admin)->post(route('admin.projects.store'), [...$base, 'lead_ids' => [$eligible->id]])->assertSessionHasNoErrors();
        $project = Project::where('code', 'C2C')->first();
        $this->assertTrue($project->isLedBy($eligible));

        // Dropping a lead keeps them on the project as a member.
        $this->actingAs($admin)->put(route('admin.projects.update', $project), [...$base, 'lead_ids' => []])->assertSessionHasNoErrors();
        $this->assertFalse($project->fresh()->isLedBy($eligible));
        $this->assertTrue($project->fresh()->hasMember($eligible));
    }
}
