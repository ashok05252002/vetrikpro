<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a merge request's timeline. Written once, never edited.
 */
class MergeRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'from_status', 'to_status', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
