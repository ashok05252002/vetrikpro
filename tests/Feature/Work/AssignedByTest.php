<?php

namespace Tests\Feature\Work;

use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AssignedByTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_a_task_records_who_assigned_it()
    {
        $lead = User::factory()->create();
        $dev = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $lead->id]);
        $project->members()->attach([$lead->id, $dev->id]);

        $this->actingAs($lead)->post(route('tasks.store'), [
            'project_id' => $project->id, 'title' => 'Payroll export', 'status' => 'todo', 'priority' => 'medium', 'assigned_to' => $dev->id,
        ])->assertRedirect();

        $task = Task::where('title', 'Payroll export')->first();
        $this->assertSame($lead->id, $task->assigned_by);
        $this->assertSame($lead->id, $task->created_by);
    }

    public function test_the_assigner_follows_the_latest_change_and_clears_on_unassign()
    {
        $task = Task::factory()->create(['assigned_to' => null]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first);
        $task->update(['assigned_to' => User::factory()->create()->id]);
        $this->assertSame($first->id, $task->fresh()->assigned_by);

        // Editing something else leaves the assigner alone.
        $this->actingAs($second);
        $task->update(['title' => 'Renamed']);
        $this->assertSame($first->id, $task->fresh()->assigned_by);

        $task->update(['assigned_to' => null]);
        $this->assertNull($task->fresh()->assigned_by);
    }

    public function test_a_bug_records_its_assigner_too()
    {
        $point = TestPoint::factory()->create(['assigned_to' => null]);
        $lead = User::factory()->create();

        $this->actingAs($lead);
        $point->update(['assigned_to' => User::factory()->create()->id]);

        $this->assertSame($lead->id, $point->fresh()->assigned_by);
    }

    public function test_board_cards_carry_who_added_and_who_assigned()
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create(['created_by' => $admin->id, 'assigned_to' => null]);
        $this->actingAs($admin);
        $task->update(['assigned_to' => $admin->id]);

        $this->get(route('projects.show', $task->project_id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('columns.0.items.0.creator.id', $admin->id)
                ->where('columns.0.items.0.assigner.id', $admin->id));
    }
}
