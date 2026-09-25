<?php

namespace App\Models\Concerns;

use App\Models\StatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared by tasks and testing points: who may change the status, and a log
 * of every change.
 *
 * The log is written from model events, so it records the change however it
 * arrived — board drag, edit dialog, or anything added later — and can never
 * be forgotten by one code path.
 */
trait HasStatusWorkflow
{
    public static function bootHasStatusWorkflow(): void
    {
        static::created(fn (self $model) => $model->logStatus(null));

        static::updated(function (self $model) {
            if ($model->wasChanged('status')) {
                $original = $model->getOriginal('status');
                $model->logStatus($original instanceof \BackedEnum ? $original->value : $original);
            }
        });
    }

    public function statusChanges(): MorphMany
    {
        return $this->morphMany(StatusChange::class, 'subject')->orderBy('id');
    }

    /**
     * The people who own this piece of work: whoever created it, whoever it is
     * assigned to, the project's owner, and administrators. Nobody else moves
     * it along, even if they can see it or edit its wording.
     */
    public function statusChangeableBy(User $user): bool
    {
        return $user->isSuper()
            || ($this->created_by !== null && $this->created_by === $user->id)
            || ($this->assigned_to !== null && $this->assigned_to === $user->id)
            || ($this->project !== null && $this->project->owner_id === $user->id);
    }

    private function logStatus(?string $from): void
    {
        $this->statusChanges()->create([
            'user_id' => auth()->id(),
            'from_status' => $from,
            'to_status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
        ]);
    }
}
