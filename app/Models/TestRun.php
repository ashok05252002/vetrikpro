<?php

namespace App\Models;

use App\Enums\TestResult;
use App\Enums\TestRunStatus;
use App\Models\Concerns\HasProjectNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One round of testing over a chosen set of testing points. Numbered per
 * project like everything else: RUN-3.
 */
class TestRun extends Model
{
    use HasProjectNumber;

    public const REFERENCE_PREFIX = 'RUN';

    protected $fillable = ['project_id', 'name', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => TestRunStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(TestRunResult::class)->orderBy('position')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === TestRunStatus::Open;
    }

    /**
     * Results counted by outcome, every outcome present (zero if none), from
     * one grouped query unless the counts were already eager-loaded.
     *
     * @return array<string, int>
     */
    public function tally(): array
    {
        $counts = $this->relationLoaded('results')
            ? $this->results->countBy(fn (TestRunResult $r) => $r->result->value)->all()
            : $this->results()->reorder()->selectRaw('result, COUNT(*) as total')->groupBy('result')->pluck('total', 'result')->all();

        $tally = [];
        foreach (TestResult::cases() as $case) {
            $tally[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $tally;
    }
}
