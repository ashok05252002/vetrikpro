<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone the company invoices. */
class Customer extends Model
{
    use IsMasterData;

    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'address', 'state_code', 'gstin', 'is_active'];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isInUse(): bool
    {
        return $this->invoices()->exists();
    }
}
