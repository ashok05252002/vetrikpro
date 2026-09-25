<?php

namespace App\Models;

use App\Enums\MergeRequestStatus;
use App\Models\Concerns\HasProjectNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MergeRequest extends Model
{
    use HasProjectNumber;

    public const REFERENCE_PREFIX = 'MR';

    /**
     * Status and the review columns are deliberately not fillable: they only
     * change through MergeRequestService, which checks the transition.
     */
    protected $fillable = ['project_id', 'branch_id', 'target_branch', 'title', 'description', 'requested_by', 'reviewer_id'];

    protected function casts(): array
    {
        return [
            'status' => MergeRequestStatus::class,
            'reviewed_at' => 'datetime',
            'merged_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MergeRequestEvent::class)->orderBy('id');
    }
}
