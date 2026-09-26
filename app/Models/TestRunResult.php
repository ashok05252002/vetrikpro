<?php

namespace App\Models;

use App\Enums\TestResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One testing point's outcome in one run. The point's number and title are
 * copied in when the run starts, so the run still reads correctly after the
 * point is renamed or deleted.
 */
class TestRunResult extends Model
{
    protected $fillable = ['test_point_id', 'point_number', 'point_title', 'result', 'notes', 'position'];

    protected function casts(): array
    {
        return [
            'result' => TestResult::class,
            'tested_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TestRun::class, 'test_run_id');
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(TestPoint::class, 'test_point_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function reference(): string
    {
        return TestPoint::REFERENCE_PREFIX.'-'.$this->point_number;
    }
}
