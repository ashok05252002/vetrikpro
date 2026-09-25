<?php

namespace App\Support;

/**
 * Every permission the application knows about.
 *
 * Permissions are declared here, in code, and only *assigned* in the database
 * (to roles, and as per-user overrides). A key that is not in this registry
 * cannot be granted: validation and resolution both check against it, so a
 * stray form field or a stale row can never create a phantom permission.
 *
 * Adding a permission means one entry here plus the check that uses it.
 * Nothing needs to be migrated — roles that should have it are edited in the UI.
 */
final class Permissions
{
    /**
     * Grouped for the role editor, in display order.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'People' => [
                'users.manage' => 'Create, edit and delete user accounts',
                'roles.manage' => 'Manage roles and per-user access',
                'employees.view' => 'View employee profiles',
                'employees.manage' => 'Create, edit and delete employee records',
                'employees.documents' => 'Upload, download and delete employee documents',
                'masters.manage' => 'Manage departments and designations',
            ],
            'Work' => [
                'projects.view_all' => 'Work on every project, not only their own',
                'projects.manage' => 'Create, edit and delete projects and their members',
            ],
            'Developer' => [
                'dev.merge_any' => 'Review and merge branches on any project',
            ],
            'Organisation' => [
                'settings.manage' => 'Change organisation settings',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::groups())));
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /**
     * Keep only the keys the registry knows, in registry order.
     *
     * @param  iterable<string>  $keys
     * @return list<string>
     */
    public static function only(iterable $keys): array
    {
        $keys = collect($keys)->all();

        return array_values(array_filter(self::all(), fn (string $key) => in_array($key, $keys, true)));
    }

    /**
     * Shape for the frontend role editor and the user Access section.
     *
     * @return list<array{group: string, permissions: list<array{key: string, label: string}>}>
     */
    public static function forEditor(): array
    {
        return collect(self::groups())
            ->map(fn (array $permissions, string $group) => [
                'group' => $group,
                'permissions' => collect($permissions)
                    ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
