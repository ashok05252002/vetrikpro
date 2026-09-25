<?php

namespace App\Support;

/**
 * Every permission the application knows about, as modules × actions.
 *
 * Permissions are declared here, in code, and only *assigned* in the database
 * (to roles, and as per-user overrides). A key that is not in this registry
 * cannot be granted: validation and resolution both check against it, so a
 * stray form field or a stale row can never create a phantom permission.
 *
 * Keys are "<module>.<action>". Most modules offer the four standard actions;
 * a few add their own (employees.onboard, merge_requests.review). Holding any
 * action on a module implies its "view": nobody edits what they cannot see.
 *
 * Adding a permission means one entry here plus the check that uses it.
 */
final class Permissions
{
    public const VIEW = 'view';

    public const STANDARD = ['view', 'create', 'edit', 'delete'];

    /**
     * group => module => [label, actions => description].
     *
     * @return array<string, array<string, array{label: string, actions: array<string, string>}>>
     */
    public static function modules(): array
    {
        return [
            'People' => [
                'roles' => ['label' => 'Roles & access', 'actions' => [
                    'view' => 'See roles and what they grant',
                    'create' => 'Create roles',
                    'edit' => 'Edit roles and individual people’s access',
                    'delete' => 'Delete roles',
                ]],
                // Each employee is also their login: the account lives here too.
                'employees' => ['label' => 'Employees', 'actions' => [
                    'view' => 'See employees and their accounts',
                    'create' => 'Add employees (creates their login)',
                    'edit' => 'Edit employees, send password resets, activate or deactivate',
                    'delete' => 'Delete employees and their login',
                    'onboard' => 'Send invites and review onboarding',
                ]],
                'documents' => ['label' => 'Employee documents', 'actions' => [
                    'view' => 'Open and download employee documents',
                    'create' => 'Upload documents for an employee',
                    'delete' => 'Delete employee documents',
                ]],
                'departments' => ['label' => 'Departments', 'actions' => [
                    'view' => 'See departments',
                    'create' => 'Add departments',
                    'edit' => 'Edit departments',
                    'delete' => 'Delete departments',
                ]],
                'designations' => ['label' => 'Designations', 'actions' => [
                    'view' => 'See designations',
                    'create' => 'Add designations',
                    'edit' => 'Edit designations',
                    'delete' => 'Delete designations',
                ]],
            ],
            'Work' => [
                'projects' => ['label' => 'Projects', 'actions' => [
                    'view' => 'See and work on every project, not only their own',
                    'create' => 'Create projects',
                    'edit' => 'Edit any project, its members and its content',
                    'delete' => 'Delete projects',
                ]],
                'merge_requests' => ['label' => 'Merge requests', 'actions' => [
                    'review' => 'Review and merge on any project',
                ]],
            ],
            'Configuration' => [
                'settings' => ['label' => 'Organisation settings', 'actions' => [
                    'view' => 'See organisation settings',
                    'edit' => 'Change organisation settings',
                ]],
                'document_types' => ['label' => 'Document types', 'actions' => [
                    'view' => 'See the document checklist',
                    'create' => 'Add document types',
                    'edit' => 'Edit document types',
                    'delete' => 'Delete document types',
                ]],
            ],
        ];
    }

    /**
     * Grouped for the role editor, in display order.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return collect(self::modules())
            ->map(fn (array $modules) => collect($modules)
                ->flatMap(fn (array $module, string $key) => collect($module['actions'])
                    ->mapWithKeys(fn (string $label, string $action) => ["{$key}.{$action}" => $label]))
                ->all())
            ->all();
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
     * Keep only the keys the registry knows, add each module's implied "view",
     * and return them in registry order.
     *
     * @param  iterable<string>  $keys
     * @return list<string>
     */
    public static function only(iterable $keys): array
    {
        $keys = collect($keys)->all();
        $all = self::all();

        foreach ($keys as $key) {
            $view = strtok($key, '.').'.'.self::VIEW;

            if (in_array($key, $all, true) && in_array($view, $all, true)) {
                $keys[] = $view;
            }
        }

        return array_values(array_filter($all, fn (string $key) => in_array($key, $keys, true)));
    }

    /**
     * Shape for the permission matrix: groups of modules, each with its
     * actions in the standard column order first, extras after.
     *
     * @return list<array{group: string, modules: list<array{key: string, label: string, actions: list<array{key: string, action: string, label: string}>}>}>
     */
    public static function forEditor(): array
    {
        return collect(self::modules())
            ->map(fn (array $modules, string $group) => [
                'group' => $group,
                'modules' => collect($modules)->map(fn (array $module, string $key) => [
                    'key' => $key,
                    'label' => $module['label'],
                    'actions' => collect($module['actions'])
                        ->sortBy(fn ($label, string $action) => ($i = array_search($action, self::STANDARD, true)) === false ? 99 : $i)
                        ->map(fn (string $label, string $action) => ['key' => "{$key}.{$action}", 'action' => $action, 'label' => $label])
                        ->values()
                        ->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
