<?php

namespace Tests\Feature\Work;

use App\Enums\TestPointStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TestingAndNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Project $project): User
    {
        $user = User::factory()->create();
        $project->members()->attach($user);

        return $user;
    }

    private function pointPayload(array $overrides = []): array
    {
        return [
            'title' => 'Leave request rejects overlapping dates',
            'steps' => '1. Request 3–5 May 2. Request 4–6 May',
            'expected_result' => 'The second request is refused.',
            'status' => 'open',
            'priority' => 'high',
            ...$overrides,
        ];
    }

    // Numbering

    public function test_tasks_are_numbered_per_project()
    {
        [$a, $b] = Project::factory()->count(2)->create();

        $first = Task::factory()->create(['project_id' => $a->id]);
        $second = Task::factory()->create(['project_id' => $a->id]);
        $other = Task::factory()->create(['project_id' => $b->id]);

        $this->assertSame([1, 2, 1], [$first->number, $second->number, $other->number]);
        $this->assertSame('T-2', $second->reference());
    }

    public function test_numbers_are_never_reused_after_a_delete_in_the_middle()
    {
        $project = Project::factory()->create();
        [$one, $two, $three] = Task::factory()->count(3)->create(['project_id' => $project->id]);

        $two->delete();

        $this->assertSame(4, Task::factory()->create(['project_id' => $project->id])->number);
    }

    public function test_the_database_refuses_a_duplicate_number()
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'number' => 7]);

        $this->expectException(QueryException::class);
        Task::factory()->create(['project_id' => $project->id, 'number' => 7]);
    }

    public function test_references_parse_in_the_forms_people_type()
    {
        $this->assertSame(12, Task::parseReference('T-12'));
        $this->assertSame(12, Task::parseReference('t12'));
        $this->assertSame(12, Task::parseReference(' 12 '));
        $this->assertSame(4, TestPoint::parseReference('TP-4'));
        $this->assertNull(Task::parseReference('TP-4'));
        $this->assertNull(Task::parseReference('login page'));
    }

    // Task list view

    public function test_the_tasks_tab_has_a_filterable_list_view()
    {
        $project = Project::factory()->create();
        $member = $this->memberOf($project);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Alpha']);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Beta', 'status' => 'done']);

        $this->actingAs($member)
            ->get(route('projects.show', [$project, 'view' => 'list', 'search' => 'T-2']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('view', 'list')
                ->missing('columns')
                ->has('list.data', 1)
                ->where('list.data.0.title', 'Beta')
                ->where('list.data.0.reference', 'T-2'));

        $this->actingAs($member)
            ->get(route('projects.show', [$project, 'view' => 'list', 'status' => 'done']))
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1));
    }

    // Testing points

    public function test_a_member_can_create_a_numbered_testing_point()
    {
        $project = Project::factory()->create();
        $member = $this->memberOf($project);

        $this->actingAs($member)
            ->post(route('testing.points.store', $project), $this->pointPayload())
            ->assertSessionHas('success', 'TP-1 “Leave request rejects overlapping dates” created.');

        $point = $project->testPoints()->first();
        $this->assertSame(1, $point->number);
        $this->assertSame($member->id, $point->created_by);
    }

    public function test_outsiders_cannot_see_or_add_testing_points()
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('testing.points.index', $project))->assertForbidden();
        $this->actingAs($outsider)->post(route('testing.points.store', $project), $this->pointPayload())->assertForbidden();
    }

    public function test_the_board_groups_points_by_status()
    {
        $project = Project::factory()->create();
        TestPoint::factory()->count(2)->create(['project_id' => $project->id]);
        TestPoint::factory()->create(['project_id' => $project->id, 'status' => TestPointStatus::Repeated]);

        $this->actingAs($this->memberOf($project))
            ->get(route('testing.points.index', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->component('testing/points')
                ->where('columns', fn ($columns) => collect($columns)->pluck('value')->all() === ['open', 'in_progress', 'ready_for_test', 'repeated', 'closed']
                    && count(collect($columns)->firstWhere('value', 'open')['items']) === 2)
                ->where('summary.repeated', 1));
    }

    public function test_reaching_an_outcome_records_who_ran_it_and_when()
    {
        $project = Project::factory()->create();
        $tester = $this->memberOf($project);
        $point = TestPoint::factory()->create(['project_id' => $project->id, 'assigned_to' => $tester->id]);

        $this->actingAs($tester)
            ->patch(route('testing.points.move', [$project, $point]), ['status' => 'closed', 'position' => 0]);

        $point->refresh();
        $this->assertSame(TestPointStatus::Closed, $point->status);
        $this->assertSame($tester->id, $point->last_tested_by);
        $this->assertNotNull($point->last_tested_at);
    }

    public function test_moving_back_to_testing_keeps_the_last_run_on_record()
    {
        $project = Project::factory()->create();
        $tester = $this->memberOf($project);
        $point = TestPoint::factory()->create(['project_id' => $project->id, 'assigned_to' => $tester->id]);

        $this->actingAs($tester)->patch(route('testing.points.move', [$project, $point]), ['status' => 'repeated', 'position' => 0]);
        $this->actingAs($tester)->patch(route('testing.points.move', [$project, $point]), ['status' => 'ready_for_test', 'position' => 0]);

        $this->assertNotNull($point->fresh()->last_tested_at);
    }

    public function test_board_positions_stay_dense_after_moves()
    {
        $project = Project::factory()->create();
        $member = $this->memberOf($project);
        $points = collect(range(0, 2))->map(fn ($i) => TestPoint::factory()->create(['project_id' => $project->id, 'position' => $i, 'assigned_to' => $member->id]));

        $this->actingAs($member)->patch(route('testing.points.move', [$project, $points[2]]), ['status' => 'open', 'position' => 0]);

        $order = $project->testPoints()->where('status', 'open')->orderBy('position')->pluck('id')->all();
        $this->assertSame([$points[2]->id, $points[0]->id, $points[1]->id], $order);
        $this->assertSame([0, 1, 2], $project->testPoints()->orderBy('position')->pluck('position')->all());
    }

    public function test_a_point_can_only_verify_a_task_on_its_own_project()
    {
        $project = Project::factory()->create();
        $member = $this->memberOf($project);
        $foreign = Task::factory()->create();
        $own = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->post(route('testing.points.store', $project), $this->pointPayload(['task_id' => $foreign->id]))
            ->assertSessionHasErrors('task_id');

        $this->actingAs($member)
            ->post(route('testing.points.store', $project), $this->pointPayload(['task_id' => $own->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_point_is_only_reachable_through_its_own_project()
    {
        $point = TestPoint::factory()->create();
        $other = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('testing.points.show', [$other, $point]))
            ->assertNotFound();
    }

    public function test_only_the_owner_or_a_project_manager_deletes_a_point()
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $member = $this->memberOf($project);
        $point = TestPoint::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)->delete(route('testing.points.destroy', [$project, $point]))->assertForbidden();
        $this->actingAs($owner)->delete(route('testing.points.destroy', [$project, $point]))->assertRedirect(route('testing.points.index', $project));

        $this->assertNull($point->fresh());
    }

    public function test_the_testing_list_filters_by_status_and_number()
    {
        $project = Project::factory()->create();
        $member = $this->memberOf($project);
        TestPoint::factory()->create(['project_id' => $project->id, 'title' => 'First']);
        TestPoint::factory()->create(['project_id' => $project->id, 'title' => 'Second', 'status' => TestPointStatus::Repeated]);

        $this->actingAs($member)
            ->get(route('testing.points.index', [$project, 'view' => 'list', 'status' => 'repeated']))
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1)->where('list.data.0.title', 'Second'));

        $this->actingAs($member)
            ->get(route('testing.points.index', [$project, 'view' => 'list', 'search' => 'TP-1']))
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1)->where('list.data.0.title', 'First'));
    }

    // Reference lookups

    public function test_lookups_find_work_by_number_or_title_within_the_project()
    {
        $project = Project::factory()->create();
        $member = $this->memberOf($project);
        Task::factory()->count(12)->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Payroll export']);
        Task::factory()->create(['title' => 'Payroll export elsewhere']);

        $byNumber = $this->actingAs($member)->getJson(route('projects.lookups', [$project, 'tasks', 'search' => 'T-12']))->json('data');
        $this->assertSame('T-12', $byNumber[0]['reference']);

        $byTitle = $this->actingAs($member)->getJson(route('projects.lookups', [$project, 'tasks', 'search' => 'payroll']))->json('data');
        $this->assertSame(['Payroll export'], collect($byTitle)->pluck('title')->all());
    }

    public function test_lookups_are_for_project_members_only()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('projects.lookups', [$project, 'test-points']))
            ->assertForbidden();
    }
}
