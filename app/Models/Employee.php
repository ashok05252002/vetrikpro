<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'designation_id',
        'employee_code',
        'phone',
        'date_of_birth',
        'gender',
        'date_of_joining',
        'employment_type',
        'salary',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'date_of_joining' => 'date:Y-m-d',
            'salary' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Document rows go by cascade, which fires no model events, so the
        // files are removed here, once, for the whole folder.
        static::deleted(fn (Employee $employee) => $employee->purgeDocumentFiles());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    public function purgeDocumentFiles(): void
    {
        Storage::disk(EmployeeDocument::DISK)->deleteDirectory(EmployeeDocument::directoryFor($this->id));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Next sequential code, e.g. EMP-0007.
     */
    public static function nextCode(): string
    {
        $last = static::query()->orderByDesc('id')->value('employee_code');
        $number = $last && preg_match('/(\d+)$/', $last, $m) ? ((int) $m[1]) + 1 : 1;

        return 'EMP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
