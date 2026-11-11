<?php

namespace App\Models;

use App\Enums\RenewalCycleStatus;
use App\Support\Clock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One period of a renewal. `paid_total` is the sum of its payments, kept on
 * the row by RenewalService so lists can filter on the balance.
 */
class RenewalCycle extends Model
{
    protected $fillable = ['due_date', 'amount'];

    protected function casts(): array
    {
        return [
            'status' => RenewalCycleStatus::class,
            'due_date' => 'date:Y-m-d',
            'closed_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'paid_total' => 'decimal:2',
        ];
    }

    public function renewal(): BelongsTo
    {
        return $this->belongsTo(Renewal::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RenewalPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(RenewalReminder::class)->orderByDesc('sent_on')->orderByDesc('id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', RenewalCycleStatus::Pending);
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_total, 2));
    }

    public function isPartlyPaid(): bool
    {
        return $this->status === RenewalCycleStatus::Pending && (float) $this->paid_total > 0;
    }

    /** Negative once past due. */
    public function daysLeft(): int
    {
        return (int) Clock::today()->diffInDays($this->due_date, false);
    }

    /** The first day reminders go out. */
    public function remindFrom(Renewal $renewal): Carbon
    {
        return $this->due_date->copy()->subDays($renewal->remind_days_before);
    }
}
