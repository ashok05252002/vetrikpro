<?php

namespace App\Models;

use App\Enums\RenewalCycleStatus;
use App\Support\Clock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Something that is paid for again every cycle: a domain, a server, an AMC.
 * Its periods are RenewalCycles; the open one is what reminders chase.
 */
class Renewal extends Model
{
    public const ACTIVE = 'active';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id', 'renewal_type_id', 'title', 'identifier', 'provider', 'cycle_months', 'cost_price', 'sell_price',
        'remind_days_before', 'repeat_daily', 'notify_customer', 'auto_renew', 'start_date', 'send_start_mail', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cycle_months' => 'integer',
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'remind_days_before' => 'integer',
            'repeat_daily' => 'boolean',
            'notify_customer' => 'boolean',
            'auto_renew' => 'boolean',
            'snoozed_until' => 'date:Y-m-d',
            'start_date' => 'date:Y-m-d',
            'send_start_mail' => 'boolean',
            'start_mail_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RenewalType::class, 'renewal_type_id');
    }

    /** "Domain clientx.com", or the title: how the customer sees it. */
    public function customerLabel(): string
    {
        return $this->identifier ? ($this->type?->name ?? '').' '.$this->identifier : $this->title;
    }

    /** The people responsible; each gets the team reminder. */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'renewal_owners')->orderBy('name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(RenewalCycle::class)->orderByDesc('due_date')->orderByDesc('id');
    }

    /** The period being chased, if any. */
    public function openCycle(): HasOne
    {
        return $this->hasOne(RenewalCycle::class)->where('status', RenewalCycleStatus::Pending);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->gt(Clock::today());
    }

    /** The price a new cycle is raised for: the selling price, else what it costs us. */
    public function cycleAmount(): float
    {
        return (float) ($this->sell_price ?? $this->cost_price ?? 0);
    }

    public function cycleLabel(): string
    {
        return match ($this->cycle_months) {
            1 => 'Monthly',
            3 => 'Quarterly',
            6 => 'Half-yearly',
            12 => 'Yearly',
            24 => 'Every 2 years',
            36 => 'Every 3 years',
            default => "Every {$this->cycle_months} months",
        };
    }
}
