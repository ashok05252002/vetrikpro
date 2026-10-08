<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pay is now seen only with the salary or stipend box ticked: promoting no
 * longer brings salaries with it, and documents can be marked as stating pay.
 *
 * Nobody loses sight of pay silently on deploy: every role (and per-user grant)
 * that had promote, and so saw salaries, gets the salary permission written
 * down. It then shows as ticked, and the administrator can untick it.
 *
 * A promotion by someone without the salary permission keeps the pay as it is,
 * which may be none — so the new salary may now be empty too.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('role_permissions')->where('permission', 'employees.promote')->pluck('role_id');
        DB::table('role_permissions')->insertOrIgnore($roles->map(fn ($id) => ['role_id' => $id, 'permission' => 'employees.salary'])->all());

        // A person's own salary override, either way, is the administrator's decision and stays.
        $users = DB::table('user_permission_overrides')->where('permission', 'employees.promote')->where('granted', true)->pluck('user_id');
        DB::table('user_permission_overrides')->insertOrIgnore($users->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'employees.salary', 'granted' => true, 'created_at' => now(), 'updated_at' => now(),
        ])->all());

        Schema::table('document_types', function (Blueprint $table) {
            $table->boolean('states_pay')->default(false)->after('is_required');
        });

        DB::table('document_types')->whereIn('code', ['offer_letter', 'signed_offer_letter', 'contract'])->update(['states_pay' => true]);

        Schema::table('promotions', function (Blueprint $table) {
            $table->decimal('to_salary', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('states_pay');
        });
    }
};
