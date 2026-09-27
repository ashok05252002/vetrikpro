<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TestPointStatus;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Promotion;
use App\Models\Task;
use App\Models\TestPoint;
use App\Models\User;
use App\Notifications\OverdueTasksDigest;
use App\Support\Clock;
use App\Support\ProjectPeople;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** One test per bug found in the 2026-09-27 review, so none comes back. */
class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    // 1 + 13: a mail outage never turns a saved change into an error.
    public function test_a_failing_mail_server_does_not_break_a_status_change()
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $admin = User::factory()->admin()->create();
        $point = TestPoint::factory()->create(['status' => TestPointStatus::InProgress, 'assigned_to' => User::factory()->create()->id]);

        $this->actingAs($admin)
            ->patch(route('testing.points.move', [$point->project_id, $point]), ['status' => 'ready_for_test', 'position' => 0])
            ->assertRedirect();

        $this->assertSame(TestPointStatus::ReadyForTest, $point->fresh()->status);
    }

    // 4: archived people are not offered, and are refused, as assignees.
    public function test_archived_people_cannot_be_given_work()
    {
        $lead = User::factory()->create();
        $leaver = Employee::factory()->create(['archived_at' => now()]);
        $project = Project::factory()->create(['owner_id' => $lead->id]);
        $project->members()->attach([$lead->id, $leaver->user_id]);

        $this->assertFalse(ProjectPeople::assignable($project)->contains('id', $leaver->user_id));

        $this->actingAs($lead)->post(route('tasks.store'), [
            'project_id' => $project->id, 'title' => 'X', 'status' => 'todo', 'priority' => 'low', 'assigned_to' => $leaver->user_id,
        ])->assertSessionHasErrors('assigned_to');

        // A card that already has them can still be saved unchanged.
        $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $leaver->user_id]);
        $this->actingAs($lead)->put(route('tasks.update', $task), [
            'project_id' => $project->id, 'title' => 'Renamed', 'status' => 'todo', 'priority' => 'low', 'assigned_to' => $leaver->user_id,
        ])->assertSessionHasNoErrors();
    }

    // 5: due today is not overdue; yesterday is — the same on every screen.
    public function test_a_task_is_overdue_only_after_its_due_date()
    {
        $today = Task::factory()->create(['status' => TaskStatus::Todo, 'due_date' => Clock::today()]);
        $yesterday = Task::factory()->create(['status' => TaskStatus::Todo, 'due_date' => Clock::today()->subDay()]);

        $this->assertFalse($today->isOverdue());
        $this->assertTrue($yesterday->isOverdue());
        $this->assertSame([$yesterday->id], Task::overdue()->pluck('id')->all());
    }

    // 6 + 10: live projects only; an owner is not told twice about their own task.
    public function test_the_overdue_digest_skips_paused_projects_and_duplicates()
    {
        Notification::fake();
        $owner = User::factory()->create();
        $dev = User::factory()->create();
        $live = Project::factory()->create(['owner_id' => $owner->id, 'status' => ProjectStatus::Active]);
        $paused = Project::factory()->create(['owner_id' => $owner->id, 'status' => ProjectStatus::OnHold]);
        $late = ['status' => TaskStatus::Todo, 'due_date' => Clock::today()->subDays(2)];

        Task::factory()->create(['project_id' => $live->id, 'assigned_to' => $owner->id, ...$late]);
        Task::factory()->create(['project_id' => $live->id, 'assigned_to' => $dev->id, ...$late]);
        Task::factory()->create(['project_id' => $paused->id, 'assigned_to' => $dev->id, ...$late]);

        $this->artisan('tasks:overdue-digest');

        Notification::assertSentTo($dev, OverdueTasksDigest::class, fn ($n) => $n->tasks->count() === 1);
        Notification::assertSentTo($owner, OverdueTasksDigest::class, fn ($n) => $n->scope === OverdueTasksDigest::MINE && $n->tasks->count() === 1);
        Notification::assertSentTo($owner, OverdueTasksDigest::class, fn ($n) => $n->scope === OverdueTasksDigest::OWNED && $n->tasks->count() === 1);
    }

    // 7: issuing by hand is not emailing.
    public function test_an_invoice_issued_without_email_has_no_sent_date()
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::create(['name' => 'Acme']);
        $this->actingAs($admin)->post(route('accounts.invoices.store'), [
            'customer_id' => $customer->id, 'issue_date' => '2026-09-27',
            'items' => [['description' => 'Audit', 'quantity' => 1, 'unit' => 'nos', 'unit_price' => 100, 'discounted_price' => 100, 'gst_rate' => 18]],
        ]);
        $invoice = Invoice::sole();

        $this->actingAs($admin)->post(route('accounts.invoices.mark-sent', $invoice));

        $invoice->refresh();
        $this->assertNotNull($invoice->issued_at);
        $this->assertNull($invoice->sent_at);
        $this->assertNull($invoice->sent_to);
    }

    // 8: the dashboard counts current staff.
    public function test_the_dashboard_employee_count_leaves_out_archived_people()
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->count(2)->create();
        Employee::factory()->create(['archived_at' => now()]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.employees', Employee::current()->count()));
    }

    // 9: a bad GSTIN is reported on the GSTIN, in words.
    public function test_gstin_mistakes_are_explained_on_the_gstin()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('accounts.customers.store'), ['name' => 'X', 'gstin' => '99ABCDE1234F1Z5'])
            ->assertSessionHasErrors(['gstin'])
            ->assertSessionDoesntHaveErrors(['state_code']);

        $this->actingAs($admin)->post(route('accounts.customers.store'), ['name' => 'X', 'gstin' => '33ABCDE1234F1Z5', 'state_code' => '29'])
            ->assertSessionHasErrors(['gstin']);
    }

    // 11: someone who recorded a promotion is archived, not deleted.
    public function test_recording_a_promotion_counts_as_history()
    {
        $hr = User::factory()->hr()->create();
        $this->assertFalse($hr->hasWorkHistory());

        Promotion::create(['employee_id' => Employee::factory()->create()->id, 'to_designation_name' => 'Lead', 'to_salary' => 1, 'effective_date' => '2026-10-01', 'created_by' => $hr->id]);

        $this->assertTrue($hr->hasWorkHistory());
    }

    public function test_documents_people_upload_about_themselves_are_not_history()
    {
        $employee = Employee::factory()->create();
        \DB::table('employee_documents')->insert([
            'document_type_id' => DocumentType::value('id'),
            'employee_id' => $employee->id, 'uploaded_by' => $employee->user_id, 'title' => 'PAN', 'original_name' => 'pan.pdf',
            'file_path' => 'x', 'mime_type' => 'application/pdf', 'size' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($employee->user->hasWorkHistory());
    }

    // 12: "today" is the organisation's date, not the server's UTC date.
    public function test_today_follows_the_display_timezone()
    {
        app(Settings::class)->set(['display.timezone' => 'Asia/Kolkata']);
        Carbon::setTestNow(Carbon::parse('2026-09-27 20:00:00', 'UTC')); // 01:30 on the 28th in India

        $this->assertSame('2026-09-28', Clock::today()->toDateString());

        Carbon::setTestNow();
    }
}
