<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_an_employee_profile_for_a_user()
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();
        $designation = Designation::factory()->create(['department_id' => $department->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.employees.store'), [
                'user_id' => $user->id,
                'employee_code' => 'EMP-0042',
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'phone' => '+91 98765 43210',
                'date_of_birth' => '1995-04-01',
                'gender' => 'female',
                'date_of_joining' => '2024-01-15',
                'employment_type' => 'full_time',
                'salary' => '55000.00',
                'address' => '12 Example Street',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::where('employee_code', 'EMP-0042')->first();

        $this->assertNotNull($employee);
        $this->assertSame($user->id, $employee->user_id);
        $this->assertSame($department->id, $employee->department_id);
    }

    public function test_a_user_cannot_have_two_employee_profiles()
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.employees.store'), [
                'user_id' => $employee->user_id,
                'employee_code' => 'EMP-9999',
                'employment_type' => 'full_time',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_the_create_form_only_offers_users_without_a_profile()
    {
        $taken = Employee::factory()->create();
        $free = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.employees.create'))
            ->assertInertia(function (AssertableInertia $page) use ($free, $taken) {
                $ids = collect($page->toArray()['props']['users'])->pluck('id');

                $page->component('admin/employees/create');

                $this->assertContains($free->id, $ids->all());
                $this->assertNotContains($taken->user_id, $ids->all());
            });
    }

    public function test_the_edit_form_still_offers_the_currently_attached_user()
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.employees.edit', $employee))
            ->assertInertia(function (AssertableInertia $page) use ($employee) {
                $ids = collect($page->toArray()['props']['users'])->pluck('id');

                $page->component('admin/employees/edit');

                $this->assertContains($employee->user_id, $ids->all());
            });
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
