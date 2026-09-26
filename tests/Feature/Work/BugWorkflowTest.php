<?php

namespace Tests\Feature\Work;

use App\Enums\TestPointStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\TestPoint;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BugWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $tester;

    private User $developer;

    private User $leader;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Team Leader', 'slug' => 'team-leader']);
        $role->syncPermissions(['testing.assign']);

        $this->tester = User::factory()->create();
        $this->developer = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->leader = User::factory()->create(['role_id' => $role->id]);
        $this->project = Project::factory()->create();
        $this->project->members()->attach([$this->tester->id, $this->developer->id, $this->colleague->id, $this->leader->id]);
    }

    private function report(User $as, array $overrides = [])
    {
        return $this->actingAs($as)->post(route('testing.points.store', $this->project), [
            'title' => 'Half-day leave shows as a full day',
            'steps' => '1. Apply for half-day leave 2. Open the calendar',
            'expected_result' => 'Half a day is shown.',
            'actual_result' => 'A full day is shown.',
            'status' => 'open',
            'priority' => 'high',
            ...$overrides,
        ]);
    }

    private function bug(array $overrides = []): TestPoint
    {
        return TestPoint::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->tester->id,
            'assigned_to' => $this->developer->id,
            ...$overrides,
        ]);
    }

    private function move(User $as, TestPoint $bug, string $to)
    {
        return $this->actingAs($as)->patch(route('testing.points.move', [$this->project, $bug]), ['status' => $to, 'position' => 0]);
    }

    public function test_the_team_leader_permission_is_in_the_registry()
    {
        $this->assertTrue(Permissions::exists('testing.assign'));
        $this->assertTrue($this->leader->can('testing.assign'));
        $this->assertFalse($this->colleague->can('testing.assign'));
    }

    // Reporting

    public function test_any_member_reports_a_bug_and_is_recorded_as_its_reporter()
    {
        $this->report($this->colleague)->assertSessionHasNoErrors();

        $bug = TestPoint::firstOrFail();
        $this->assertSame($this->colleague->id, $bug->created_by);
        $this->assertSame(TestPointStatus::Open, $bug->status);
        $this->assertNull($bug->assigned_to);
        $this->assertSame('A full day is shown.', $bug->actual_result);

        $this->actingAs($this->tester)
            ->get(route('testing.points.index', [$this->project, 'view' => 'list']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('list.data.0.reporter.name', $this->colleague->name)
                ->where('can.assign', false));
    }

    // Assigning

    public function test_only_the_team_leader_owner_or_admin_assigns()
    {
        $this->report($this->tester, ['assigned_to' => $this->developer->id])->assertSessionHasErrors('assigned_to');
        $this->assertSame(0, TestPoint::count());

        $bug = $this->bug(['assigned_to' => null]);
        $update = fn (User $as) => $this->actingAs($as)->put(route('testing.points.update', [$this->project, $bug]), [
            'title' => $bug->title,
            'status' => $bug->status->value,
            'priority' => 'medium',
            'assigned_to' => $this->developer->id,
        ]);

        $update($this->tester)->assertSessionHasErrors('assigned_to');
        $this->assertNull($bug->fresh()->assigned_to);

        $update($this->leader)->assertSessionHasNoErrors();
        $this->assertSame($this->developer->id, $bug->fresh()->assigned_to);

        $owner = User::factory()->create();
        $this->project->update(['owner_id' => $owner->id]);
        $this->report($owner, ['assigned_to' => $this->developer->id])->assertSessionHasNoErrors();
        $this->report(User::factory()->admin()->create(), ['assigned_to' => $this->developer->id])->assertSessionHasNoErrors();
    }

    public function test_editing_the_wording_keeps_the_assignee_without_the_permission()
    {
        $bug = $this->bug();

        $this->actingAs($this->tester)->put(route('testing.points.update', [$this->project, $bug]), [
            'title' => 'Half-day leave counts as a full day',
            'status' => $bug->status->value,
            'priority' => 'medium',
            'assigned_to' => $this->developer->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Half-day leave counts as a full day', $bug->fresh()->title);
    }

    public function test_the_team_leader_sees_the_assign_control()
    {
        $bug = $this->bug();

        $this->actingAs($this->leader)
            ->get(route('testing.points.show', [$this->project, $bug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.assign', true)
                ->where('can.changeStatus', true)
                ->where('point.reporter.name', $this->tester->name)
                ->where('point.assignee.name', $this->developer->name));

        $this->actingAs($this->colleague)
            ->get(route('testing.points.show', [$this->project, $bug]))
            ->assertInertia(fn (Assert $page) => $page->where('can.assign', false)->where('can.changeStatus', false));
    }

    // Moving through the flow

    public function test_the_full_loop_reporter_developer_and_back()
    {
        $bug = $this->bug();

        $this->move($this->developer, $bug, 'in_progress')->assertSessionHasNoErrors();
        $this->move($this->developer, $bug, 'ready_for_test');
        $this->move($this->tester, $bug, 'repeated');
        $this->assertSame($this->tester->id, $bug->fresh()->last_tested_by);
        $this->move($this->developer, $bug, 'in_progress');
        $this->move($this->developer, $bug, 'ready_for_test');
        $this->move($this->tester, $bug, 'closed');

        $this->assertSame(
            ['open', 'in_progress', 'ready_for_test', 'repeated', 'in_progress', 'ready_for_test', 'closed'],
            $bug->statusChanges()->pluck('to_status')->all(),
        );
    }

    public function test_the_team_leader_moves_any_bug_and_other_members_cannot()
    {
        $bug = $this->bug();

        $this->move($this->colleague, $bug, 'in_progress')->assertForbidden();
        $this->assertSame(TestPointStatus::Open, $bug->fresh()->status);

        $this->move($this->leader, $bug, 'in_progress')->assertSessionHasNoErrors();
        $this->assertSame(TestPointStatus::InProgress, $bug->fresh()->status);
    }

    public function test_a_failed_retest_in_a_run_marks_the_bug_repeated()
    {
        $bug = $this->bug(['status' => TestPointStatus::ReadyForTest]);

        $this->actingAs($this->leader)->post(route('testing.runs.store', $this->project), ['name' => 'Retest', 'points' => [$bug->id]]);
        $row = $this->project->testRuns()->firstOrFail()->results()->firstOrFail();

        $this->actingAs($this->tester)
            ->patch(route('testing.runs.results.update', [$this->project, $row->test_run_id, $row]), ['result' => 'failed', 'notes' => 'Still a full day'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TestPointStatus::Repeated, $bug->fresh()->status);
    }
}
