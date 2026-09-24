<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Designation;
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
}
