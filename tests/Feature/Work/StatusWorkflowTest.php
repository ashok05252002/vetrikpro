<?php

namespace Tests\Feature\Work;

use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    private User $creator;

    private User $assignee;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->creator = User::factory()->create();
        $this->assignee = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->members()->attach([$this->creator->id, $this->assignee->id, $this->colleague->id]);
    }

    private function task(): Task
    {
        return Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->creator->id, 'assigned_to' => $this->assignee->id]);
    }

    private function move(User $as, Task $task, string $to = 'in_progress')
    {
        return $this->actingAs($as)->patch(route('tasks.move', $task), ['status' => $to, 'position' => 0]);
    }

    public function test_creator_assignee_owner_and_admin_can_move_a_task()
    {
        foreach ([$this->creator, $this->assignee, $this->owner, User::factory()->admin()->create()] as $who) {
            $task = $this->task();
            $this->move($who, $task)->assertRedirect();
            $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
        }
    }

    public function test_another_member_cannot_move_it()
    {
        $task = $this->task();

        $this->move($this->colleague, $task)->assertForbidden();
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    public function test_even_hr_with_project_rights_cannot_move_someone_elses_task()
    {
        $task = $this->task();

        $this->move(User::factory()->hr()->create(), $task)->assertForbidden();
    }

    public function test_a_member_may_edit_the_wording_but_not_the_status()
    {
        $task = $this->task();
        $payload = ['project_id' => $this->project->id, 'title' => 'Reworded', 'status' => 'todo', 'priority' => 'medium'];

        $this->actingAs($this->colleague)->put(route('tasks.update', $task), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Reworded', $task->fresh()->title);

        $this->actingAs($this->colleague)->put(route('tasks.update', $task), [...$payload, 'status' => 'done'])->assertSessionHasErrors('status');
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    public function test_every_status_change_is_logged_with_who_and_when()
    {
        $task = $this->task();
        $this->move($this->assignee, $task, 'in_progress');
        $this->move($this->creator, $task, 'done');

        $log = $task->statusChanges()->get();

        $this->assertSame([null, 'todo', 'in_progress'], $log->pluck('from_status')->all());
        $this->assertSame(['todo', 'in_progress', 'done'], $log->pluck('to_status')->all());
        $this->assertSame([$this->assignee->id, $this->creator->id], $log->skip(1)->pluck('user_id')->values()->all());
    }

    public function test_reordering_in_the_same_column_is_not_logged_as_a_change()
    {
        $task = $this->task();
        $this->move($this->assignee, $task, 'todo');

        $this->assertSame(1, $task->statusChanges()->count());
    }

    public function test_the_board_says_which_cards_the_viewer_may_move()
    {
        $this->task();

        $this->actingAs($this->colleague)->get(route('projects.show', $this->project))
            ->assertInertia(fn (Assert $page) => $page->where('columns.0.items.0.can_move', false));
        $this->actingAs($this->assignee)->get(route('projects.show', $this->project))
            ->assertInertia(fn (Assert $page) => $page->where('columns.0.items.0.can_move', true));
    }

    public function test_the_task_page_shows_the_history()
    {
        $task = $this->task();
        $this->move($this->assignee, $task, 'in_review');

        $this->actingAs($this->colleague)->get(route('tasks.show', $task))
            ->assertInertia(fn (Assert $page) => $page
                ->has('task.history', 2)
                ->where('task.history.1.to_status', 'in_review')
                ->where('task.history.1.user.id', $this->assignee->id)
                ->where('can.changeStatus', false));
    }

    // Testing points follow the same rule

    public function test_only_the_creator_tester_owner_or_admin_move_a_testing_point()
    {
        $point = TestPoint::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->creator->id, 'assigned_to' => $this->assignee->id]);
        $move = fn (User $as) => $this->actingAs($as)->patch(route('testing.points.move', [$this->project, $point]), ['status' => 'closed', 'position' => 0]);

        $move($this->colleague)->assertForbidden();
        $move($this->assignee)->assertRedirect();

        $this->assertSame(TestPointStatus::Closed, $point->fresh()->status);
        $this->assertSame(['open', 'closed'], $point->statusChanges()->pluck('to_status')->all());
    }
}
