<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
