<?php

namespace App\Models;

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
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
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
