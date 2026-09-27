<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_department()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.departments.store'), [
                'name' => 'Engineering',
                'code' => 'ENG',
                'description' => 'Builds the product',
            ])
            ->assertRedirect(route('admin.departments.index'));

        $this->assertTrue(Department::where('code', 'ENG')->exists());
    }

    public function test_department_names_are_unique()
    {
        Department::factory()->create(['name' => 'Engineering']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.departments.store'), ['name' => 'Engineering', 'code' => 'ENG2'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_department_can_be_renamed_to_its_own_name()
    {
        $department = Department::factory()->create(['name' => 'Engineering', 'code' => 'ENG']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.departments.update', $department), [
                'name' => 'Engineering',
                'code' => 'ENG',
                'description' => 'Updated blurb',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated blurb', $department->fresh()->description);
    }

    public function test_the_same_designation_name_may_exist_in_different_departments()
    {
        $a = Department::factory()->create();
        $b = Department::factory()->create();

        Designation::factory()->create(['department_id' => $a->id, 'name' => 'Manager']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.designations.store'), ['department_id' => $b->id, 'name' => 'Manager'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Designation::where('name', 'Manager')->count());
    }

    public function test_a_designation_name_cannot_repeat_within_one_department()
    {
        $department = Department::factory()->create();
        Designation::factory()->create(['department_id' => $department->id, 'name' => 'Manager']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.designations.store'), ['department_id' => $department->id, 'name' => 'Manager'])
            ->assertSessionHasErrors('name');
    }

    public function test_employees_cannot_manage_master_data()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.departments.store'), ['name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertFalse(Department::where('name', 'Sneaky')->exists());
    }

    public function test_an_unused_department_can_be_deleted()
    {
        $department = Department::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.departments.destroy', $department))
            ->assertRedirect(route('admin.departments.index'));

        $this->assertNull(Department::find($department->id));
    }

    public function test_a_department_in_use_cannot_be_deleted_only_switched_off()
    {
        $department = Department::factory()->create();
        Employee::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.departments.destroy', $department))
            ->assertSessionHas('error');
        $this->assertNotNull($department->fresh());

        $this->actingAs($admin)
            ->patch(route('admin.departments.active', $department), ['is_active' => false])
            ->assertSessionHas('success');
        $this->assertFalse($department->fresh()->is_active);
    }

    public function test_a_designation_held_by_someone_cannot_be_deleted()
    {
        $designation = Designation::factory()->create();
        Employee::factory()->create(['designation_id' => $designation->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.designations.destroy', $designation))
            ->assertSessionHas('error');

        $this->assertNotNull($designation->fresh());
    }

    public function test_an_inactive_designation_is_refused_for_new_holders_but_kept_by_existing_ones()
    {
        $designation = Designation::factory()->create(['is_active' => false]);
        $holder = Employee::factory()->create(['designation_id' => $designation->id]);
        $other = Employee::factory()->create();
        $admin = User::factory()->admin()->create();

        $payload = fn (Employee $e) => [
            'name' => $e->user->name, 'email' => $e->user->email, 'employee_code' => $e->employee_code,
            'employment_type' => 'full_time', 'status' => 'active', 'designation_id' => $designation->id,
        ];

        $this->actingAs($admin)->put(route('admin.employees.update', $holder), $payload($holder))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.employees.update', $other), $payload($other))->assertSessionHasErrors('designation_id');
    }

    public function test_switching_master_data_off_needs_edit_permission()
    {
        $department = Department::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.departments.active', $department), ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($department->fresh()->is_active);
    }
}
