<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EmployeeDocument extends Model
{
    public const DISK = 'documents';

    protected $fillable = [
        'document_type_id',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'expires_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date:Y-m-d',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The row and the file go together.
        static::deleted(fn (EmployeeDocument $document) => Storage::disk(self::DISK)->delete($document->file_path));
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Where an employee's files live on the documents disk.
     */
    public static function directoryFor(int $employeeId): string
    {
        return "employees/{$employeeId}";
    }
}
