<?php

namespace App\Models;

use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalPayment extends Model
{
    protected $fillable = ['paid_on', 'amount', 'mode', 'reference', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'mode' => PaymentMode::class,
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RenewalCycle::class, 'renewal_cycle_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
