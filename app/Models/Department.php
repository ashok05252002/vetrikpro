<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, IsMasterData;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    public function isInUse(): bool
    {
        return $this->designations()->exists() || $this->employees()->exists()
            || Promotion::where('from_department_id', $this->id)->orWhere('to_department_id', $this->id)->exists();
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
