<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry on the document checklist: "Aadhaar card", "PAN card"…
 * Configured under Configuration → Document types.
 */
class DocumentType extends Model
{
    public const SIGNED_OFFER_LETTER = 'signed_offer_letter';

    protected $fillable = ['name', 'code', 'description', 'is_required', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function signedOfferLetter(): self
    {
        return static::where('code', self::SIGNED_OFFER_LETTER)->firstOrFail();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return static::active()->ordered()->get()
            ->map(fn (self $type) => ['value' => (string) $type->id, 'label' => $type->name])
            ->all();
    }
}
