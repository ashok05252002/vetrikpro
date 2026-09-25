<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TestPointAttachment extends Model
{
    /** Kilobytes, as the `max` rule counts them: 2 MB. */
    public const MAX_KB = 2048;

    /** Raster images only: no video, no PDF, and no SVG, which can carry script. */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Per testing point, so a page of evidence stays a page. */
    public const MAX_PER_POINT = 20;

    protected $fillable = ['file_path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (TestPointAttachment $attachment) => Storage::disk(EmployeeDocument::DISK)->delete($attachment->file_path));
    }

    public function testPoint(): BelongsTo
    {
        return $this->belongsTo(TestPoint::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
