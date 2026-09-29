<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InternTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Kavya S', 'email' => 'kavya@company.test', 'employee_code' => 'INT-001', 'status' => 'active', 'send_invite' => false, ...$overrides];
    }

    public function test_an_intern_with_a_stipend_is_added_as_an_intern_employee()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.interns.store'), $this->payload(['has_stipend' => true, 'stipend' => 12000]))
            ->assertRedirect(route('admin.interns.index'));

        $intern = Employee::where('employee_code', 'INT-001')->first();
        $this->assertTrue($intern->isIntern());
        $this->assertTrue($intern->has_stipend);
        $this->assertEquals(12000, $intern->stipend);
        $this->assertNull($intern->salary);
        $this->assertSame(Role::EMPLOYEE, $intern->user->role->slug);
    }

    public function test_a_stipend_needs_an_amount_and_none_clears_it()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.interns.store'), $this->payload(['has_stipend' => true]))->assertSessionHasErrors('stipend');

        $this->actingAs($admin)->post(route('admin.interns.store'), $this->payload(['has_stipend' => false, 'stipend' => 5000]));
        $this->assertNull(Employee::where('employee_code', 'INT-001')->value('stipend'));
    }

    public function test_interns_are_listed_apart_from_staff()
    {
        $admin = User::factory()->admin()->create();
        $intern = Employee::factory()->create(['employment_type' => 'intern']);
        $staff = Employee::factory()->create();

        $this->actingAs($admin)->get(route('admin.interns.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('interns.data', 1)->where('interns.data.0.id', $intern->id));

        $this->actingAs($admin)->get(route('admin.employees.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('employees.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($intern->id) && collect($rows)->pluck('id')->contains($staff->id)));
    }

    public function test_the_interns_permission_is_its_own_and_can_be_granted_to_one_person()
    {
        $coordinator = User::factory()->create();

        $this->actingAs($coordinator)->get(route('admin.interns.index'))->assertForbidden();

        $coordinator->syncPermissionOverrides(['interns.view' => true, 'interns.create' => true]);

        $this->actingAs($coordinator->fresh())->get(route('admin.interns.index'))->assertOk();
        $this->actingAs($coordinator->fresh())->get(route('admin.employees.index'))->assertForbidden();
    }
}
