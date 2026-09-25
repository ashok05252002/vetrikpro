<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Users and employees become one thing in the app: every person has a login
 * and an HR record, managed together under Employees. The two tables stay —
 * sign-in and HR data are different concerns — but no login may exist
 * without an employee record any more.
 *
 * 1. Anyone with a login and no employee record gets one.
 * 2. The "users" permissions fold into "employees", on roles and overrides,
 *    so nobody gains or loses anything: a role that could manage accounts
 *    can now manage the employees those accounts belong to.
 */
return new class extends Migration
{
    private const MAP = [
        'users.view' => 'employees.view',
        'users.create' => 'employees.create',
        'users.edit' => 'employees.edit',
        'users.delete' => 'employees.delete',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $next = $this->nextCodeNumber();
            $now = now();

            foreach (DB::table('users')->whereNotIn('id', DB::table('employees')->select('user_id'))->orderBy('id')->get() as $user) {
                DB::table('employees')->insert([
                    'user_id' => $user->id,
                    'employee_code' => 'EMP-'.str_pad((string) $next++, 4, '0', STR_PAD_LEFT),
                    'employment_type' => 'full_time',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (self::MAP as $old => $new) {
                foreach (DB::table('role_permissions')->where('permission', $old)->get() as $row) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $row->role_id, 'permission' => $new]);
                }
                DB::table('role_permissions')->where('permission', $old)->delete();

                foreach (DB::table('user_permission_overrides')->where('permission', $old)->get() as $row) {
                    DB::table('user_permission_overrides')->updateOrInsert(
                        ['user_id' => $row->user_id, 'permission' => $new],
                        ['granted' => $row->granted, 'created_at' => $row->created_at, 'updated_at' => now()],
                    );
                }
                DB::table('user_permission_overrides')->where('permission', $old)->delete();
            }
        });
    }

    /**
     * The permission split cannot be told apart once merged, so going down
     * restores the users.* keys for every role that can manage employees.
     * Backfilled employee records are kept: deleting HR data on a rollback
     * would be worse than leaving an extra row.
     */
    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $old => $new) {
                foreach (DB::table('role_permissions')->where('permission', $new)->get() as $row) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $row->role_id, 'permission' => $old]);
                }
            }
        });
    }

    private function nextCodeNumber(): int
    {
        $max = DB::table('employees')->pluck('employee_code')
            ->map(fn (string $code) => preg_match('/(\d+)$/', $code, $m) ? (int) $m[1] : 0)
            ->max();

        return ((int) $max) + 1;
    }
};
