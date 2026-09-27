<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Models\User;
use App\Notifications\PromotionAnnounced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(EmployeeDocument::DISK);
        Notification::fake();
    }

    private function engineer(): Employee
    {
        $engineering = Department::factory()->create(['name' => 'Engineering']);

        return Employee::factory()->create([
            'department_id' => $engineering->id,
            'designation_id' => Designation::factory()->create(['name' => 'Software Engineer', 'department_id' => $engineering->id])->id,
            'salary' => 50000,
        ]);
    }

    public function test_a_promotion_updates_the_employee_keeps_history_and_emails_the_letter()
    {
        $employee = $this->engineer();
        $senior = Designation::factory()->create(['name' => 'Senior Engineer', 'department_id' => $employee->department_id]);
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $senior->id,
            'to_salary' => 57500,
            'effective_date' => '2026-10-01',
            'note' => 'Annual appraisal',
            'send_email' => true,
        ])->assertSessionHas('success');

        $employee->refresh();
        $this->assertSame($senior->id, $employee->designation_id);
        $this->assertEquals(57500, $employee->salary);

        $promotion = Promotion::sole();
        $this->assertSame('Software Engineer', $promotion->from_designation_name);
        $this->assertSame('Senior Engineer', $promotion->to_designation_name);
        $this->assertEquals(50000, $promotion->from_salary);
        $this->assertSame(15.0, $promotion->incrementPercent());
        $this->assertSame($hr->id, $promotion->created_by);
        $this->assertNotNull($promotion->emailed_at);
        Storage::disk(EmployeeDocument::DISK)->assertExists($promotion->letter_path);

        Notification::assertSentTo($employee->user, PromotionAnnounced::class, function (PromotionAnnounced $n) use ($employee) {
            $mail = $n->toMail($employee->user);

            return str_contains($mail->subject, 'Senior Engineer') && count($mail->rawAttachments) === 1;
        });
    }

    public function test_a_salary_only_revision_keeps_the_designation()
    {
        $employee = $this->engineer();
        $designation = $employee->designation_id;

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $designation,
            'to_salary' => 55000,
            'effective_date' => '2026-10-01',
            'send_email' => false,
        ])->assertSessionHas('success');

        $this->assertSame($designation, $employee->fresh()->designation_id);
        $this->assertFalse(Promotion::sole()->isDesignationChange());
        $this->assertNull(Promotion::sole()->emailed_at);
        Notification::assertNothingSent();
    }

    public function test_a_change_that_changes_nothing_is_refused()
    {
        $employee = $this->engineer();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $employee->designation_id,
            'to_salary' => 50000,
            'effective_date' => '2026-10-01',
        ])->assertSessionHasErrors('to_salary');

        $this->assertSame(0, Promotion::count());
    }

    public function test_promoting_needs_the_promote_permission()
    {
        $employee = $this->engineer();

        $this->actingAs(User::factory()->create())->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $employee->designation_id,
            'to_salary' => 90000,
            'effective_date' => '2026-10-01',
        ])->assertForbidden();

        $this->assertEquals(50000, $employee->fresh()->salary);
    }

    public function test_a_designation_in_promotion_history_cannot_be_deleted()
    {
        $employee = $this->engineer();
        $old = Designation::find($employee->designation_id);
        $senior = Designation::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $senior->id, 'to_salary' => 60000, 'effective_date' => '2026-10-01', 'send_email' => false,
        ]);

        // Nobody holds the old title now, but the history names it.
        $this->actingAs($admin)->delete(route('admin.designations.destroy', $old))->assertSessionHas('error');
        $this->assertNotNull($old->fresh());
    }

    public function test_the_letter_downloads_as_a_pdf()
    {
        $employee = $this->engineer();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.employees.promotions.store', $employee), [
            'to_designation_id' => $employee->designation_id, 'to_salary' => 52000, 'effective_date' => '2026-10-01', 'send_email' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.employees.promotions.letter', [$employee, Promotion::sole()]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_nobody_promotes_themselves()
    {
        $admin = User::factory()->admin()->create();
        $self = Employee::factory()->create(['user_id' => $admin->id, 'salary' => 50000]);

        $this->actingAs($admin)->post(route('admin.employees.promotions.store', $self), [
            'to_designation_id' => Designation::factory()->create()->id, 'to_salary' => 99000, 'effective_date' => '2026-10-01',
        ])->assertForbidden();

        $this->assertEquals(50000, $self->fresh()->salary);
    }
}
