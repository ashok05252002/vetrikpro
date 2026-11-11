<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of renewal — Domain, Server / VPS, AMC… — managed in Configuration
 * hub → Renewal types. Suggests a cycle and notice period for a new renewal.
 * Once a renewal uses it, it can be switched off but not deleted.
 */
class RenewalType extends Model
{
    use IsMasterData;

    protected $fillable = ['name', 'cycle_months', 'remind_days', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'cycle_months' => 'integer',
            'remind_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class);
    }

    public function isInUse(): bool
    {
        return $this->renewals()->exists();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Active types, plus the one a renewal already has.
     *
     * @return array<int, array{value: string, label: string, cycle: int, remind: int}>
     */
    public static function options(?int $keep = null): array
    {
        return static::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($keep, fn ($q, $id) => $q->orWhere('id', $id)))
            ->ordered()->get()
            ->map(fn (self $t) => ['value' => (string) $t->id, 'label' => $t->name, 'cycle' => $t->cycle_months, 'remind' => $t->remind_days])
            ->all();
    }
}
