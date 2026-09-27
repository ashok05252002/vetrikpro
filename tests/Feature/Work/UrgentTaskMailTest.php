<?php

namespace Tests\Feature\Work;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskMarkedUrgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UrgentTaskMailTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $lead;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->lead = User::factory()->create(['name' => 'Lead Person']);
        $this->dev = User::factory()->create(['name' => 'Dev Person']);
        $this->project = Project::factory()->create(['owner_id' => $this->lead->id]);
        $this->project->members()->attach([$this->lead->id, $this->dev->id]);
    }

    private function payload(array $overrides = []): array
    {
        return ['project_id' => $this->project->id, 'title' => 'Payroll export fails', 'status' => 'todo', 'priority' => 'medium', 'assigned_to' => $this->dev->id, ...$overrides];
    }

    public function test_creating_an_urgent_task_emails_the_assignee()
    {
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload(['priority' => 'urgent']));

        Notification::assertSentTo($this->dev, TaskMarkedUrgent::class, fn (TaskMarkedUrgent $n) => $n->why === TaskMarkedUrgent::CREATED && $n->actor->is($this->lead));
        Notification::assertNotSentTo($this->lead, TaskMarkedUrgent::class);
    }

    public function test_raising_a_task_to_urgent_emails_the_assignee()
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'priority' => TaskPriority::Medium]);

        $this->actingAs($this->lead)->put(route('tasks.update', $task), $this->payload(['priority' => 'urgent']));

        Notification::assertSentTo($this->dev, TaskMarkedUrgent::class, fn (TaskMarkedUrgent $n) => $n->why === TaskMarkedUrgent::ESCALATED);
    }

    public function test_handing_an_urgent_task_to_someone_emails_them()
    {
        $other = User::factory()->create();
        $this->project->members()->attach($other);
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'priority' => TaskPriority::Urgent]);

        $this->actingAs($this->lead)->put(route('tasks.update', $task), $this->payload(['priority' => 'urgent', 'assigned_to' => $other->id]));

        Notification::assertSentTo($other, TaskMarkedUrgent::class, fn (TaskMarkedUrgent $n) => $n->why === TaskMarkedUrgent::REASSIGNED);
    }

    public function test_no_email_for_non_urgent_or_unchanged_or_self_marked_work()
    {
        // Created with nobody signed in, as a seeder would: no email.
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'priority' => TaskPriority::Urgent]);
        $mine = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id]);

        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload(['priority' => 'high']));
        // Already urgent, only the title changes.
        $this->actingAs($this->lead)->put(route('tasks.update', $task), $this->payload(['priority' => 'urgent', 'title' => 'Renamed']));
        // The assignee marks their own task urgent.
        $this->actingAs($this->dev)->put(route('tasks.update', $mine), $this->payload(['priority' => 'urgent']));
        // Urgent but nobody assigned.
        $this->actingAs($this->lead)->post(route('tasks.store'), $this->payload(['priority' => 'urgent', 'assigned_to' => null]));

        // The high-priority task still tells its assignee it was assigned (WorkAssigned); none is urgent mail.
        Notification::assertSentTimes(TaskMarkedUrgent::class, 0);
    }

    public function test_the_email_is_branded_and_says_what_and_why()
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'priority' => TaskPriority::Urgent, 'title' => 'Payroll export fails']);

        $mail = (new TaskMarkedUrgent($task, $this->lead, TaskMarkedUrgent::ESCALATED))->toMail($this->dev);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Urgent: T-', $mail->subject);
        $this->assertStringContainsString('Payroll export fails', $html);
        $this->assertStringContainsString('Lead Person', $html);
        $this->assertStringContainsString('marked your task as urgent', $html);
        $this->assertStringContainsString(route('tasks.show', $task), $html);
    }
}
