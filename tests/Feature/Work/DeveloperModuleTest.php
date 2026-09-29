<?php

namespace Tests\Feature\Work;

use App\Enums\BranchStatus;
use App\Enums\MergeRequestStatus;
use App\Enums\TestPointStatus;
use App\Exceptions\MergeRequestConflict;
use App\Models\Branch;
use App\Models\MergeRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use App\Services\MergeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeveloperModuleTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $dev;

    private User $devAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['default_branch' => 'main']);
        $this->dev = User::factory()->create();
        $this->devAdmin = User::factory()->create();
        $this->project->members()->attach($this->dev);
        $this->project->members()->attach($this->devAdmin, ['role' => 'dev_admin']);
    }

    private function register(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->dev)->post(route('projects.branches.store', $this->project), [
            'name' => 'feature/leave-approvals',
            'base_branch' => 'main',
            'description' => 'Managers approve or reject leave from the team view.',
            ...$overrides,
        ]);
    }

    private function branch(): Branch
    {
        $this->register();

        return $this->project->branches()->firstOrFail();
    }

    private function requestMerge(Branch $branch, array $overrides = [])
    {
        return $this->actingAs($this->dev)->post(route('projects.merge-requests.store', [$this->project, $branch]), [
            'title' => 'Leave approvals',
            'target_branch' => 'main',
            ...$overrides,
        ]);
    }

    private function mergeRequest(): MergeRequest
    {
        $this->requestMerge($this->branch());

        return $this->project->mergeRequests()->firstOrFail();
    }

    private function transition(MergeRequest $mr, string $to, string $expected, User $as, ?string $note = null)
    {
        return $this->actingAs($as)->post(route('projects.merge-requests.transition', [$this->project, $mr]), array_filter([
            'to' => $to,
            'expected' => $expected,
            'note' => $note,
        ]));
    }

    // Registering branches

    public function test_a_member_registers_a_branch_with_its_task_and_testing_points()
    {
        $tasks = Task::factory()->count(2)->create(['project_id' => $this->project->id]);
        $point = TestPoint::factory()->create(['project_id' => $this->project->id]);

        $this->register(['task_ids' => $tasks->pluck('id')->all(), 'test_point_ids' => [$point->id]])
            ->assertSessionHas('success');

        $branch = $this->project->branches()->first();
        $this->assertSame(BranchStatus::Active, $branch->status);
        $this->assertSame($this->dev->id, $branch->created_by);
        $this->assertEqualsCanonicalizing($tasks->pluck('id')->all(), $branch->tasks()->pluck('tasks.id')->all());
        $this->assertSame([$point->id], $branch->testPoints()->pluck('test_points.id')->all());
    }

    public function test_a_description_is_required()
    {
        $this->register(['description' => ''])->assertSessionHasErrors('description');
    }

    public function test_invalid_git_names_are_rejected()
    {
        foreach (['has space', 'double..dot', '/leading', 'trailing/', 'a//b', 'x.lock'] as $bad) {
            $this->register(['name' => $bad])->assertSessionHasErrors('name');
        }

        $this->register(['name' => 'fix/HR-12_date.format'])->assertSessionHasNoErrors();
    }

    public function test_a_branch_name_is_registered_once_per_project()
    {
        $this->register();
        $this->register()->assertSessionHasErrors('name');

        // The same name on another project is a different branch.
        $other = Project::factory()->create();
        $other->members()->attach($this->dev);
        $this->actingAs($this->dev)
            ->post(route('projects.branches.store', $other), ['name' => 'feature/leave-approvals', 'base_branch' => 'main', 'description' => 'x'])
            ->assertSessionHasNoErrors();
    }

    public function test_linked_work_must_belong_to_the_project()
    {
        $foreign = Task::factory()->create();

        $this->register(['task_ids' => [$foreign->id]])->assertSessionHasErrors('task_ids.0');
    }

    public function test_outsiders_cannot_register_branches_or_see_the_git_tab()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('projects.git', $this->project))->assertForbidden();
        $this->register([], $outsider)->assertForbidden();
    }

    public function test_linking_more_work_later_is_additive()
    {
        $branch = $this->branch();
        [$a, $b] = Task::factory()->count(2)->create(['project_id' => $this->project->id]);

        $this->actingAs($this->dev)->post(route('projects.branches.links.store', [$this->project, $branch]), ['task_ids' => [$a->id]]);
        $this->actingAs($this->devAdmin)->post(route('projects.branches.links.store', [$this->project, $branch]), ['task_ids' => [$b->id]]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $branch->tasks()->pluck('tasks.id')->all());

        $this->actingAs($this->dev)->delete(route('projects.branches.links.destroy', [$this->project, $branch, 'tasks', $a->id]));
        $this->assertSame([$b->id], $branch->tasks()->pluck('tasks.id')->all());
    }

    public function test_another_member_cannot_edit_someone_elses_branch()
    {
        $branch = $this->branch();
        $colleague = User::factory()->create();
        $this->project->members()->attach($colleague);

        $this->actingAs($colleague)
            ->put(route('projects.branches.update', [$this->project, $branch]), ['base_branch' => 'main', 'description' => 'Hijacked'])
            ->assertForbidden();
    }

    // Opening merge requests

    public function test_a_member_asks_a_dev_admin_to_merge()
    {
        $branch = $this->branch();

        $this->requestMerge($branch, ['reviewer_id' => $this->devAdmin->id])->assertSessionHas('success');

        $mr = $this->project->mergeRequests()->first();
        $this->assertSame('MR-1', $mr->reference());
        $this->assertSame(MergeRequestStatus::Open, $mr->status);
        $this->assertSame($this->devAdmin->id, $mr->reviewer_id);
        $this->assertSame(['opened'], $mr->events()->pluck('action')->all());
    }

    public function test_the_reviewer_must_be_one_of_the_projects_dev_admins()
    {
        $this->requestMerge($this->branch(), ['reviewer_id' => $this->dev->id])->assertSessionHasErrors('reviewer_id');
    }

    public function test_a_branch_has_at_most_one_live_merge_request()
    {
        $branch = $this->branch();
        $this->requestMerge($branch);

        $this->requestMerge($branch)->assertForbidden();
        $this->assertSame(1, $branch->mergeRequests()->count());

        // The service refuses too, even if a stale page slipped past the policy.
        $this->expectException(MergeRequestConflict::class);
        app(MergeRequestService::class)->open($branch, $this->dev, ['title' => 'Again', 'target_branch' => 'main']);
    }

    public function test_after_closing_a_request_the_branch_can_ask_again()
    {
        $mr = $this->mergeRequest();
        $this->transition($mr, 'closed', 'open', $this->dev);

        $this->requestMerge($mr->branch)->assertSessionHas('success');
        $this->assertSame(['MR-1', 'MR-2'], $this->project->mergeRequests()->orderBy('number')->get()->map->reference()->all());
    }

    // Reviewing

    public function test_the_full_review_flow_ends_with_the_branch_merged()
    {
        $mr = $this->mergeRequest();

        $this->transition($mr, 'changes_requested', 'open', $this->devAdmin, 'Handle half-day leave.')->assertSessionHas('success');
        $this->transition($mr, 'open', 'changes_requested', $this->dev)->assertSessionHas('success');
        $this->transition($mr, 'approved', 'open', $this->devAdmin)->assertSessionHas('success');
        $this->transition($mr, 'merged', 'approved', $this->devAdmin)->assertSessionHas('success');

        $mr->refresh();
        $this->assertSame(MergeRequestStatus::Merged, $mr->status);
        $this->assertSame($this->devAdmin->id, $mr->merged_by);
        $this->assertSame(BranchStatus::Merged, $mr->branch->status);
        $this->assertSame(['opened', 'changes_requested', 'resubmitted', 'approved', 'merged'], $mr->events()->pluck('action')->all());
        $this->assertSame('Handle half-day leave.', $mr->events()->where('action', 'changes_requested')->value('note'));
    }

    public function test_nobody_reviews_their_own_request()
    {
        // The requester is also a dev admin here.
        $this->project->members()->updateExistingPivot($this->dev->id, ['role' => 'dev_admin']);
        $mr = $this->mergeRequest();

        $this->transition($mr, 'approved', 'open', $this->dev)->assertForbidden();
        $this->assertSame(MergeRequestStatus::Open, $mr->fresh()->status);
    }

    public function test_plain_members_cannot_approve()
    {
        $mr = $this->mergeRequest();
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $this->transition($mr, 'approved', 'open', $member)->assertForbidden();
    }

    public function test_merge_any_permission_reviews_on_any_project()
    {
        $mr = $this->mergeRequest();
        $admin = User::factory()->admin()->create();

        $this->transition($mr, 'approved', 'open', $admin)->assertSessionHas('success');
    }

    public function test_requesting_changes_needs_a_reason()
    {
        $mr = $this->mergeRequest();

        $this->transition($mr, 'changes_requested', 'open', $this->devAdmin)->assertSessionHasErrors('note');
    }

    public function test_an_open_request_cannot_jump_straight_to_merged()
    {
        $mr = $this->mergeRequest();

        $this->transition($mr, 'merged', 'open', $this->devAdmin)->assertSessionHas('error');
        $this->assertSame(MergeRequestStatus::Open, $mr->fresh()->status);
    }

    public function test_a_second_reviewer_acting_on_a_stale_page_is_told_who_got_there_first()
    {
        $mr = $this->mergeRequest();
        $second = User::factory()->create(['name' => 'Second Reviewer']);
        $this->project->members()->attach($second, ['role' => 'dev_admin']);
        $this->devAdmin->update(['name' => 'First Reviewer']);

        // Both loaded the page while it was open; the first approves.
        $this->transition($mr, 'approved', 'open', $this->devAdmin);

        // The second still believes it is open.
        $this->transition($mr, 'changes_requested', 'open', $second, 'Needs work')
            ->assertSessionHas('error', 'MR-1 is already Approved — First Reviewer got there first. The page has been refreshed.');

        $this->assertSame(MergeRequestStatus::Approved, $mr->fresh()->status);
        $this->assertSame(2, $mr->events()->count());
    }

    public function test_approval_freezes_the_linked_work()
    {
        $mr = $this->mergeRequest();
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $this->transition($mr, 'approved', 'open', $this->devAdmin);

        $this->actingAs($this->dev)
            ->post(route('projects.branches.links.store', [$this->project, $mr->branch]), ['task_ids' => [$task->id]])
            ->assertForbidden();
    }

    public function test_the_merge_request_page_reports_readiness()
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'status' => 'in_progress']);
        $passed = TestPoint::factory()->create(['project_id' => $this->project->id, 'status' => TestPointStatus::Closed]);
        $failed = TestPoint::factory()->create(['project_id' => $this->project->id, 'status' => TestPointStatus::Repeated]);
        $this->register(['task_ids' => [$task->id], 'test_point_ids' => [$passed->id, $failed->id]]);
        $branch = $this->project->branches()->first();
        $this->requestMerge($branch);
        $mr = $this->project->mergeRequests()->first();

        $this->actingAs($this->devAdmin)
            ->get(route('projects.merge-requests.show', [$this->project, $mr]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/merge-request')
                ->where('mergeRequest.readiness', ['tasks_open' => 1, 'tests_not_passed' => 1, 'tests_failed' => 1, 'ready' => false])
                ->where('mergeRequest.next', ['approved', 'changes_requested', 'closed'])
                ->where('can.review', true)
                ->has('mergeRequest.tasks', 1)
                ->has('mergeRequest.test_points', 2));
    }

    public function test_anyone_on_the_project_can_comment()
    {
        $mr = $this->mergeRequest();

        $this->actingAs($this->dev)->post(route('projects.merge-requests.comments.store', [$this->project, $mr]), ['note' => 'Pushed the fix.']);

        $this->assertSame('Pushed the fix.', $mr->events()->where('action', 'commented')->value('note'));
        $this->assertSame(MergeRequestStatus::Open, $mr->fresh()->status);
    }

    // Inbox

    public function test_the_inbox_shows_what_awaits_my_review()
    {
        $this->mergeRequest();

        $this->actingAs($this->devAdmin)
            ->get(route('merge-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('scope', 'review')->has('mergeRequests.data', 1)->where('awaitingCount', 1));

        // The requester does not review their own.
        $this->actingAs($this->dev)
            ->get(route('merge-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->has('mergeRequests.data', 0));

        $this->actingAs($this->dev)
            ->get(route('merge-requests.index', ['scope' => 'mine']))
            ->assertInertia(fn (Assert $page) => $page->has('mergeRequests.data', 1));
    }

    public function test_a_request_for_a_named_reviewer_waits_only_on_them()
    {
        $other = User::factory()->create();
        $this->project->members()->attach($other, ['role' => 'dev_admin']);
        $this->requestMerge($this->branch(), ['reviewer_id' => $other->id]);

        $this->actingAs($this->devAdmin)->get(route('merge-requests.index'))->assertInertia(fn (Assert $page) => $page->has('mergeRequests.data', 0));
        $this->actingAs($other)->get(route('merge-requests.index'))->assertInertia(fn (Assert $page) => $page->has('mergeRequests.data', 1));
    }

    public function test_the_inbox_never_shows_another_projects_requests()
    {
        $this->mergeRequest();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('merge-requests.index', ['scope' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('mergeRequests.data', 0));
    }

    public function test_the_work_lookup_warns_when_another_branch_already_has_it()
    {
        $branch = $this->branch();
        $task = Task::factory()->create(['project_id' => $branch->project_id]);
        $branch->tasks()->attach($task);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson(route('projects.lookups', [$branch->project_id, 'tasks']))
            ->assertJsonPath('data.0.on_branches.0.name', $branch->name);

        // On that branch's own page it is not "another" branch.
        $this->actingAs($admin)->getJson(route('projects.lookups', [$branch->project_id, 'tasks', 'except_branch' => $branch->id]))
            ->assertJsonCount(0, 'data.0.on_branches');
    }
}
