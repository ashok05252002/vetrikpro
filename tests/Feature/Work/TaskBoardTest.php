<?php

namespace Tests\Feature\Work;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithMember(User $member): Project
    {
        $project = Project::factory()->create();
        $project->members()->attach($member);

        return $project;
    }

    public function test_the_board_groups_tasks_into_stage_columns()
    {
        $member = User::factory()->create();
        $project = $this->projectWithMember($member);

        Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertInertia(function (AssertableInertia $page) {
                $columns = collect($page->toArray()['props']['columns']);

                $page->component('projects/board');

                $this->assertSame(['todo', 'in_progress', 'in_review', 'done'], $columns->pluck('value')->all());
                $this->assertCount(2, $columns->firstWhere('value', 'todo')['items']);
                $this->assertCount(1, $columns->firstWhere('value', 'done')['items']);
            });
    }

    public function test_a_non_member_cannot_open_the_board()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_admins_can_open_any_board()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.show', $project))
            ->assertOk();
    }

    public function test_moving_a_task_changes_its_column_and_reindexes_positions()
    {
        $member = User::factory()->create();
        $project = $this->projectWithMember($member);

        $a = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 0]);
        $b = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 1]);
        $moving = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress, 'position' => 0]);

        $this->actingAs($member)
            ->patch(route('tasks.move', $moving), ['status' => 'todo', 'position' => 1])
            ->assertRedirect();

        $this->assertSame(TaskStatus::Todo, $moving->fresh()->status);

        // a, moving, b — dense and in order.
        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $moving->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_moving_a_task_to_done_stamps_completed_at()
    {
        $member = User::factory()->create();
        $project = $this->projectWithMember($member);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);

        $this->actingAs($member)->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_moving_a_task_back_out_of_done_clears_completed_at()
    {
        $member = User::factory()->create();
        $project = $this->projectWithMember($member);
        $task = Task::factory()->done()->create(['project_id' => $project->id]);

        $this->actingAs($member)->patch(route('tasks.move', $task), ['status' => 'in_review', 'position' => 0]);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_an_assignee_may_move_their_own_task_without_being_a_member()
    {
        $outsider = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $outsider->id]);

        $this->actingAs($outsider)
            ->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0])
            ->assertRedirect();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_an_unrelated_user_cannot_move_a_task()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.move', $task), ['status' => 'done', 'position' => 0])
            ->assertForbidden();
    }

    public function test_an_invalid_stage_is_rejected()
    {
        $member = User::factory()->create();
        $project = $this->projectWithMember($member);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->patch(route('tasks.move', $task), ['status' => 'shipped', 'position' => 0])
            ->assertSessionHasErrors('status');
    }
}
