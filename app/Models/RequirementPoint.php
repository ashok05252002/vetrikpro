<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One requirement point: its S.No, module, description and notes, in one version. */
class RequirementPoint extends Model
{
    protected $fillable = ['number', 'module', 'description', 'notes'];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RequirementVersion::class, 'requirement_version_id');
    }

    /** The tasks that deliver this point. */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_requirement_point');
    }

    /** "#12" — the S.No as shown in pickers and chips. */
    public function reference(): string
    {
        return '#'.$this->number;
    }
}
