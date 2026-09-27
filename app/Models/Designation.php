<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Database\Factories\DesignationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    /** @use HasFactory<DesignationFactory> */
    use HasFactory, IsMasterData;

    protected $fillable = [
        'department_id',
        'name',
        'description',
        'is_active',
    ];

    public function isInUse(): bool
    {
        return $this->employees()->exists()
            || Promotion::where('from_designation_id', $this->id)->orWhere('to_designation_id', $this->id)->exists();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
