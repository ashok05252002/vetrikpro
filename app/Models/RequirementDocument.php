<?php

namespace App\Models;

use App\Models\Concerns\HasProjectNumber;
use App\Support\Uploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RequirementDocument extends Model
{
    use HasProjectNumber;

    public const REFERENCE_PREFIX = 'REQ';

    protected $fillable = ['project_id', 'title', 'description', 'created_by'];

    protected static function booted(): void
    {
        // Versions go by cascade, which fires no model events: clear the folder here.
        static::deleted(fn (RequirementDocument $document) => Storage::disk(EmployeeDocument::DISK)->deleteDirectory($document->directory()));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RequirementVersion::class)->orderByDesc('version');
    }

    /**
     * The newest version is the current one; there is no separate pointer to
     * keep in step.
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(RequirementVersion::class)->ofMany('version', 'max');
    }

    public function directory(): string
    {
        return "projects/{$this->project_id}/requirements/{$this->id}";
    }

    /**
     * Store a file as the next version. Call inside a transaction: the
     * document row is locked while the version number is chosen, so two
     * uploads at once become v2 and v3, never two v2s.
     */
    public function addVersion(UploadedFile $file, ?string $note, User $by): RequirementVersion
    {
        static::query()->whereKey($this->id)->lockForUpdate()->value('id');

        $next = (int) $this->versions()->max('version') + 1;

        return $this->versions()->create([
            'version' => $next,
            'file_path' => $file->store($this->directory(), EmployeeDocument::DISK),
            ...Uploads::meta($file),
            'change_note' => $note,
            'uploaded_by' => $by->id,
        ]);
    }
}
