<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Production seed: a single administrator and the organisation settings.
 * Roles, permissions and document types come from the migrations; everything
 * else (departments, staff, projects) is entered through the app.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@vetrik.in'],
            [
                'name' => 'Administrator',
                'password' => 'Vetrik@kirteV',
                'role_id' => Role::bySlug(Role::ADMIN)->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // Every login is an employee, the admin included.
        Employee::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'employee_code' => Employee::nextCode(),
                'date_of_joining' => now()->toDateString(),
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
        );

        // Organisation settings. Only written if nothing is stored yet, so a
        // reseed never overwrites a name someone has actually set.
        if (DB::table('settings')->doesntExist()) {
            app(Settings::class)->set([
                'company.name' => 'Vetrik Private Limited',
                'display.timezone' => 'Asia/Kolkata',
                'display.date_format' => 'dmy',
                'display.currency' => 'INR',
            ]);
        }
    }
}
