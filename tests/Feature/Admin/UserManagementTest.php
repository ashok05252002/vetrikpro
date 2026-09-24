<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_plain_employees_cannot_reach_the_admin_area()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admins_can_list_users()
    {
        User::factory()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_hr_managers_can_reach_the_admin_area()
    {
        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_an_admin_can_create_a_user()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Jane Doe',
                'email' => 'jane@company.com',
                'role' => 'hr',
                'is_active' => true,
                'password' => 'Str0ng-Passw0rd',
                'password_confirmation' => 'Str0ng-Passw0rd',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'jane@company.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Hr, $user->role);
        $this->assertTrue($user->is_active);
        // Stored hashed, and the credentials actually work.
        $this->assertTrue(Hash::check('Str0ng-Passw0rd', $user->password));
    }

    public function test_a_created_user_can_log_in()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Jane Doe',
                'email' => 'jane@company.com',
                'role' => 'employee',
                'is_active' => true,
                'password' => 'Str0ng-Passw0rd',
                'password_confirmation' => 'Str0ng-Passw0rd',
            ]);

        auth()->logout();

        $this->post(route('login'), [
            'email' => 'jane@company.com',
            'password' => 'Str0ng-Passw0rd',
        ]);

        $this->assertAuthenticated();
    }

    public function test_duplicate_emails_are_rejected()
    {
        User::factory()->create(['email' => 'taken@company.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Someone',
                'email' => 'taken@company.com',
                'role' => 'employee',
                'is_active' => true,
                'password' => 'Str0ng-Passw0rd',
                'password_confirmation' => 'Str0ng-Passw0rd',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_an_invalid_role_is_rejected()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Someone',
                'email' => 'someone@company.com',
                'role' => 'superuser',
                'is_active' => true,
                'password' => 'Str0ng-Passw0rd',
                'password_confirmation' => 'Str0ng-Passw0rd',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_updating_without_a_password_keeps_the_existing_one()
    {
        $user = User::factory()->create();
        $original = $user->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $user), [
                'name' => 'Renamed',
                'email' => $user->email,
                'role' => 'employee',
                'is_active' => false,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertSame($original, $user->password);
    }

    public function test_an_admin_cannot_delete_their_own_account()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_an_admin_can_delete_another_user()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $user));

        $this->assertNull(User::find($user->id));
    }
}
