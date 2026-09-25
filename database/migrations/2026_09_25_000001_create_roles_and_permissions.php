<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the hardcoded users.role enum with configurable roles.
 *
 * The three roles that existed as enum values become system roles with the
 * same slugs, and every user is moved across, so this runs on a live database
 * without a reseed. The HR permission list is written out here rather than
 * read from the registry: a migration has to mean the same thing forever.
 */
return new class extends Migration
{
    /** What HR could do before roles were configurable: all of /admin except settings. */
    private const HR_PERMISSIONS = [
        'users.manage',
        'employees.view',
        'employees.manage',
        'employees.documents',
        'masters.manage',
        'projects.view_all',
        'projects.manage',
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            // Holds every permission, present and future; cannot be narrowed.
            $table->boolean('is_super')->default(false);
            // Shipped with the app; can be edited but not deleted.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->unique(['role_id', 'permission']);
        });

        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            // true = granted on top of the role, false = taken away from it.
            $table->boolean('granted');
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });

        $now = now();

        $roles = [
            'admin' => ['name' => 'Administrator', 'description' => 'Full access to everything.', 'is_super' => true],
            'hr' => ['name' => 'HR Manager', 'description' => 'People, master data and projects. No organisation settings.', 'is_super' => false],
            'employee' => ['name' => 'Employee', 'description' => 'Their own projects and tasks.', 'is_super' => false],
        ];

        foreach ($roles as $slug => $role) {
            DB::table('roles')->insert([...$role, 'slug' => $slug, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $hrId = DB::table('roles')->where('slug', 'hr')->value('id');
        DB::table('role_permissions')->insert(
            array_map(fn (string $permission) => ['role_id' => $hrId, 'permission' => $permission], self::HR_PERMISSIONS),
        );

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained()->restrictOnDelete();
        });

        foreach (array_keys($roles) as $slug) {
            DB::table('users')->where('role', $slug)->update([
                'role_id' => DB::table('roles')->where('slug', $slug)->value('id'),
            ]);
        }

        // Anything unrecognised lands on the least-privileged role, never on none.
        DB::table('users')->whereNull('role_id')->update([
            'role_id' => DB::table('roles')->where('slug', 'employee')->value('id'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee')->after('email');
        });

        foreach (DB::table('roles')->whereIn('slug', ['admin', 'hr', 'employee'])->get() as $role) {
            DB::table('users')->where('role_id', $role->id)->update(['role' => $role->slug]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('user_permission_overrides');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
