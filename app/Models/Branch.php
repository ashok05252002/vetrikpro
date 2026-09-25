<?php

namespace App\Models;

use App\Enums\BranchStatus;
use App\Enums\MergeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    /**
     * What git accepts as a branch name, near enough: no spaces, no "..",
     * no leading or trailing slash, no ".lock" ending, no "//".
     */
    public const NAME_PATTERN = '/^(?!\/)(?!.*\/\/)(?!.*\.\.)(?!.*\.lock$)[A-Za-z0-9._\/-]+(?<!\/)$/';

    protected $fillable = ['project_id', 'name', 'base_branch', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'merged_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }

    public function testPoints(): BelongsToMany
    {
        return $this->belongsToMany(TestPoint::class)->withTimestamps();
    }

    public function mergeRequests(): HasMany
    {
        return $this->hasMany(MergeRequest::class)->latest('id');
    }

    public function liveMergeRequest(): HasOne
    {
        return $this->hasOne(MergeRequest::class)->whereIn('status', MergeRequestStatus::live())->latestOfMany();
    }
}
