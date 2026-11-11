<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One day's reminder for one cycle, to the team or to the customer. */
class RenewalReminder extends Model
{
    public const TEAM = 'team';

    public const CUSTOMER = 'customer';

    /** The one-off "your plan has started" email to the customer. */
    public const START = 'start';

    protected $fillable = ['renewal_cycle_id', 'audience', 'sent_on', 'recipients', 'days_left'];

    protected function casts(): array
    {
        return [
            'sent_on' => 'date:Y-m-d',
            'recipients' => 'array',
            'days_left' => 'integer',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RenewalCycle::class, 'renewal_cycle_id');
    }
}
