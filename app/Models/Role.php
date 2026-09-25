<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';

    public const HR = 'hr';

    public const EMPLOYEE = 'employee';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_super' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissionRows(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public static function bySlug(string $slug): self
    {
        return static::where('slug', $slug)->firstOrFail();
    }

    /**
     * The permissions this role grants. A super role holds the whole registry,
     * including permissions added after it was created.
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        if ($this->is_super) {
            return Permissions::all();
        }

        $rows = $this->relationLoaded('permissionRows') ? $this->permissionRows : $this->permissionRows()->get();

        return Permissions::only($rows->pluck('permission'));
    }

    /**
     * Replace this role's permissions. Unknown keys are dropped, and a super
     * role has nothing to store.
     *
     * @param  iterable<string>  $keys
     */
    public function syncPermissions(iterable $keys): void
    {
        $this->permissionRows()->delete();

        if (! $this->is_super) {
            $this->permissionRows()->createMany(
                array_map(fn (string $key) => ['permission' => $key], Permissions::only($keys)),
            );
        }

        $this->unsetRelation('permissionRows');
    }
}
