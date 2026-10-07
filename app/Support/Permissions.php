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

    /**
     * Keys that bring others with them, beyond the implied "view": revising
     * someone's salary means seeing it.
     */
    public const IMPLIES = [
        'employees.promote' => ['employees.salary'],
    ];

    /**
     * Actions that do not bring their module's "view". Creating a project is
     * not seeing every project: the creator joins what they make (see
     * Admin\ProjectController::store) and otherwise sees only their own.
     */
    public const STANDALONE = ['projects.create'];

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
                    // Pay is kept from everyone else: the amount on the profile,
                    // the forms, the promotion history and the letters that state it.
                    'salary' => 'See and set salaries, and open offer and promotion letters',
                    'promote' => 'Promote people and revise salaries, with a letter by email (includes seeing salaries)',
                ]],
                // Interns are managed apart from staff, so a coordinator can run
                // the internship programme without seeing everyone's records.
                'interns' => ['label' => 'Interns', 'actions' => [
                    'view' => 'See interns',
                    'create' => 'Add interns (creates their login)',
                    'edit' => 'Edit interns and archive them',
                    'delete' => 'Delete interns added by mistake',
                    'stipend' => 'See and set stipends, and open internship letters that state them',
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
                // Eligibility, not access: who may be *chosen* for a role inside
                // a project. No "view" here, so it grants nothing by itself.
                'project_roles' => ['label' => 'Project roles', 'actions' => [
                    'lead' => 'Can be made a project lead (full control of that project)',
                    'merge' => 'Can be given merge access on a project',
                ]],
                'merge_requests' => ['label' => 'Merge requests', 'actions' => [
                    'review' => 'Review and merge on any project',
                ]],
                // The team leader's permission. Reporting a bug needs none:
                // any project member may report one.
                'testing' => ['label' => 'Testing', 'actions' => [
                    'assign' => 'Assign bugs to people and move any bug through any step, on the projects they belong to',
                ]],
            ],
            'Accounts' => [
                'invoices' => ['label' => 'Invoices', 'actions' => [
                    'view' => 'See invoices',
                    'create' => 'Create draft invoices',
                    'edit' => 'Edit drafts, mark invoices paid or cancel them',
                    'delete' => 'Delete draft invoices',
                    'send' => 'Issue invoices and email them to customers',
                ]],
                'customers' => ['label' => 'Customers', 'actions' => [
                    'view' => 'See customers',
                    'create' => 'Add customers',
                    'edit' => 'Edit customers and switch them off',
                    'delete' => 'Delete customers never invoiced',
                ]],
                'products' => ['label' => 'Products & services', 'actions' => [
                    'view' => 'See products and services',
                    'create' => 'Add products and services',
                    'edit' => 'Edit them and switch them off',
                    'delete' => 'Delete ones never invoiced',
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
     * Keep only the keys the registry knows, add what each one implies (its
     * module's "view", and anything in IMPLIES), and return them in registry order.
     *
     * @param  iterable<string>  $keys
     * @return list<string>
     */
    public static function only(iterable $keys): array
    {
        $keys = collect($keys)->all();
        $all = self::all();

        foreach ($keys as $key) {
            if (in_array($key, $all, true)) {
                array_push($keys, ...(self::IMPLIES[$key] ?? []));
            }
        }

        foreach ($keys as $key) {
            $view = strtok($key, '.').'.'.self::VIEW;

            if (in_array($key, $all, true) && in_array($view, $all, true) && ! in_array($key, self::STANDALONE, true)) {
                $keys[] = $view;
            }
        }

        return array_values(array_filter($all, fn (string $key) => in_array($key, $keys, true)));
    }

    /**
     * Shape for the permission matrix: groups of modules, each with its
     * actions in the standard column order first, extras after.
     *
     * @return list<array{group: string, modules: list<array{key: string, label: string, actions: list<array{key: string, action: string, label: string, standalone: bool}>}>}>
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
                        ->map(fn (string $label, string $action) => [
                            'key' => "{$key}.{$action}",
                            'action' => $action,
                            'label' => $label,
                            'standalone' => in_array("{$key}.{$action}", self::STANDALONE, true),
                        ])
                        ->values()
                        ->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
