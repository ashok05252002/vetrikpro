<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissionRows')
            ->orderByDesc('is_super')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                ...$role->only('id', 'name', 'slug', 'description', 'is_super', 'is_system', 'users_count'),
                'permissions_count' => count($role->permissionKeys()),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'totalPermissions' => count(Permissions::all()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/create', [
            'permissionGroups' => Permissions::forEditor(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = DB::transaction(function () use ($request) {
            $role = Role::create([
                ...$request->safe()->only('name', 'description'),
                'slug' => $this->uniqueSlug($request->string('name')->value()),
            ]);
            $role->syncPermissions($request->validated('permissions'));

            return $role;
        });

        return to_route('admin.roles.index')->with('success', "Role “{$role->name}” created.");
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('admin/roles/edit', [
            'role' => [
                ...$role->only('id', 'name', 'slug', 'description', 'is_super', 'is_system'),
                'permissions' => $role->permissionKeys(),
                'users_count' => $role->users()->count(),
            ],
            'permissionGroups' => Permissions::forEditor(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            // The slug is an identifier other code and filters rely on; renaming
            // the role changes its label only.
            $role->update($request->safe()->only('name', 'description'));
            $role->syncPermissions($request->validated('permissions'));
        });

        return to_route('admin.roles.index')->with('success', "Role “{$role->name}” updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', "“{$role->name}” ships with the app and cannot be deleted.");
        }

        if ($role->users()->exists()) {
            return back()->with('error', "Move everyone off “{$role->name}” before deleting it.");
        }

        $role->delete();

        return to_route('admin.roles.index')->with('success', "Role “{$role->name}” deleted.");
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
