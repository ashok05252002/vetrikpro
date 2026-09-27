<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Master data (departments, designations, products…) that records point at.
 *
 * Once anything uses a row it can only be switched off, never deleted, so no
 * record ever loses what it named. An inactive row drops out of pickers, but a
 * record that already holds it keeps it — and may keep saving with it.
 */
trait IsMasterData
{
    public function initializeIsMasterData(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    /** Whether any record points at this row. In use means it cannot be deleted. */
    abstract public function isInUse(): bool;

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * What a picker offers: the active rows, plus the one the record being
     * edited already holds, even if it has since been switched off.
     */
    public function scopeSelectable(Builder $query, ?int $keep = null): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('is_active', true)->when($keep, fn (Builder $q) => $q->orWhere('id', $keep)));
    }

    /**
     * Validation for a picker field: the value must be active, unless it is
     * the value the record already had.
     */
    public static function selectableRule(?int $current = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($current) {
            if ($value === null || $value === '' || (int) $value === $current) {
                return;
            }

            if (! static::query()->whereKey($value)->where('is_active', true)->exists()) {
                $fail('That option has been switched off. Choose an active one.');
            }
        };
    }
}
