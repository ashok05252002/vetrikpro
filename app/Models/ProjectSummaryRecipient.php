<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone besides the owner who gets a project's daily summary: a person with
 * an account, or an outside address.
 */
class ProjectSummaryRecipient extends Model
{
    protected $fillable = ['user_id', 'email', 'added_by'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Where the summary goes: the account's current email, or the address
     * typed in. Null for a disabled account, which stops receiving it.
     */
    public function address(): ?string
    {
        if ($this->user_id !== null) {
            return $this->user?->is_active ? strtolower($this->user->email) : null;
        }

        return $this->email;
    }
}
