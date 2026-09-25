<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the coarse permissions ("users.manage") into per-action ones
 * ("users.view", "users.create", …). Every old key maps to exactly the
 * actions it used to allow, on roles and on per-user overrides alike, so
 * nobody gains or loses access in the move. The map is written out here
 * rather than read from the registry: a migration must mean the same thing
 * forever.
 */
return new class extends Migration
{
    private const MAP = [
        'users.manage' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
        'roles.manage' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete'],
        'employees.view' => ['employees.view'],
        'employees.manage' => ['employees.view', 'employees.create', 'employees.edit', 'employees.delete', 'employees.onboard'],
        'employees.documents' => ['documents.view', 'documents.create', 'documents.delete'],
        'masters.manage' => [
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'designations.view', 'designations.create', 'designations.edit', 'designations.delete',
        ],
        'projects.view_all' => ['projects.view'],
        'projects.manage' => ['projects.view', 'projects.create', 'projects.edit', 'projects.delete'],
        'settings.manage' => [
            'settings.view', 'settings.edit',
            'document_types.view', 'document_types.create', 'document_types.edit', 'document_types.delete',
        ],
        'dev.merge_any' => ['merge_requests.review'],
    ];

    public function up(): void
    {
        $this->convert(self::MAP);
    }

    public function down(): void
    {
        // Back to the coarse keys: an old key returns only if every action it
        // stands for is present, which is how they were written going up.
        DB::transaction(function () {
            foreach (DB::table('role_permissions')->get()->groupBy('role_id') as $roleId => $rows) {
                $held = $rows->pluck('permission')->all();
                DB::table('role_permissions')->where('role_id', $roleId)->delete();

                foreach (self::MAP as $old => $new) {
                    if (array_diff($new, $held) === []) {
                        DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission' => $old]);
                    }
                }
            }

            foreach (DB::table('user_permission_overrides')->get() as $row) {
                $old = collect(self::MAP)->search(fn (array $new) => in_array($row->permission, $new, true));
                DB::table('user_permission_overrides')->where('id', $row->id)->delete();

                if ($old !== false) {
                    DB::table('user_permission_overrides')->updateOrInsert(
                        ['user_id' => $row->user_id, 'permission' => $old],
                        ['granted' => $row->granted, 'created_at' => $row->created_at, 'updated_at' => now()],
                    );
                }
            }
        });
    }

    /**
     * @param  array<string, list<string>>  $map
     */
    private function convert(array $map): void
    {
        DB::transaction(function () use ($map) {
            foreach (DB::table('role_permissions')->whereIn('permission', array_keys($map))->get() as $row) {
                foreach ($map[$row->permission] as $key) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $row->role_id, 'permission' => $key]);
                }

                // A key that maps onto itself (employees.view) must survive.
                if (! in_array($row->permission, $map[$row->permission], true)) {
                    DB::table('role_permissions')->where('id', $row->id)->delete();
                }
            }

            foreach (DB::table('user_permission_overrides')->whereIn('permission', array_keys($map))->get() as $row) {
                foreach ($map[$row->permission] as $key) {
                    // A revoke of the old key revokes every action it covered.
                    DB::table('user_permission_overrides')->updateOrInsert(
                        ['user_id' => $row->user_id, 'permission' => $key],
                        ['granted' => $row->granted, 'created_at' => $row->created_at, 'updated_at' => now()],
                    );
                }

                if (! in_array($row->permission, $map[$row->permission], true)) {
                    DB::table('user_permission_overrides')->where('id', $row->id)->delete();
                }
            }
        });
    }
};
