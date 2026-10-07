<?php

namespace App\Models;

use App\Enums\OnboardingStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'has_stipend',
        'stipend',
        'address',
        'status',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_name',
        'bank_branch',
    ];

    /** Onboarding columns change only through the onboarding flow, never mass assignment. */
    protected $hidden = ['bank_account_number', 'offer_letter_path'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'date_of_joining' => 'date:Y-m-d',
            'salary' => 'decimal:2',
            'has_stipend' => 'boolean',
            'stipend' => 'decimal:2',
            'bank_account_number' => 'encrypted',
            'onboarding_status' => OnboardingStatus::class,
            'invited_at' => 'datetime',
            'onboarding_submitted_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'archived_at' => 'datetime',
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

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public const INTERN = 'intern';

    public function isIntern(): bool
    {
        return $this->employment_type === self::INTERN;
    }

    /** The permission that guards this person's pay: a salary for staff, a stipend for interns. */
    public function payPermission(): string
    {
        return $this->isIntern() ? 'interns.stipend' : 'employees.salary';
    }

    /** Hide the pay amounts from a viewer who may not see them, for serialising. */
    public function withPayFor(User $viewer): static
    {
        return $viewer->canSeePayOf($this) ? $this : $this->makeHidden(['salary', 'stipend']);
    }

    /** @param  Builder<Employee>  $query */
    public function scopeInterns(Builder $query): Builder
    {
        return $query->where('employment_type', self::INTERN);
    }

    /** @param  Builder<Employee>  $query */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('employment_type', '!=', self::INTERN);
    }

    /** @param  Builder<Employee>  $query */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** @param  Builder<Employee>  $query */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /** Promotions and salary revisions, newest first. */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class)->latest('effective_date')->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    /**
     * "XXXX XXXX 1234": enough for HR to confirm the right account without
     * the full number sitting on every screen.
     */
    public function maskedAccountNumber(): ?string
    {
        $number = $this->bank_account_number;

        return $number ? str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4) : null;
    }

    public function isOnboarding(): bool
    {
        return $this->onboarding_status !== null && $this->onboarding_status !== OnboardingStatus::Completed;
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
