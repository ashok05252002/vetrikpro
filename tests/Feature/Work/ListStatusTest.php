<?php

namespace Tests\Feature\Work;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ListStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_tasks_says_which_rows_i_may_move_and_moving_works()
    {
        $dev = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($dev);
        $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $dev->id, 'status' => TaskStatus::Todo]);

        $this->actingAs($dev)->get(route('tasks.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tasks.data.0.can_move', true));

        $this->actingAs($dev)->patch(route('tasks.move', $task), ['status' => 'in_progress', 'position' => 9999])->assertRedirect();

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }
}
