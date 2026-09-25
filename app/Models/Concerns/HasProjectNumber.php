<?php

namespace App\Models\Concerns;

use App\Services\ProjectSequence;

/**
 * A record numbered within its project, shown as a reference like "T-12".
 * Models using this define REFERENCE_PREFIX.
 */
trait HasProjectNumber
{
    public static function bootHasProjectNumber(): void
    {
        static::creating(function (self $model) {
            $model->number ??= ProjectSequence::next($model->project_id, $model->getTable());
        });
    }

    public function reference(): string
    {
        return static::REFERENCE_PREFIX.'-'.$this->number;
    }

    /**
     * Accepts "12", "T-12" or "t12" and returns 12, so people can search by
     * the reference they see on the card.
     */
    public static function parseReference(string $input): ?int
    {
        $prefix = preg_quote(static::REFERENCE_PREFIX, '/');

        return preg_match("/^(?:{$prefix}-?)?(\\d+)$/i", trim($input), $m) ? (int) $m[1] : null;
    }
}
