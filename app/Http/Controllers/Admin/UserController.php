<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['employee:id,user_id,employee_code', 'role:id,name,slug,is_super'])
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->string('role')->value(), fn ($query, string $slug) => $query->whereHas('role', fn ($q) => $q->where('slug', $slug)))
            ->when($request->string('status')->value(), fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get()->map(fn (Role $role) => ['value' => $role->slug, 'label' => $role->name]),
            'filters' => $request->only('search', 'role', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/create', $this->formOptions($request->user()));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except('overrides'));

            if ($request->user()->can('roles.edit')) {
                $user->syncPermissionOverrides($request->overrides());
            }
        });

        return to_route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This account has access you do not have.');

        return Inertia::render('admin/users/edit', [
            'user' => [
                ...$user->only('id', 'name', 'email', 'role_id', 'is_active'),
                'overrides' => $user->permissionOverrides()->get()
                    ->mapWithKeys(fn ($override) => [$override->permission => $override->granted ? 'allow' : 'deny']),
            ],
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except('overrides');

        // A blank password field means the admin is not rotating the password.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->update($data);

            // Someone without roles.edit never sees the Access section, so a
            // missing field means "untouched", not "clear every override".
            if ($request->user()->can('roles.edit') && $request->has('overrides')) {
                $user->syncPermissionOverrides($request->overrides());
            }
        });

        return to_route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        abort_unless($request->user()->canGrant($user->permissions()), 403, 'This account has access you do not have.');

        $user->delete();

        return to_route('admin.users.index')->with('success', 'User deleted.');
    }

    /**
     * Only the roles this person may hand out are offered, each with the
     * permissions it carries so the Access section can preview inheritance.
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $actor): array
    {
        return [
            'roles' => Role::with('permissionRows')->orderBy('name')->get()
                ->filter(fn (Role $role) => $actor->canAssignRole($role))
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_super' => $role->is_super,
                    'permissions' => $role->permissionKeys(),
                ])
                ->values(),
            'permissionGroups' => Permissions::forEditor(),
            'canManageAccess' => $actor->can('roles.edit'),
        ];
    }
}
