<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementVersion extends Model
{
    protected $fillable = ['version', 'file_path', 'original_name', 'mime_type', 'size', 'change_note', 'uploaded_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'size' => 'integer'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RequirementDocument::class, 'requirement_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The file name offered on download: the original name, tagged with the
     * version so two downloads never collide in someone's Downloads folder.
     */
    public function downloadName(): string
    {
        $extension = pathinfo($this->original_name, PATHINFO_EXTENSION);
        $base = pathinfo($this->original_name, PATHINFO_FILENAME);

        return $base.' (v'.$this->version.')'.($extension ? '.'.$extension : '');
    }
}
