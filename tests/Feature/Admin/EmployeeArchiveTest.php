<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmployeeArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_turns_sign_in_off_and_moves_them_to_the_archived_list()
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->post(route('admin.employees.archive', $employee))->assertSessionHas('success');

        $employee->refresh();
        $this->assertNotNull($employee->archived_at);
        $this->assertSame($admin->id, $employee->archived_by);
        $this->assertFalse($employee->user->is_active);

        $this->actingAs($admin)->get(route('admin.employees.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('employees.data', fn ($rows) => collect($rows)->doesntContain('id', $employee->id))
                ->where('archivedCount', 1));

        $this->actingAs($admin)->get(route('admin.employees.index', ['archived' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('employees.data.0.id', $employee->id));
    }

    public function test_restoring_brings_them_back_with_sign_in_on()
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $this->actingAs($admin)->post(route('admin.employees.archive', $employee));

        $this->actingAs($admin)->post(route('admin.employees.restore', $employee))->assertRedirect(route('admin.employees.show', $employee));

        $employee->refresh();
        $this->assertNull($employee->archived_at);
        $this->assertTrue($employee->user->is_active);
    }

    public function test_an_archived_person_cannot_be_reactivated_without_restoring()
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $this->actingAs($admin)->post(route('admin.employees.archive', $employee));

        $this->actingAs($admin)->patch(route('admin.employees.status', $employee), ['is_active' => true])->assertSessionHas('error');

        $this->assertFalse($employee->user->fresh()->is_active);
    }

    public function test_someone_with_work_history_cannot_be_deleted()
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        Task::factory()->create(['created_by' => $employee->user_id]);

        $this->actingAs($admin)->delete(route('admin.employees.destroy', $employee))->assertSessionHas('error');

        $this->assertNotNull(User::find($employee->user_id));
    }

    public function test_nobody_archives_themselves_and_employees_cannot_archive()
    {
        $admin = User::factory()->admin()->create();
        $self = Employee::factory()->create(['user_id' => $admin->id]);
        $this->actingAs($admin)->post(route('admin.employees.archive', $self))->assertSessionHas('error');
        $this->assertNull($self->fresh()->archived_at);

        $other = Employee::factory()->create();
        $this->actingAs(User::factory()->create())->post(route('admin.employees.archive', $other))->assertForbidden();
        $this->assertNull($other->fresh()->archived_at);
    }
}
