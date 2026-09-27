<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tasks and testing points remember who assigned them, derived from the
 * assignee changing — so it holds however the change arrived (dialog, board,
 * or anything added later). Unassigning clears it.
 */
trait RecordsAssigner
{
    public static function bootRecordsAssigner(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('assigned_to')) {
                $model->assigned_by = $model->assigned_to === null ? null : auth()->id();
            }
        });
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
