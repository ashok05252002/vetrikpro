<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** A new employee, which is also how a login is created now. */
    private function employeePayload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@company.com',
            'role_id' => Role::bySlug(Role::EMPLOYEE)->id,
            'employee_code' => 'EMP-0900',
            'employment_type' => 'full_time',
            'status' => 'active',
            'send_invite' => false,
            ...$overrides,
        ];
    }

    private function profileOf(User $user): Employee
    {
        return $user->employee ?? Employee::factory()->create(['user_id' => $user->id]);
    }

    private function customRole(array $permissions, string $name = 'Coordinator'): Role
    {
        $role = Role::create(['name' => $name, 'slug' => str($name)->slug()->value()]);
        $role->syncPermissions($permissions);

        return $role;
    }

    // Resolution

    public function test_the_system_roles_exist_without_seeding()
    {
        $this->assertTrue(Role::bySlug(Role::ADMIN)->is_super);
        $this->assertSame(Permissions::all(), User::factory()->admin()->create()->permissions());
        $this->assertSame([], User::factory()->create()->permissions());
        $this->assertNotContains('settings.edit', User::factory()->hr()->create()->permissions());
    }

    public function test_an_override_can_grant_a_permission_the_role_lacks()
    {
        $user = User::factory()->create();
        $user->syncPermissionOverrides(['employees.view' => true]);

        $this->actingAs($user)->get(route('admin.employees.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.employees.create'))->assertForbidden();
    }

    public function test_an_override_can_revoke_a_permission_the_role_has()
    {
        $hr = User::factory()->hr()->create();
        // Revoking a module means revoking its actions: holding edit implies view.
        $hr->syncPermissionOverrides(['departments.view' => false, 'departments.create' => false, 'departments.edit' => false, 'departments.delete' => false]);

        $this->actingAs($hr)->get(route('admin.departments.index'))->assertForbidden();
        $this->actingAs($hr)->get(route('admin.employees.index'))->assertOk();
    }

    public function test_a_super_role_ignores_revoking_overrides()
    {
        $admin = User::factory()->admin()->create();
        $admin->syncPermissionOverrides(['settings.edit' => false]);

        $this->assertTrue($admin->can('settings.edit'));
    }

    public function test_unknown_permission_keys_never_resolve()
    {
        $user = User::factory()->create();
        $user->syncPermissionOverrides(['nuke.everything' => true]);

        $this->assertSame([], $user->permissions());
        $this->assertFalse($user->can('nuke.everything'));
    }

    public function test_a_custom_role_opens_exactly_its_sections()
    {
        $user = User::factory()->create(['role_id' => $this->customRole(['departments.view'])->id]);

        $this->actingAs($user)->get(route('admin.departments.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.employees.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_permissions_are_shared_with_the_frontend()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($keys) => collect($keys)->contains('employees.view') && ! collect($keys)->contains('settings.edit'))
                ->where('auth.user.role.slug', Role::HR));
    }

    // Escalation

    public function test_hr_cannot_create_an_administrator()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.employees.store'), $this->employeePayload(['role_id' => Role::bySlug(Role::ADMIN)->id]))
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'jane@company.com']);
    }

    public function test_hr_cannot_assign_a_custom_role_carrying_access_they_lack()
    {
        $role = $this->customRole(['settings.edit']);

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.employees.store'), $this->employeePayload(['role_id' => $role->id]))
            ->assertSessionHasErrors('role_id');
    }

    public function test_hr_is_only_offered_roles_they_may_assign()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.employees.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => ! collect($roles)->contains('label', 'Administrator') && collect($roles)->contains('label', 'Employee')));
    }

    public function test_hr_cannot_change_anyones_access()
    {
        $target = Employee::factory()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->put(route('admin.employees.access.update', $target), ['role_id' => $target->user->role_id, 'overrides' => ['employees.view' => 'allow']])
            ->assertForbidden();
    }

    public function test_hr_cannot_edit_deactivate_or_delete_an_administrator()
    {
        $hr = User::factory()->hr()->create();
        $admin = User::factory()->admin()->create();
        $profile = $this->profileOf($admin);

        $this->actingAs($hr)->get(route('admin.employees.edit', $profile))->assertForbidden();
        // Changing the admin's email would let HR take over the account through a reset.
        $this->actingAs($hr)
            ->put(route('admin.employees.update', $profile), [...$this->employeePayload(['email' => 'hr-owned@company.com', 'employee_code' => $profile->employee_code]), 'role_id' => null, 'send_invite' => null])
            ->assertForbidden();
        $this->actingAs($hr)->post(route('admin.employees.password-reset', $profile))->assertForbidden();
        $this->actingAs($hr)->delete(route('admin.employees.destroy', $profile))->assertForbidden();

        $this->assertSame($admin->email, $admin->fresh()->email);
    }

    public function test_an_administrator_cannot_demote_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.employees.access.update', $this->profileOf($admin)), ['role_id' => Role::bySlug(Role::EMPLOYEE)->id])
            ->assertSessionHasErrors('role_id');

        $this->assertTrue($admin->fresh()->isSuper());
    }

    // Per-person overrides, set on the Access tab

    public function test_an_unknown_override_key_is_rejected()
    {
        $target = Employee::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.employees.access.update', $target), ['role_id' => $target->user->role_id, 'overrides' => ['nuke.everything' => 'allow']])
            ->assertSessionHasErrors('overrides');
    }

    public function test_hr_editing_an_employee_leaves_their_overrides_alone()
    {
        $employee = Employee::factory()->create();
        $employee->user->syncPermissionOverrides(['projects.view' => true]);

        $this->actingAs(User::factory()->hr()->create())
            ->put(route('admin.employees.update', $employee), [
                ...$this->employeePayload(['email' => $employee->user->email, 'name' => 'Renamed', 'employee_code' => $employee->employee_code]),
                'role_id' => null,
                'send_invite' => null,
            ])
            ->assertRedirect(route('admin.employees.show', $employee));

        $this->assertSame(['projects.view'], $employee->user->fresh()->permissions());
    }

    // Role management

    public function test_only_role_managers_reach_the_role_screens()
    {
        $this->actingAs(User::factory()->hr()->create())->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.roles.index'))->assertOk();
    }

    public function test_an_admin_can_create_a_role_with_permissions()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.roles.store'), [
                'name' => 'Team Lead',
                'description' => 'Runs a team.',
                'permissions' => ['projects.edit', 'employees.view'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::bySlug('team-lead');

        // Stored in registry order, whatever order they were sent in, and
        // editing projects brings seeing them along.
        $this->assertSame(['employees.view', 'projects.view', 'projects.edit'], $role->permissionKeys());
    }

    public function test_a_role_cannot_carry_an_unknown_permission()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.roles.store'), ['name' => 'Odd', 'permissions' => ['nuke.everything']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_renaming_a_role_keeps_its_slug()
    {
        $role = Role::bySlug(Role::HR);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.roles.update', $role), ['name' => 'People Team', 'permissions' => ['employees.edit']]);

        $role->refresh();
        $this->assertSame('People Team', $role->name);
        $this->assertSame(Role::HR, $role->slug);
        $this->assertSame(['employees.view', 'employees.edit'], $role->permissionKeys());
    }

    public function test_system_roles_and_roles_in_use_cannot_be_deleted()
    {
        $admin = User::factory()->admin()->create();
        $inUse = $this->customRole([], 'Busy');
        User::factory()->create(['role_id' => $inUse->id]);
        $empty = $this->customRole([], 'Spare');

        $this->actingAs($admin)->delete(route('admin.roles.destroy', Role::bySlug(Role::EMPLOYEE)))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $inUse))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $empty))->assertSessionHas('success');

        $this->assertNotNull(Role::find($inUse->id));
        $this->assertNull(Role::find($empty->id));
    }
}
