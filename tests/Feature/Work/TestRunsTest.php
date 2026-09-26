<?php

namespace Tests\Feature\Work;

use App\Enums\TestPointStatus;
use App\Enums\TestResult;
use App\Enums\TestRunStatus;
use App\Models\Project;
use App\Models\TestPoint;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TestRunsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    private User $tester;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->tester = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->members()->attach([$this->tester->id, $this->colleague->id]);
    }

    private function point(array $overrides = []): TestPoint
    {
        return TestPoint::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->tester->id, ...$overrides]);
    }

    private function startRun(User $as, array $points, string $name = 'Release 1.2 regression'): TestRun
    {
        $this->actingAs($as)
            ->post(route('testing.runs.store', $this->project), ['name' => $name, 'points' => collect($points)->pluck('id')->all()])
            ->assertRedirect();

        return TestRun::query()->latest('id')->firstOrFail();
    }

    private function record(User $as, TestRun $run, TestPoint $point, string $result, ?string $notes = null)
    {
        $row = $run->results()->where('test_point_id', $point->id)->firstOrFail();

        return $this->actingAs($as)->patch(route('testing.runs.results.update', [$this->project, $run, $row]), ['result' => $result, 'notes' => $notes]);
    }

    // The module's front page

    public function test_the_testing_page_lists_only_projects_the_person_can_see()
    {
        $this->point(['status' => TestPointStatus::Repeated]);
        Project::factory()->create(['name' => 'Someone else’s']);

        $this->actingAs($this->colleague)
            ->get(route('testing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('testing/index')
                ->has('projects', 1)
                ->where('projects.0.id', $this->project->id)
                ->where('projects.0.points_count', 1)
                ->where('projects.0.repeated_count', 1)
                ->where('projects.0.open_count', 1)
                ->where('projects.0.latest_run', null));
    }

    public function test_the_front_page_shows_the_latest_run_with_its_progress()
    {
        $a = $this->point();
        $b = $this->point();
        $this->startRun($this->owner, [$a], 'First');
        $run = $this->startRun($this->owner, [$a, $b], 'Second');
        $this->record($this->tester, $run, $a, 'passed');

        $this->actingAs($this->owner)
            ->get(route('testing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.latest_run.name', 'Second')
                ->where('projects.0.latest_run.tally.passed', 1)
                ->where('projects.0.latest_run.tally.not_run', 1)
                ->where('projects.0.open_runs_count', 2));
    }

    // Starting a run

    public function test_a_member_starts_a_numbered_run_over_chosen_points()
    {
        [$a, $b, $c] = [$this->point(), $this->point(), $this->point()];

        $run = $this->startRun($this->colleague, [$c, $a]);

        $this->assertSame('RUN-1', $run->reference());
        $this->assertSame(TestRunStatus::Open, $run->status);
        $this->assertSame($this->colleague->id, $run->created_by);
        // In point order, every one not run yet, with its title copied in.
        $this->assertSame([$a->id, $c->id], $run->results->pluck('test_point_id')->all());
        $this->assertSame([TestResult::NotRun, TestResult::NotRun], $run->results->pluck('result')->all());
        $this->assertSame($a->title, $run->results->first()->point_title);
        $this->assertNotContains($b->id, $run->results->pluck('test_point_id'));
    }

    public function test_a_run_cannot_take_another_projects_points()
    {
        $foreign = TestPoint::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('testing.runs.store', $this->project), ['name' => 'Sneaky', 'points' => [$foreign->id]])
            ->assertSessionHasErrors('points.0');

        $this->assertSame(0, TestRun::count());
    }

    public function test_a_run_needs_at_least_one_point()
    {
        $this->actingAs($this->owner)
            ->post(route('testing.runs.store', $this->project), ['name' => 'Empty', 'points' => []])
            ->assertSessionHasErrors('points');
    }

    public function test_outsiders_cannot_see_or_start_runs()
    {
        $outsider = User::factory()->create();
        $run = $this->startRun($this->owner, [$this->point()]);

        $this->actingAs($outsider)->get(route('testing.runs.index', $this->project))->assertForbidden();
        $this->actingAs($outsider)->get(route('testing.runs.show', [$this->project, $run]))->assertForbidden();
        $this->actingAs($outsider)->post(route('testing.runs.store', $this->project), ['name' => 'x', 'points' => [1]])->assertForbidden();
    }

    public function test_a_run_is_only_reachable_through_its_own_project()
    {
        $run = $this->startRun($this->owner, [$this->point()]);
        $other = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->actingAs($this->owner)->get(route('testing.runs.show', [$other, $run]))->assertNotFound();
    }

    // Recording results

    public function test_passing_in_a_run_records_the_result_and_moves_the_point()
    {
        $point = $this->point();
        $run = $this->startRun($this->owner, [$point]);

        $this->record($this->tester, $run, $point, 'passed', 'Works on Chrome and Safari')->assertRedirect();

        $row = $run->results()->first();
        $this->assertSame(TestResult::Passed, $row->result);
        $this->assertSame('Works on Chrome and Safari', $row->notes);
        $this->assertSame($this->tester->id, $row->tested_by);
        $this->assertNotNull($row->tested_at);

        $point->refresh();
        $this->assertSame(TestPointStatus::Closed, $point->status);
        $this->assertSame($this->tester->id, $point->last_tested_by);
        // Through the model, so the status log saw it too.
        $this->assertSame('closed', $point->statusChanges()->get()->last()->to_status);
    }

    public function test_blocked_leaves_the_board_alone()
    {
        $point = $this->point(['status' => TestPointStatus::ReadyForTest]);
        $run = $this->startRun($this->owner, [$point]);

        $this->record($this->tester, $run, $point, 'blocked', 'Staging is down');

        $this->assertSame(TestResult::Blocked, $run->results()->first()->result);
        $this->assertSame(TestPointStatus::ReadyForTest, $point->fresh()->status);
    }

    public function test_clearing_a_result_forgets_who_ran_it()
    {
        $point = $this->point();
        $run = $this->startRun($this->owner, [$point]);
        $this->record($this->tester, $run, $point, 'failed');

        $this->record($this->tester, $run, $point, 'not_run');

        $row = $run->results()->first();
        $this->assertSame(TestResult::NotRun, $row->result);
        $this->assertNull($row->tested_by);
        $this->assertNull($row->tested_at);
    }

    public function test_only_those_who_may_move_the_point_record_its_result()
    {
        $point = $this->point(['created_by' => $this->owner->id]);
        $run = $this->startRun($this->colleague, [$point]);

        // Starting the run does not make the colleague the point's tester.
        $this->record($this->colleague, $run, $point, 'passed')->assertForbidden();
        $this->assertSame(TestResult::NotRun, $run->results()->first()->result);

        foreach ([$this->tester, $this->owner, User::factory()->admin()->create()] as $who) {
            $this->record($who, $run, $point, 'failed')->assertRedirect();
        }
    }

    public function test_each_run_keeps_its_own_result()
    {
        $point = $this->point();
        $first = $this->startRun($this->owner, [$point], 'Round 1');
        $this->record($this->tester, $first, $point, 'failed', 'Overlap accepted');

        $second = $this->startRun($this->owner, [$point], 'Round 2');
        $this->record($this->tester, $second, $point, 'passed');

        $this->assertSame(TestResult::Failed, $first->results()->first()->result);
        $this->assertSame(TestResult::Passed, $second->results()->first()->result);

        $this->actingAs($this->colleague)
            ->get(route('testing.points.show', [$this->project, $point]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('testing/point')
                ->has('point.runs', 2)
                ->where('point.runs.0.run.name', 'Round 2')
                ->where('point.runs.1.result', 'failed')
                ->where('point.runs.1.notes', 'Overlap accepted'));
    }

    public function test_a_result_cannot_be_reached_through_another_run()
    {
        $point = $this->point();
        $mine = $this->startRun($this->owner, [$point]);
        $other = $this->startRun($this->owner, [$point]);
        $row = $mine->results()->first();

        $this->actingAs($this->tester)
            ->patch(route('testing.runs.results.update', [$this->project, $other, $row]), ['result' => 'passed'])
            ->assertNotFound();
    }

    // Completing

    public function test_a_completed_run_freezes_its_results_until_reopened()
    {
        $point = $this->point();
        $run = $this->startRun($this->colleague, [$point]);

        // The person who started it closes it.
        $this->actingAs($this->colleague)->post(route('testing.runs.complete', [$this->project, $run]))->assertRedirect();
        $run->refresh();
        $this->assertSame(TestRunStatus::Completed, $run->status);
        $this->assertSame($this->colleague->id, $run->completed_by);

        $this->record($this->tester, $run, $point, 'passed')->assertSessionHas('error');
        $this->assertSame(TestResult::NotRun, $run->results()->first()->result);
        $this->assertSame(TestPointStatus::Open, $point->fresh()->status);

        $this->actingAs($this->owner)->post(route('testing.runs.reopen', [$this->project, $run]));
        $this->assertNull($run->fresh()->completed_at);
        $this->record($this->tester, $run, $point, 'passed')->assertSessionHasNoErrors();
        $this->assertSame(TestResult::Passed, $run->results()->first()->result);
    }

    public function test_another_member_cannot_complete_someone_elses_run()
    {
        $run = $this->startRun($this->owner, [$this->point()]);

        $this->actingAs($this->tester)->post(route('testing.runs.complete', [$this->project, $run]))->assertForbidden();
        $this->assertTrue($run->fresh()->isOpen());
    }

    // Deleting

    public function test_deleting_a_point_keeps_the_run_record()
    {
        $point = $this->point(['title' => 'Overlapping leave is refused']);
        $run = $this->startRun($this->owner, [$point]);
        $this->record($this->tester, $run, $point, 'failed');

        $point->delete();

        $row = $run->results()->first();
        $this->assertNull($row->test_point_id);
        $this->assertSame('Overlapping leave is refused', $row->point_title);
        $this->assertSame(TestResult::Failed, $row->result);

        $this->actingAs($this->owner)
            ->get(route('testing.runs.show', [$this->project, $run]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('testing/run')
                ->where('results.0.point', null)
                ->where('results.0.can_record', false)
                ->where('run.tally.failed', 1));
    }

    public function test_deleting_a_run_leaves_the_points_alone()
    {
        $point = $this->point();
        $run = $this->startRun($this->owner, [$point]);
        $this->record($this->tester, $run, $point, 'passed');

        $this->actingAs($this->tester)->delete(route('testing.runs.destroy', [$this->project, $run]))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('testing.runs.destroy', [$this->project, $run]))->assertRedirect(route('testing.runs.index', $this->project));

        $this->assertSame(0, TestRun::count());
        $this->assertSame(TestPointStatus::Closed, $point->fresh()->status);
    }

    // Pages

    public function test_the_runs_tab_lists_runs_newest_first_with_counts()
    {
        $point = $this->point();
        $this->startRun($this->owner, [$point], 'Older');
        $this->startRun($this->owner, [$point], 'Newer');

        $this->actingAs($this->colleague)
            ->get(route('testing.runs.index', $this->project))
            ->assertInertia(fn (Assert $page) => $page
                ->component('testing/runs')
                ->where('counts', ['points' => 1, 'runs' => 2])
                ->where('runs.data.0.name', 'Newer')
                ->where('runs.data.0.total', 1)
                ->has('points', 1)
                ->where('can.create', true));
    }
}
