<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@hrms.test'],
            [
                'name' => 'System Administrator',
                'password' => 'password',
                'role_id' => Role::bySlug(Role::ADMIN)->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $departments = collect([
            ['name' => 'Engineering', 'code' => 'ENG'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Sales', 'code' => 'SALES'],
            ['name' => 'Finance', 'code' => 'FIN'],
        ])->mapWithKeys(fn (array $row) => [
            $row['code'] => Department::updateOrCreate(['name' => $row['name']], $row),
        ]);

        $designations = [
            'ENG' => ['Software Engineer', 'Senior Software Engineer', 'Engineering Manager'],
            'HR' => ['HR Executive', 'HR Manager'],
            'SALES' => ['Sales Executive', 'Sales Manager'],
            'FIN' => ['Accountant', 'Finance Manager'],
        ];

        foreach ($designations as $code => $names) {
            foreach ($names as $name) {
                Designation::updateOrCreate(
                    ['department_id' => $departments[$code]->id, 'name' => $name],
                    [],
                );
            }
        }

        // An HR manager, plus a sample employee profile for the admin.
        User::updateOrCreate(
            ['email' => 'hr@hrms.test'],
            [
                'name' => 'Priya HR',
                'password' => 'password',
                'role_id' => Role::bySlug(Role::HR)->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // A handful of staff accounts so tasks have somewhere to land.
        $staff = [
            ['Arun Kumar', 'arun@hrms.test', 'ENG'],
            ['Meera Nair', 'meera@hrms.test', 'ENG'],
            ['Rahul Das', 'rahul@hrms.test', 'SALES'],
            ['Sneha Iyer', 'sneha@hrms.test', 'FIN'],
        ];

        foreach ($staff as $index => [$name, $email, $deptCode]) {
            $member = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'role_id' => Role::bySlug(Role::EMPLOYEE)->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            Employee::updateOrCreate(
                ['user_id' => $member->id],
                [
                    'employee_code' => 'EMP-'.str_pad((string) ($index + 2), 4, '0', STR_PAD_LEFT),
                    'department_id' => $departments[$deptCode]->id,
                    'date_of_joining' => now()->subMonths(6 + $index)->toDateString(),
                    'employment_type' => 'full_time',
                    'status' => 'active',
                ],
            );
        }

        Employee::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'employee_code' => 'EMP-0001',
                'department_id' => $departments['ENG']->id,
                'designation_id' => Designation::where('name', 'Engineering Manager')->value('id'),
                'date_of_joining' => now()->subYears(2)->toDateString(),
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
        );

        // Organisation settings. Only written if nothing is stored yet, so a
        // reseed never overwrites a name someone has actually set.
        $settings = app(Settings::class);

        if (DB::table('settings')->doesntExist()) {
            $settings->set([
                'company.name' => 'Vetrik Private Limited',
                'display.timezone' => 'Asia/Kolkata',
                'display.date_format' => 'dmy',
                'display.currency' => 'INR',
            ]);
        }

        $this->call(WorkSeeder::class);
    }
}
