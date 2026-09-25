<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function userPayload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@company.com',
            'role_id' => Role::bySlug(Role::EMPLOYEE)->id,
            'is_active' => true,
            'password' => 'Str0ng-Passw0rd',
            'password_confirmation' => 'Str0ng-Passw0rd',
            ...$overrides,
        ];
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
        $hr->syncPermissionOverrides(['users.view' => false, 'users.create' => false, 'users.edit' => false, 'users.delete' => false]);

        $this->actingAs($hr)->get(route('admin.users.index'))->assertForbidden();
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
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_permissions_are_shared_with_the_frontend()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($keys) => collect($keys)->contains('users.view') && ! collect($keys)->contains('settings.edit'))
                ->where('auth.user.role.slug', Role::HR));
    }

    // Escalation

    public function test_hr_cannot_create_an_administrator()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.users.store'), $this->userPayload(['role_id' => Role::bySlug(Role::ADMIN)->id]))
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'jane@company.com']);
    }

    public function test_hr_cannot_assign_a_custom_role_carrying_access_they_lack()
    {
        $role = $this->customRole(['settings.edit']);

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.users.store'), $this->userPayload(['role_id' => $role->id]))
            ->assertSessionHasErrors('role_id');
    }

    public function test_hr_is_only_offered_roles_they_may_assign()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.users.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => ! collect($roles)->contains('name', 'Administrator') && collect($roles)->contains('name', 'Employee'))
                ->where('canManageAccess', false));
    }

    public function test_hr_cannot_set_per_user_overrides()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->post(route('admin.users.store'), $this->userPayload(['overrides' => ['employees.view' => 'allow']]))
            ->assertSessionHasErrors('overrides');
    }

    public function test_hr_cannot_edit_or_delete_an_administrator()
    {
        $hr = User::factory()->hr()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($hr)->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->actingAs($hr)
            ->put(route('admin.users.update', $admin), $this->userPayload(['email' => $admin->email, 'password' => 'Hijack3d!Pass', 'password_confirmation' => 'Hijack3d!Pass']))
            ->assertForbidden();
        $this->actingAs($hr)->delete(route('admin.users.destroy', $admin))->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_an_administrator_cannot_demote_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->userPayload(['email' => $admin->email, 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('role_id');

        $this->assertTrue($admin->fresh()->isSuper());
    }

    // Overrides through the user form

    public function test_an_admin_can_set_overrides_while_creating_a_user()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->userPayload(['overrides' => ['employees.view' => 'allow', 'projects.view' => 'allow']]))
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'jane@company.com')->first();

        $this->assertSame(['employees.view', 'projects.view'], $user->permissions());
    }

    public function test_an_unknown_override_key_is_rejected()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->userPayload(['overrides' => ['nuke.everything' => 'allow']]))
            ->assertSessionHasErrors('overrides');
    }

    public function test_hr_editing_a_user_leaves_their_overrides_alone()
    {
        $user = User::factory()->create();
        $user->syncPermissionOverrides(['employees.view' => true]);

        $this->actingAs(User::factory()->hr()->create())
            ->put(route('admin.users.update', $user), $this->userPayload(['email' => $user->email, 'name' => 'Renamed', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(['employees.view'], $user->fresh()->permissions());
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
            ->put(route('admin.roles.update', $role), ['name' => 'People Team', 'permissions' => ['users.edit']]);

        $role->refresh();
        $this->assertSame('People Team', $role->name);
        $this->assertSame(Role::HR, $role->slug);
        $this->assertSame(['users.view', 'users.edit'], $role->permissionKeys());
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
