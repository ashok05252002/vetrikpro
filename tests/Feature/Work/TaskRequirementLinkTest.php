<?php

namespace Tests\Feature\Work;

use App\Models\Project;
use App\Models\RequirementPoint;
use App\Models\RequirementVersion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tasks link to the requirement points they deliver. */
class TaskRequirementLinkTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lead = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->lead->id]);
    }

    /** Save a version by hand, as the Requirements page does. */
    private function version(array $points, ?Project $project = null): RequirementVersion
    {
        $project ??= $this->project;
        $this->actingAs($this->lead)->post(route('projects.requirements.manual.store', $project), [
            'points' => array_map(fn ($p) => ['module' => $p[0], 'description' => $p[1]], $points),
        ])->assertSessionHasNoErrors();

        return RequirementVersion::latestFor($project);
    }

    private function point(RequirementVersion $version, int $number): RequirementPoint
    {
        return $version->points()->where('number', $number)->sole();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'project_id' => $this->project->id,
            'title' => 'GST split on invoice',
            'description' => null,
            'status' => 'todo',
            'priority' => 'medium',
            'assigned_to' => null,
            'due_date' => null,
            ...$overrides,
        ];
    }

    public function test_a_task_is_created_with_several_requirement_points()
    {
        $v1 = $this->version([['Billing', 'GST split'], ['Billing', 'IGST for other states'], ['Login', 'Sign in']]);

        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload([
            'requirement_point_ids' => [$this->point($v1, 1)->id, $this->point($v1, 2)->id],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([1, 2], Task::sole()->requirementPoints->pluck('number')->all());
    }

    public function test_points_are_optional()
    {
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertCount(0, Task::sole()->requirementPoints);
    }

    public function test_points_from_another_project_are_refused()
    {
        $other = Project::factory()->create(['owner_id' => $this->lead->id]);
        $foreign = $this->version([['X', 'Elsewhere']], $other);

        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload([
            'requirement_point_ids' => [$this->point($foreign, 1)->id],
        ]))->assertSessionHasErrors('requirement_point_ids');

        $this->assertSame(0, Task::count());
    }

    public function test_editing_replaces_the_links_and_leaving_them_out_keeps_them()
    {
        $v1 = $this->version([['Billing', 'GST split'], ['Billing', 'IGST']]);
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload(['requirement_point_ids' => [$this->point($v1, 1)->id]]));
        $task = Task::sole();

        $this->actingAs($this->lead)->put(route('tasks.update', $task), $this->payload(['requirement_point_ids' => [$this->point($v1, 2)->id]]))
            ->assertSessionHasNoErrors();
        $this->assertSame([2], $task->fresh()->requirementPoints->pluck('number')->all());

        // A save that does not send the field leaves the links alone.
        $this->actingAs($this->lead)->put(route('tasks.update', $task), $this->payload(['title' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame([2], $task->fresh()->requirementPoints->pluck('number')->all());

        $this->actingAs($this->lead)->put(route('tasks.update', $task), $this->payload(['requirement_point_ids' => []]));
        $this->assertCount(0, $task->fresh()->requirementPoints);
    }

    public function test_the_lookup_searches_the_latest_version_by_number_module_or_words()
    {
        $this->version([['Billing', 'GST split'], ['Login', 'Sign in']]);
        $this->version([['Billing', 'GST split'], ['Login', 'Sign in'], ['Reports', 'Monthly GST report']]);

        $url = fn (string $search) => route('projects.lookups', [$this->project, 'requirement-points', 'search' => $search]);

        $this->actingAs($this->lead)->getJson($url('3'))->assertOk()->assertJsonPath('data.0.reference', '#3')->assertJsonPath('version', 'V1.1');
        $this->actingAs($this->lead)->getJson($url('login'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Login — Sign in');
        $this->actingAs($this->lead)->getJson($url('gst'))->assertJsonCount(2, 'data');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson($url(''))->assertForbidden();
    }

    public function test_links_follow_unchanged_points_into_a_new_version_and_changed_ones_are_flagged()
    {
        $v1 = $this->version([['Billing', 'GST split'], ['Billing', 'IGST']]);
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload([
            'requirement_point_ids' => [$this->point($v1, 1)->id, $this->point($v1, 2)->id],
        ]));
        $task = Task::sole();

        // V1.1 keeps #1 as it was, rewrites #2.
        $v2 = $this->version([['Billing', 'GST split'], ['Billing', 'IGST and cess']]);

        $linked = $task->fresh()->requirementPoints->load('version');
        $this->assertEqualsCanonicalizing(
            ['V1.1 #1', 'V1 #2'],
            $linked->map(fn ($p) => $p->version->label().' '.$p->reference())->all(),
        );

        $points = $this->actingAs($this->lead)->get(route('tasks.show', $task))->viewData('page')['props']['task']['requirement_points'];
        $this->assertFalse(collect($points)->firstWhere('version', 'V1.1')['outdated']);
        $this->assertTrue(collect($points)->firstWhere('version', 'V1')['outdated']);
        $this->assertSame(0, $this->point($v2, 2)->tasks()->count());
    }

    public function test_the_requirements_page_shows_each_points_tasks_and_filters_uncovered_ones()
    {
        $v1 = $this->version([['Billing', 'GST split'], ['Login', 'Sign in']]);
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload(['requirement_point_ids' => [$this->point($v1, 1)->id]]));

        $page = $this->actingAs($this->lead)->get(route('projects.requirements.index', $this->project))->assertOk()->viewData('page')['props'];
        $this->assertSame('T-1', $page['points']['data'][0]['tasks'][0]['reference']);
        $this->assertSame(['linked' => 1, 'total' => 2], $page['coverage']);

        $none = $this->actingAs($this->lead)->get(route('projects.requirements.index', [$this->project, 'coverage' => 'none']))->viewData('page')['props'];
        $this->assertSame([2], collect($none['points']['data'])->pluck('number')->all());
    }

    public function test_deleting_a_task_removes_its_links()
    {
        $v1 = $this->version([['Billing', 'GST split']]);
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload(['requirement_point_ids' => [$this->point($v1, 1)->id]]));

        $this->actingAs($this->lead)->delete(route('tasks.destroy', Task::sole()));

        $this->assertSame(0, $this->point($v1, 1)->tasks()->count());
        $this->assertDatabaseCount('task_requirement_point', 0);
    }
}
