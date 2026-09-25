<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Validation\Validator;

/**
 * Role and per-user override rules shared by the create and edit user forms.
 *
 * The rule that matters: nobody hands out access they do not hold. It covers
 * both the role picked and any permission granted as an override, so an HR
 * manager can create staff accounts but cannot mint an administrator.
 */
trait ValidatesAccess
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function accessRules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            // key => 'allow' | 'deny'; a key left out inherits from the role.
            'overrides' => ['sometimes', 'array'],
            'overrides.*' => ['in:allow,deny'],
        ];
    }

    protected function validateAccess(Validator $validator, ?User $target = null): void
    {
        $actor = $this->user();
        $role = Role::find($this->integer('role_id'));

        if ($role !== null && ! $actor->canAssignRole($role)) {
            $validator->errors()->add('role_id', 'You cannot assign a role with access you do not have yourself.');
        }

        if ($target !== null && $target->is($actor) && $actor->isSuper() && $role !== null && ! $role->is_super) {
            $validator->errors()->add('role_id', 'You cannot remove your own administrator role.');
        }

        $overrides = $this->input('overrides', []);

        if (! is_array($overrides) || $overrides === []) {
            return;
        }

        if (! $actor->can('roles.edit')) {
            $validator->errors()->add('overrides', 'You are not allowed to change individual access.');

            return;
        }

        $unknown = array_diff(array_keys($overrides), Permissions::all());

        if ($unknown !== []) {
            $validator->errors()->add('overrides', 'Unknown permission: '.implode(', ', $unknown).'.');
        }

        $granted = array_keys(array_filter($overrides, fn ($value) => $value === 'allow'));

        if (! $actor->canGrant($granted)) {
            $validator->errors()->add('overrides', 'You cannot grant access you do not have yourself.');
        }
    }

    /**
     * Overrides as the model stores them: permission => granted.
     *
     * @return array<string, bool>
     */
    public function overrides(): array
    {
        return array_map(fn (string $value) => $value === 'allow', $this->validated('overrides', []));
    }
}
