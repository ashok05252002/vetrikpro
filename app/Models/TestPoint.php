<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TestPointStatus;
use App\Models\Concerns\HasProjectNumber;
use App\Models\Concerns\HasStatusWorkflow;
use App\Models\Concerns\RecordsAssigner;
use App\Models\Concerns\SendsWorkMail;
use Database\Factories\TestPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TestPoint extends Model
{
    /** @use HasFactory<TestPointFactory> */
    use HasFactory, HasProjectNumber, RecordsAssigner, SendsWorkMail;

    use HasStatusWorkflow {
        statusChangeableBy as ownsWork;
    }

    public const REFERENCE_PREFIX = 'TP';

    protected $fillable = [
        'project_id',
        'task_id',
        'title',
        'steps',
        'expected_result',
        'actual_result',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestPointStatus::class,
            'priority' => TaskPriority::class,
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Attachment rows go by cascade, which fires no model events.
        static::deleted(fn (TestPoint $point) => Storage::disk(EmployeeDocument::DISK)->deleteDirectory($point->attachmentDirectory()));

        // Who last ran it, and when, is derived from reaching an outcome —
        // never typed — so it cannot disagree with the column it sits in.
        static::saving(function (TestPoint $point) {
            if ($point->isDirty('status') && $point->status->isOutcome()) {
                $point->last_tested_at = now();
                $point->last_tested_by = auth()->id() ?? $point->last_tested_by;
            }
        });
    }

    /**
     * Who moves a bug along: whoever reported it, whoever it is assigned to,
     * the project owner, administrators — and anyone who assigns bugs (the
     * team leader), since they run the flow.
     */
    public function statusChangeableBy(User $user): bool
    {
        return $this->ownsWork($user) || $user->can('testing.assign');
    }

    /**
     * Choosing who a bug is for is the team leader's call: people holding
     * testing.assign, the project owner, and administrators.
     */
    public function assignableBy(User $user): bool
    {
        return $user->can('testing.assign') || ($this->project !== null && $this->project->isLedBy($user));
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TestPointAttachment::class)->orderBy('id');
    }

    /**
     * This point's result in every run it was part of, newest run first.
     */
    public function runResults(): HasMany
    {
        return $this->hasMany(TestRunResult::class)->latest('test_run_id');
    }

    public function attachmentDirectory(): string
    {
        return "projects/{$this->project_id}/testing/{$this->id}";
    }

    public function lastTester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_tested_by');
    }
}
