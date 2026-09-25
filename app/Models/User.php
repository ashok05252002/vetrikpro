<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Deleting an account cascades its employee record in the database,
        // bypassing Employee's own hook, so its files are cleared from here.
        static::deleting(fn (User $user) => $user->employee?->purgeDocumentFiles());
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * Initials for avatar fallbacks, e.g. "Priya HR" -> "PH".
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    /** @var list<string>|null Resolved once per request; see forgetPermissions(). */
    private ?array $resolvedPermissions = null;

    /**
     * What this user may do: their role's permissions, plus overrides granted
     * to them, minus overrides taken away. A super role ignores overrides, so
     * the administrator cannot be locked out by a stray revoke.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        $role = $this->role;

        if ($role === null) {
            return $this->resolvedPermissions = [];
        }

        if ($role->is_super) {
            return $this->resolvedPermissions = Permissions::all();
        }

        $keys = $role->permissionKeys();

        foreach ($this->permissionOverrides()->get() as $override) {
            $keys = $override->granted
                ? [...$keys, $override->permission]
                : array_diff($keys, [$override->permission]);
        }

        return $this->resolvedPermissions = Permissions::only($keys);
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions(), true);
    }

    public function isSuper(): bool
    {
        return (bool) $this->role?->is_super;
    }

    /**
     * Whether this user may hand the given permissions to someone else.
     * Nobody can give away what they do not hold themselves, which is what
     * stops an HR manager creating an administrator.
     *
     * @param  iterable<string>  $keys
     */
    public function canGrant(iterable $keys): bool
    {
        return array_diff(collect($keys)->all(), $this->permissions()) === [];
    }

    public function canAssignRole(Role $role): bool
    {
        return $role->is_super ? $this->isSuper() : $this->canGrant($role->permissionKeys());
    }

    /**
     * Replace this user's overrides. Keys map to true (grant) or false (revoke);
     * anything absent inherits from the role. Unknown keys are dropped.
     *
     * @param  array<string, bool>  $overrides
     */
    public function syncPermissionOverrides(array $overrides): void
    {
        $this->permissionOverrides()->delete();

        $rows = collect($overrides)
            ->only(Permissions::all())
            ->map(fn (bool $granted, string $permission) => ['permission' => $permission, 'granted' => $granted])
            ->values()
            ->all();

        $this->permissionOverrides()->createMany($rows);
        $this->forgetPermissions();
    }

    /**
     * Drop the cached resolution after the role or overrides change.
     */
    public function forgetPermissions(): void
    {
        $this->resolvedPermissions = null;
        $this->unsetRelation('role');
    }
}
