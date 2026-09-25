<?php

namespace App\Models;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'code',
        'description',
        'repository_url',
        'default_branch',
        'status',
        'start_date',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function devAdmins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', ProjectMemberRole::DevAdmin->value);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Whether someone may work in this project at all: see its boards, add and
     * edit its tasks and test points. The single rule every project-scoped
     * policy starts from.
     */
    public function isAccessibleBy(User $user): bool
    {
        return $user->can('projects.view_all')
            || $this->owner_id === $user->id
            || $this->hasMember($user);
    }

    public function isDevAdmin(User $user): bool
    {
        return $this->devAdmins()->whereKey($user->id)->exists();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function testPoints(): HasMany
    {
        return $this->hasMany(TestPoint::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(RequirementDocument::class);
    }

    /**
     * Percentage of tasks in the Done column, 0 when the project has no tasks.
     */
    public function progress(): int
    {
        $total = $this->tasks_count ?? $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->done_tasks_count ?? $this->tasks()->where('status', TaskStatus::Done)->count();

        return (int) round($done / $total * 100);
    }
}
