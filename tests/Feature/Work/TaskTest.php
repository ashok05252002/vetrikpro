<?php

namespace Tests\Feature\Work;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\BoardOrdering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_create_a_task()
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($member);

        $this->actingAs($member)
            ->post(route('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Ship the payroll export',
                'status' => 'todo',
                'priority' => 'high',
                'assigned_to' => $member->id,
                'due_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect();

        $task = Task::where('title', 'Ship the payroll export')->first();

        $this->assertNotNull($task);
        $this->assertSame($member->id, $task->created_by);
        $this->assertSame($project->id, $task->project_id);
    }

    public function test_an_outsider_cannot_create_a_task_on_a_project()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Sneaky task',
                'status' => 'todo',
                'priority' => 'low',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'Sneaky task']);
    }

    public function test_new_tasks_stack_at_the_bottom_of_their_column()
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 0]);

        $this->assertSame(1, BoardOrdering::nextPosition(new Task(['project_id' => $project->id]), TaskStatus::Todo));
    }

    public function test_my_tasks_lists_only_your_own_by_default()
    {
        $user = User::factory()->create();
        Task::factory()->create(['assigned_to' => $user->id, 'title' => 'Mine']);
        Task::factory()->create(['title' => 'Belongs to someone else']);

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $titles = collect($page->toArray()['props']['tasks']['data'])->pluck('title');

                $this->assertSame(['Mine'], $titles->all());
            });
    }

    public function test_admins_can_widen_my_tasks_to_everyone()
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('tasks.index', ['scope' => 'all']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tasks.total', 3)
                ->where('filters.scope', 'all'));
    }

    public function test_an_employee_cannot_widen_my_tasks_to_everyone()
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('tasks.index', ['scope' => 'all']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tasks.total', 0)
                ->where('filters.scope', 'mine'));
    }

    public function test_the_overdue_filter_only_returns_late_unfinished_tasks()
    {
        $user = User::factory()->create();
        Task::factory()->overdue()->create(['assigned_to' => $user->id, 'title' => 'Late']);
        Task::factory()->done()->create(['assigned_to' => $user->id, 'title' => 'Done late', 'due_date' => now()->subWeek()]);
        Task::factory()->create(['assigned_to' => $user->id, 'title' => 'Not due yet', 'due_date' => now()->addWeek()]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['overdue' => 1]))
            ->assertInertia(function (AssertableInertia $page) {
                $titles = collect($page->toArray()['props']['tasks']['data'])->pluck('title');

                $this->assertSame(['Late'], $titles->all());
            });
    }

    public function test_a_member_can_comment_on_a_task()
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($member);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->post(route('tasks.comments.store', $task), ['body' => 'Blocked on the vendor.'])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'body' => 'Blocked on the vendor.',
        ]);
    }

    public function test_an_outsider_cannot_comment()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.comments.store', $task), ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_you_cannot_delete_someone_elses_comment()
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $comment = $task->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Theirs']);

        $this->actingAs($member)
            ->delete(route('tasks.comments.destroy', [$task, $comment]))
            ->assertForbidden();

        $this->assertDatabaseHas('task_comments', ['id' => $comment->id]);
    }

    public function test_deleting_a_project_deletes_its_tasks_and_comments()
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $comment = $task->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Note']);

        $project->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_project_progress_is_the_share_of_done_tasks()
    {
        $project = Project::factory()->create();
        Task::factory()->count(3)->done()->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id]);

        $this->assertSame(75, $project->progress());
    }

    public function test_a_project_with_no_tasks_is_zero_percent()
    {
        $this->assertSame(0, Project::factory()->create()->progress());
    }
}
