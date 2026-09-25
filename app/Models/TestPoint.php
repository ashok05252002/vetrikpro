<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TestPointStatus;
use App\Models\Concerns\HasProjectNumber;
use App\Models\Concerns\HasStatusWorkflow;
use Database\Factories\TestPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestPoint extends Model
{
    /** @use HasFactory<TestPointFactory> */
    use HasFactory, HasProjectNumber, HasStatusWorkflow;

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
        // Who last ran it, and when, is derived from reaching an outcome —
        // never typed — so it cannot disagree with the column it sits in.
        static::saving(function (TestPoint $point) {
            if ($point->isDirty('status') && $point->status->isOutcome()) {
                $point->last_tested_at = now();
                $point->last_tested_by = auth()->id() ?? $point->last_tested_by;
            }
        });
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

    public function lastTester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_tested_by');
    }
}
