<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_account_cannot_sign_in()
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact your administrator.']);

        $this->assertGuest();
    }

    public function test_a_wrong_password_still_says_wrong_password()
    {
        // Deactivation is only revealed to someone who knew the password.
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_deactivation_ends_a_live_session_on_the_next_request()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_an_admin_deactivates_and_reactivates_someone()
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $user = $employee->user;

        $this->actingAs($admin)->patch(route('admin.employees.status', $employee), ['is_active' => false])->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertSame($admin->id, $user->deactivated_by);
        $this->assertNotNull($user->deactivated_at);

        $this->actingAs($admin)->patch(route('admin.employees.status', $employee), ['is_active' => true]);

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->deactivated_at);
        $this->assertNull($user->deactivated_by);
    }

    public function test_nobody_deactivates_themselves()
    {
        $admin = User::factory()->admin()->create();
        $mine = Employee::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)->patch(route('admin.employees.status', $mine), ['is_active' => false])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_hr_cannot_deactivate_an_administrator()
    {
        $admin = User::factory()->admin()->create();
        $adminProfile = Employee::factory()->create(['user_id' => $admin->id]);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('admin.employees.status', $adminProfile), ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_toggling_needs_employees_edit()
    {
        $viewer = User::factory()->create();
        $viewer->syncPermissionOverrides(['employees.view' => true]);

        $this->actingAs($viewer)
            ->patch(route('admin.employees.status', Employee::factory()->create()), ['is_active' => false])
            ->assertForbidden();
    }
}
