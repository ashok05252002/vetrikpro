<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Salaries and stipends get their own permissions (employees.salary,
 * interns.stipend). Until now anyone who could open a profile saw the pay, so
 * the roles that set it keep it: HR keeps salaries (promoting implies them
 * anyway), and whoever edits interns keeps their stipends. Every other role
 * loses sight of pay until it is granted on the role.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grants = [];

        if ($hr = DB::table('roles')->where('slug', 'hr')->value('id')) {
            $grants[] = ['role_id' => $hr, 'permission' => 'employees.salary'];
        }

        foreach (DB::table('role_permissions')->where('permission', 'interns.edit')->pluck('role_id') as $roleId) {
            $grants[] = ['role_id' => $roleId, 'permission' => 'interns.stipend'];
        }

        DB::table('role_permissions')->insertOrIgnore($grants);
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', ['employees.salary', 'interns.stipend'])->delete();
        DB::table('user_permission_overrides')->whereIn('permission', ['employees.salary', 'interns.stipend'])->delete();
    }
};
