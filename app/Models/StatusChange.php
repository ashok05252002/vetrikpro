<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a task's or testing point's status history. Written once by
 * the model that changed; never edited.
 */
class StatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'from_status', 'to_status'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
