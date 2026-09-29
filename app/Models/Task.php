<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasProjectNumber;
use App\Models\Concerns\HasStatusWorkflow;
use App\Models\Concerns\RecordsAssigner;
use App\Models\Concerns\SendsWorkMail;
use App\Notifications\TaskMarkedUrgent;
use App\Support\Clock;
use App\Support\SendsMailSafely;
use App\Support\Settings;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasProjectNumber, HasStatusWorkflow, RecordsAssigner, SendsWorkMail;

    public const REFERENCE_PREFIX = 'T';

    protected $fillable = [
        'project_id',
        'assigned_to',
        'created_by',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // completed_at is derived from the column the task sits in, so the two
        // can never disagree however the status was changed.
        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
            }
        });

        // Urgent work emails its assignee — from the model, so it happens
        // however the priority or assignee was changed.
        static::saved(fn (Task $task) => $task->notifyIfUrgent());
    }

    /** The branches that claim to deliver this. */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function testPoints(): HasMany
    {
        return $this->hasMany(TestPoint::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    /**
     * Email the assignee when the task becomes urgent for them: created
     * urgent, raised to urgent, or an urgent task handed to them. Never for
     * something they did themselves, never without a signed-in person behind
     * the change, and only once the change is committed.
     */
    public function notifyIfUrgent(): void
    {
        if ($this->priority !== TaskPriority::Urgent || $this->assigned_to === null || ! app(Settings::class)->get('notify.task.urgent')) {
            return;
        }

        $why = match (true) {
            $this->wasRecentlyCreated => TaskMarkedUrgent::CREATED,
            $this->wasChanged('priority') => TaskMarkedUrgent::ESCALATED,
            $this->wasChanged('assigned_to') => TaskMarkedUrgent::REASSIGNED,
            default => null,
        };

        $actor = auth()->user();

        // Only a person's action sends mail: seeders, imports and console
        // commands run with nobody signed in and must never email real inboxes.
        if ($why === null || $actor === null || $actor->id === $this->assigned_to) {
            return;
        }

        SendsMailSafely::afterCommit(fn () => $this->assignee?->notify(new TaskMarkedUrgent($this, $actor, $why)));
    }

    public function isOverdue(): bool
    {
        // Overdue from the day after the due date, in the organisation's timezone —
        // the same rule as scopeOverdue(), the dashboard and the overdue email.
        return $this->due_date !== null
            && $this->status !== TaskStatus::Done
            && $this->due_date->lt(Clock::today());
    }

    /** Open and due between today and a week from today, in the organisation's timezone. */
    public function scopeDueThisWeek(Builder $query): Builder
    {
        return $query->where('status', '!=', TaskStatus::Done)
            ->whereBetween('due_date', [Clock::today()->toDateString(), Clock::today()->addWeek()->toDateString()]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', Clock::today()->toDateString())
            ->where('status', '!=', TaskStatus::Done);
    }
}
