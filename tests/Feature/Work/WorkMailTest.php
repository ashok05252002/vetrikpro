<?php

namespace Tests\Feature\Work;

use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use App\Notifications\OverdueTasksDigest;
use App\Notifications\WorkAssigned;
use App\Notifications\WorkStatusChanged;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkMailTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $lead;

    private User $dev;

    private User $tester;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->lead = User::factory()->create(['name' => 'Lead Person']);
        $this->dev = User::factory()->create(['name' => 'Dev Person']);
        $this->tester = User::factory()->create(['name' => 'Tess Tester']);
        $this->project = Project::factory()->create(['owner_id' => $this->lead->id]);
        $this->project->members()->attach([$this->lead->id, $this->dev->id, $this->tester->id]);
    }

    public function test_assigning_a_task_emails_the_new_assignee_but_not_the_actor()
    {
        $this->actingAs($this->lead)->post(route('tasks.store'), [
            'project_id' => $this->project->id, 'title' => 'Export', 'status' => 'todo', 'priority' => 'medium', 'assigned_to' => $this->dev->id,
        ]);

        Notification::assertSentTo($this->dev, WorkAssigned::class);
        Notification::assertNotSentTo($this->lead, WorkAssigned::class);
    }

    public function test_assignment_mail_can_be_switched_off()
    {
        app(Settings::class)->set(['notify.task.assigned' => false]);

        $this->actingAs($this->lead)->post(route('tasks.store'), [
            'project_id' => $this->project->id, 'title' => 'Export', 'status' => 'todo', 'priority' => 'medium', 'assigned_to' => $this->dev->id,
        ]);

        Notification::assertNothingSent();
    }

    public function test_a_status_that_is_switched_on_emails_assignee_and_reporter_not_the_mover()
    {
        app(Settings::class)->set(['notify.bug.status' => ['ready_for_test']]);
        $point = TestPoint::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->tester->id, 'assigned_to' => $this->dev->id, 'status' => TestPointStatus::InProgress]);

        $this->actingAs($this->dev);
        $point->update(['status' => TestPointStatus::ReadyForTest]);

        Notification::assertSentTo($this->tester, WorkStatusChanged::class, fn (WorkStatusChanged $n) => $n->from === 'In progress');
        Notification::assertNotSentTo($this->dev, WorkStatusChanged::class);
    }

    public function test_a_status_that_is_switched_off_sends_nothing()
    {
        app(Settings::class)->set(['notify.task.status' => ['done']]);
        $task = Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->lead->id, 'assigned_to' => $this->dev->id, 'status' => TaskStatus::Todo]);

        $this->actingAs($this->dev);
        $task->update(['status' => TaskStatus::InProgress]);
        Notification::assertNothingSent();

        $task->update(['status' => TaskStatus::Done]);
        Notification::assertSentTo($this->lead, WorkStatusChanged::class);
    }

    public function test_nothing_is_sent_with_nobody_signed_in()
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id]);

        Notification::assertNothingSent();
    }

    public function test_the_overdue_digest_goes_to_each_assignee_and_a_summary_to_owners()
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'status' => TaskStatus::InProgress, 'due_date' => today()->subDays(3)]);
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'status' => TaskStatus::Todo, 'due_date' => today()->subDay()]);
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->tester->id, 'status' => TaskStatus::Done, 'due_date' => today()->subDays(5)]);
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->tester->id, 'status' => TaskStatus::Todo, 'due_date' => today()->addDay()]);

        $this->artisan('tasks:overdue-digest')->assertSuccessful();

        Notification::assertSentTo($this->dev, OverdueTasksDigest::class, fn (OverdueTasksDigest $n) => $n->scope === OverdueTasksDigest::MINE && $n->tasks->count() === 2);
        Notification::assertSentTo($this->lead, OverdueTasksDigest::class, fn (OverdueTasksDigest $n) => $n->scope === OverdueTasksDigest::OWNED && $n->tasks->count() === 2);
        Notification::assertNotSentTo($this->tester, OverdueTasksDigest::class);
    }

    public function test_the_overdue_digest_respects_its_switches()
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'status' => TaskStatus::Todo, 'due_date' => today()->subDay()]);

        app(Settings::class)->set(['notify.overdue.owners' => false]);
        $this->artisan('tasks:overdue-digest');
        Notification::assertNotSentTo($this->lead, OverdueTasksDigest::class);

        app(Settings::class)->set(['notify.overdue.enabled' => false]);
        Notification::fake();
        $this->artisan('tasks:overdue-digest');
        Notification::assertNothingSent();
    }

    public function test_an_administrator_can_change_the_triggers()
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.config.notifications.update'), [
            'task_assigned' => false, 'task_urgent' => true, 'task_status' => ['done'],
            'bug_assigned' => true, 'bug_status' => ['open', 'closed'],
            'overdue_enabled' => true, 'overdue_owners' => false, 'overdue_time' => '18:30',
        ])->assertSessionHasNoErrors();

        $s = app(Settings::class);
        $this->assertFalse($s->get('notify.task.assigned'));
        $this->assertSame(['open', 'closed'], $s->get('notify.bug.status'));
        $this->assertSame('18:30', $s->get('notify.overdue.time'));

        $this->actingAs(User::factory()->hr()->create())->put(route('admin.config.notifications.update'), [])->assertForbidden();
    }
}
