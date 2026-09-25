<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@company.com',
            'employee_code' => 'EMP-0042',
            'employment_type' => 'full_time',
            'status' => 'active',
            'send_invite' => false,
            ...$overrides,
        ];
    }

    public function test_adding_an_employee_creates_their_login_and_hr_record_together()
    {
        $department = Department::factory()->create();
        $designation = Designation::factory()->create(['department_id' => $department->id]);
        $hrRole = Role::bySlug(Role::HR);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.employees.store'), $this->payload([
                'role_id' => $hrRole->id,
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'salary' => '55000.00',
            ]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'jane@company.com')->firstOrFail();
        $employee = $user->employee;

        $this->assertSame('Jane Doe', $user->name);
        $this->assertSame($hrRole->id, $user->role_id);
        $this->assertSame('EMP-0042', $employee->employee_code);
        $this->assertSame($department->id, $employee->department_id);
    }

    public function test_without_a_role_a_new_employee_gets_the_employee_role()
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.employees.store'), $this->payload());

        $this->assertSame(Role::EMPLOYEE, User::where('email', 'jane@company.com')->first()->role->slug);
    }

    public function test_the_email_must_not_belong_to_anyone_else()
    {
        User::factory()->create(['email' => 'jane@company.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertSessionHasErrors('email');
    }

    public function test_editing_changes_the_login_name_and_email_too()
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.employees.update', $employee), $this->payload([
                'name' => 'Renamed Person', 'email' => 'renamed@company.com', 'employee_code' => $employee->employee_code, 'send_invite' => null,
            ]))
            ->assertRedirect(route('admin.employees.show', $employee));

        $this->assertSame('Renamed Person', $employee->user->fresh()->name);
        $this->assertSame('renamed@company.com', $employee->user->fresh()->email);
    }

    public function test_the_role_cannot_be_changed_through_the_edit_form()
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.employees.update', $employee), [
                ...$this->payload(['email' => $employee->user->email, 'employee_code' => $employee->employee_code]),
                'send_invite' => null,
                'role_id' => Role::bySlug(Role::ADMIN)->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertFalse($employee->user->fresh()->isSuper());
    }

    public function test_deleting_an_employee_removes_their_login()
    {
        $employee = Employee::factory()->create();
        $userId = $employee->user_id;

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertNull(User::find($userId));
        $this->assertNull(Employee::find($employee->id));
    }

    public function test_nobody_deletes_themselves()
    {
        $admin = User::factory()->admin()->create();
        $mine = Employee::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)->delete(route('admin.employees.destroy', $mine))->assertSessionHas('error');
        $this->assertNotNull($admin->fresh());
    }

    public function test_a_password_reset_link_is_emailed_rather_than_a_password_typed()
    {
        Notification::fake();
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.employees.password-reset', $employee))
            ->assertSessionHas('success');

        Notification::assertSentTo($employee->user, ResetPassword::class);
    }

    public function test_old_users_links_land_on_employees()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/users')
            ->assertRedirect('/admin/employees');
    }

    public function test_every_login_has_an_employee_record_after_the_merge()
    {
        // The seeded HR manager had no profile before; the merge migration added one.
        $this->seed();

        $this->assertSame(0, User::doesntHave('employee')->count());
    }

    public function test_employee_codes_increment()
    {
        Employee::factory()->create(['employee_code' => 'EMP-0007']);

        $this->assertSame('EMP-0008', Employee::nextCode());
    }

    public function test_the_first_employee_code_is_emp_0001()
    {
        $this->assertSame('EMP-0001', Employee::nextCode());
    }

    public function test_deleting_a_user_cascades_to_the_employee_profile()
    {
        $employee = Employee::factory()->create();

        $employee->user->delete();

        $this->assertNull(Employee::find($employee->id));
    }

    public function test_deleting_a_department_leaves_employees_intact()
    {
        $employee = Employee::factory()->create();

        $employee->department->delete();

        $employee->refresh();

        $this->assertNotNull($employee);
        $this->assertNull($employee->department_id);
    }

    public function test_employees_can_be_searched_by_name()
    {
        $match = Employee::factory()->create();
        $match->user->update(['name' => 'Distinctive Name']);
        Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.employees.index', ['search' => 'Distinctive']))
            ->assertInertia(function (AssertableInertia $page) use ($match) {
                $data = $page->toArray()['props']['employees']['data'];

                $this->assertCount(1, $data);
                $this->assertSame($match->id, $data[0]['id']);
            });
    }
}
