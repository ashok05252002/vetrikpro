<?php

namespace Tests\Feature\Work;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Mail\DailySummary;
use App\Models\Project;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use App\Notifications\OverdueTasksDigest;
use App\Services\DailySummary\ProjectSummary;
use App\Support\Clock;
use App\Support\MailThread;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $dev;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['name' => 'Olive Owner', 'email' => 'olive@example.com']);
        $this->dev = User::factory()->create(['name' => 'Dev Person', 'email' => 'dev@example.com']);
        $this->project = Project::factory()->create(['name' => 'Alpha', 'owner_id' => $this->owner->id]);
        $this->project->members()->attach([$this->owner->id, $this->dev->id]);
    }

    private function sentTo(string $email): ?DailySummary
    {
        return Mail::sent(DailySummary::class, fn (DailySummary $m) => $m->hasTo($email))->first();
    }

    public function test_leadership_gets_every_active_project_in_one_email()
    {
        Project::factory()->create(['name' => 'Beta']);
        Project::factory()->create(['name' => 'Paused', 'status' => ProjectStatus::OnHold]);
        app(Settings::class)->set(['notify.summary.emails' => ['founder@example.com'], 'notify.summary.projects' => false]);

        $this->artisan('summaries:daily')->assertSuccessful();

        Mail::assertSentCount(1);
        $mail = $this->sentTo('founder@example.com');
        $this->assertTrue($mail->leadership);
        $this->assertSame(['Alpha', 'Beta'], array_column($mail->projects, 'name'));
    }

    public function test_a_project_summary_goes_to_the_owner_and_everyone_added()
    {
        $disabled = User::factory()->create(['is_active' => false]);
        $this->project->summaryRecipients()->createMany([
            ['user_id' => $this->dev->id],
            ['email' => 'client@outside.test'],
            ['user_id' => $disabled->id],
        ]);

        $this->artisan('summaries:daily')->assertSuccessful();

        $this->assertNotNull($this->sentTo('olive@example.com'));
        $this->assertNotNull($this->sentTo('dev@example.com'));
        $this->assertFalse($this->sentTo('client@outside.test')->leadership);
        $this->assertNull($this->sentTo($disabled->email));
        Mail::assertSentCount(3);
    }

    public function test_someone_on_several_lists_gets_one_email_with_all_their_projects()
    {
        $beta = Project::factory()->create(['name' => 'Beta', 'owner_id' => $this->owner->id]);
        $beta->summaryRecipients()->create(['user_id' => $this->dev->id]);
        $this->project->summaryRecipients()->create(['user_id' => $this->dev->id]);
        // The owner is on the leadership list too, in a different case.
        app(Settings::class)->set(['notify.summary.emails' => ['olive@example.com']]);
        Project::factory()->create(['name' => 'Gamma']);

        $this->artisan('summaries:daily');

        Mail::assertSent(DailySummary::class, fn (DailySummary $m) => $m->hasTo('olive@example.com'));
        $this->assertSame(1, Mail::sent(DailySummary::class, fn (DailySummary $m) => $m->hasTo('olive@example.com'))->count());
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], array_column($this->sentTo('olive@example.com')->projects, 'name'));
        $this->assertSame(['Alpha', 'Beta'], array_column($this->sentTo('dev@example.com')->projects, 'name'));
    }

    public function test_both_switches_off_sends_nothing()
    {
        app(Settings::class)->set([
            'notify.summary.emails' => ['founder@example.com'],
            'notify.summary.leaders' => false,
            'notify.summary.projects' => false,
        ]);

        $this->artisan('summaries:daily')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_pending_lists_overdue_and_upcoming_tasks_open_bugs()
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'title' => 'Late one', 'status' => TaskStatus::InProgress, 'due_date' => Clock::today()->subDays(3)]);
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Soon one', 'status' => TaskStatus::Todo, 'due_date' => Clock::today()->addDays(2)]);
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Finished', 'status' => TaskStatus::Done, 'due_date' => Clock::today()->subDays(3)]);
        TestPoint::factory()->create(['project_id' => $this->project->id, 'title' => 'Crash', 'status' => TestPointStatus::Open]);
        TestPoint::factory()->create(['project_id' => $this->project->id, 'title' => 'Fixed', 'status' => TestPointStatus::Closed]);

        $s = app(ProjectSummary::class)->build($this->project, now()->subDay());

        $this->assertSame(['Late one'], array_column($s['pending']['overdue']['rows'], 'title'));
        $this->assertSame(3, $s['pending']['overdue']['rows'][0]['days']);
        $this->assertSame(['Soon one'], array_column($s['pending']['due_soon']['rows'], 'title'));
        $this->assertSame(['Crash'], array_column($s['pending']['bugs']['rows'], 'title'));
        $this->assertSame(33, $s['progress']);
    }

    public function test_changes_are_counted_from_the_last_summary_sent_to_that_address()
    {
        $old = Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Old move', 'status' => TaskStatus::Todo]);
        $old->update(['status' => TaskStatus::InProgress]);

        $this->travel(2)->hours();
        DB::table('summary_deliveries')->insert(['email' => 'olive@example.com', 'last_sent_at' => now()]);
        $this->travel(1)->hours();

        $this->actingAs($this->dev);
        $old->update(['status' => TaskStatus::Done]);
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Brand new', 'status' => TaskStatus::Todo]);
        auth()->logout();

        $this->artisan('summaries:daily');

        $rows = collect($this->sentTo('olive@example.com')->projects[0]['changes']['tasks']['rows'])->keyBy('title');
        $this->assertSame(['In progress', 'Done', 'Dev Person'], [$rows['Old move']['from'], $rows['Old move']['to'], $rows['Old move']['by']]);
        $this->assertTrue($rows['Brand new']['created']);
        $this->assertSame(1, $this->sentTo('olive@example.com')->projects[0]['counts']['completed']);

        // The window moved on: tomorrow's email starts from this run.
        $this->assertSame(now()->toDateTimeString(), Carbon::parse(DB::table('summary_deliveries')->where('email', 'olive@example.com')->value('last_sent_at'))->toDateTimeString());
    }

    public function test_a_failed_send_keeps_the_window_open()
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('535 Authentication Failed'));

        $this->artisan('summaries:daily')->assertSuccessful();

        $this->assertSame(0, DB::table('summary_deliveries')->count());
    }

    public function test_the_email_renders()
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->dev->id, 'title' => 'Late one', 'status' => TaskStatus::Todo, 'due_date' => Clock::today()->subDay()]);
        TestPoint::factory()->create(['project_id' => $this->project->id, 'title' => 'Crash']);

        $mail = new DailySummary([app(ProjectSummary::class)->build($this->project, now()->subDay())], now()->subDay(), false, 'Olive Owner');

        $html = $mail->render();
        $this->assertStringContainsString('Late one', $html);
        $this->assertStringContainsString('1 day late', $html);
        $this->assertStringContainsString('Hi Olive,', $html);
        $this->assertStringContainsString('the last summary ('.now()->subDay()->timezone(app(Settings::class)->get('display.timezone'))->format('j M Y, g:i A').')', $html);
        $mail->assertHasSubject('Daily project summary');
        $this->assertStringContainsString('Daily summary · '.now(app(Settings::class)->get('display.timezone'))->format('j M Y'), $html);
    }

    public function test_the_owner_or_a_lead_adds_people_and_addresses()
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.summary-recipients.store', $this->project), ['user_id' => $this->dev->id])->assertSessionHasNoErrors();
        $this->post(route('projects.summary-recipients.store', $this->project), ['email' => ' Client@Outside.test '])->assertSessionHasNoErrors();
        // An address that belongs to an account is stored as the account.
        $this->post(route('projects.summary-recipients.store', $this->project), ['email' => 'DEV@example.com'])->assertSessionHas('success', 'They already get this project\'s summary.');
        $this->post(route('projects.summary-recipients.store', $this->project), ['user_id' => $this->owner->id])->assertSessionHas('success', 'The owner already gets this project\'s summary.');
        $this->post(route('projects.summary-recipients.store', $this->project), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->post(route('projects.summary-recipients.store', $this->project), [])->assertSessionHasErrors('email');

        $this->assertSame(
            [[$this->dev->id, null], [null, 'client@outside.test']],
            $this->project->summaryRecipients()->orderBy('id')->get()->map(fn ($r) => [$r->user_id, $r->email])->all(),
        );

        $this->get(route('projects.members.index', $this->project))
            ->assertInertia(fn ($page) => $page->has('summary.recipients', 2)->has('summary.candidates', 0));

        $outside = $this->project->summaryRecipients()->whereNotNull('email')->sole();
        $this->delete(route('projects.summary-recipients.destroy', [$this->project, $outside]))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->project->summaryRecipients()->count());
    }

    public function test_a_plain_member_cannot_change_the_list_or_see_it()
    {
        $this->actingAs($this->dev);

        $this->post(route('projects.summary-recipients.store', $this->project), ['email' => 'x@example.com'])->assertForbidden();
        $this->get(route('projects.members.index', $this->project))->assertInertia(fn ($page) => $page->where('summary', null));

        $other = Project::factory()->create();
        $foreign = $other->summaryRecipients()->create(['email' => 'y@example.com']);
        $this->actingAs($this->owner)->delete(route('projects.summary-recipients.destroy', [$this->project, $foreign]))->assertNotFound();
    }

    public function test_leadership_addresses_are_validated_and_the_preview_renders()
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->put(route('admin.config.notifications.update'), [
            'task_assigned' => true, 'task_urgent' => true, 'task_status' => [], 'bug_assigned' => true, 'bug_status' => [],
            'overdue_enabled' => true, 'overdue_time' => '17:00',
            'summary_leaders' => true, 'summary_emails' => ['nope'], 'summary_projects' => true, 'summary_time' => '18:00',
        ])->assertSessionHasErrors('summary_emails.0');

        $this->get(route('admin.config.notifications.summary-preview'))->assertOk()->assertSee('Alpha');
    }

    public function test_every_day_s_summary_threads_into_one_conversation_per_person()
    {
        // Real messages through the array transport, so the headers can be read.
        config(['mail.default' => 'array', 'mail.from.address' => 'noreply@vetrik.in']);
        Mail::swap(new MailManager($this->app));
        app(Settings::class)->set(['notify.summary.emails' => ['founder@example.com'], 'notify.summary.projects' => false]);

        $this->artisan('summaries:daily');
        $this->travel(1)->day();
        $this->artisan('summaries:daily');

        $sent = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->map->getOriginalMessage();
        $this->assertCount(2, $sent);

        [$monday, $tuesday] = $sent->all();
        $root = MailThread::root('daily-summary', 'founder@example.com');
        foreach ([$monday, $tuesday] as $m) {
            $this->assertSame('Daily project summary', $m->getSubject());
            $this->assertSame([$root], $m->getHeaders()->get('References')->getIds());
            $this->assertSame([$root], $m->getHeaders()->get('In-Reply-To')->getIds());
        }
        // Each send is still its own message.
        $ids = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->map->getMessageId();
        $this->assertCount(2, $ids->unique());
        $this->assertNotContains($root, $ids);
        // Someone else's summary is a different conversation.
        $this->assertNotSame($root, MailThread::root('daily-summary', 'cfo@example.com'));
        $this->assertStringEndsWith('@vetrik.in', $root);
    }

    public function test_the_overdue_email_threads_the_same_way()
    {
        config(['mail.default' => 'array']);
        Mail::swap(new MailManager($this->app));
        $row = ['reference' => 'T-1', 'title' => 'Late', 'project' => 'Alpha', 'due' => '1 Oct 2026', 'days' => 2, 'assignee' => null, 'url' => 'http://x'];

        $this->dev->notify(new OverdueTasksDigest(collect([$row])));
        $this->dev->notify(new OverdueTasksDigest(collect([$row, $row])));

        $sent = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->map->getOriginalMessage();
        $this->assertSame(['Your overdue tasks', 'Your overdue tasks'], $sent->map->getSubject()->all());
        $this->assertSame(
            [[MailThread::root('overdue-digest', 'dev@example.com')], [MailThread::root('overdue-digest', 'dev@example.com')]],
            $sent->map(fn ($m) => $m->getHeaders()->get('References')->getIds())->all(),
        );
    }
}
