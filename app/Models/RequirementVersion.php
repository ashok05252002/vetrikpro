<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of a project's requirements: a complete list of requirement
 * points. Versions follow a strict sequence — V1, V1.1 … V1.10, then V2,
 * V2.1 … — so nobody can skip or repeat one; nextFor() is the only way a new
 * version number is decided.
 */
class RequirementVersion extends Model
{
    public const MAX_MINOR = 10;

    protected $fillable = ['project_id', 'major', 'minor', 'source', 'file_name', 'created_by'];

    protected function casts(): array
    {
        return ['major' => 'integer', 'minor' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(RequirementPoint::class)->orderBy('number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "V1", "V1.3", "V2". */
    public function label(): string
    {
        return self::format($this->major, $this->minor);
    }

    public static function format(int $major, int $minor): string
    {
        return $minor === 0 ? "V{$major}" : "V{$major}.{$minor}";
    }

    /** The latest version of a project, or null before the first. */
    public static function latestFor(Project $project): ?self
    {
        return static::where('project_id', $project->id)->orderByDesc('major')->orderByDesc('minor')->first();
    }

    /**
     * The one version that may come next: V1 first; then the next minor, up
     * to .10; after .10, the next major.
     *
     * @return array{major: int, minor: int, label: string}
     */
    public static function nextFor(Project $project): array
    {
        $latest = static::latestFor($project);

        [$major, $minor] = match (true) {
            $latest === null => [1, 0],
            $latest->minor < self::MAX_MINOR => [$latest->major, $latest->minor + 1],
            default => [$latest->major + 1, 0],
        };

        return ['major' => $major, 'minor' => $minor, 'label' => self::format($major, $minor)];
    }
}
