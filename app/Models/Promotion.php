<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A promotion or salary revision: the designation and salary before and
 * after, from an effective date, with the letter sent to the person.
 */
class Promotion extends Model
{
    protected $fillable = [
        'employee_id',
        'from_designation_id',
        'to_designation_id',
        'from_designation_name',
        'to_designation_name',
        'from_department_id',
        'to_department_id',
        'from_salary',
        'to_salary',
        'effective_date',
        'note',
        'created_by',
    ];

    protected $hidden = ['letter_path'];

    protected function casts(): array
    {
        return [
            'from_salary' => 'decimal:2',
            'to_salary' => 'decimal:2',
            'effective_date' => 'date:Y-m-d',
            'emailed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The increase as a percentage of the old salary, or null when there was none. */
    public function incrementPercent(): ?float
    {
        if ($this->to_salary === null) {
            return null;
        }

        $from = (float) $this->from_salary;

        return $from > 0 ? round(((float) $this->to_salary - $from) / $from * 100, 1) : null;
    }

    public function isDesignationChange(): bool
    {
        return $this->from_designation_id !== $this->to_designation_id;
    }
}
