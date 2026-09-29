<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\RequirementPoint;
use App\Models\RequirementVersion;
use App\Services\Requirements\RequirementSheet;
use App\Support\ProjectWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A project's requirements: versions (V1, V1.1 … V1.10, V2 …) of requirement
 * points, each version added whole — imported from the Excel template after it
 * passes every check, or typed in by hand.
 */
class RequirementController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $versions = $project->requirementVersions()->with('creator:id,name')->withCount('points')->get();
        $current = $versions->firstWhere('id', $request->integer('version')) ?? $versions->first();

        $points = $current
            ? $current->points()
                ->when($request->string('search')->trim()->value(), fn ($q, string $s) => $q->where(fn ($w) => $w
                    ->where('description', 'like', "%{$s}%")
                    ->orWhere('notes', 'like', "%{$s}%")
                    ->when(ctype_digit($s), fn ($n) => $n->orWhere('number', (int) $s))))
                ->when($request->string('module')->value(), fn ($q, string $m) => $q->where('module', $m))
                ->paginate(50)
                ->withQueryString()
            : null;

        return Inertia::render('projects/requirements', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'versions' => $versions->map(fn (RequirementVersion $v) => [
                ...$v->only('id', 'source', 'file_name', 'created_at', 'points_count'),
                'label' => $v->label(),
                'creator' => $v->creator?->only('id', 'name'),
            ]),
            'current' => $current?->only('id'),
            'points' => $points,
            'modules' => $current ? $current->points()->reorder()->distinct()->orderBy('module')->pluck('module') : [],
            'next' => RequirementVersion::nextFor($project)['label'],
            'filters' => $request->only('search', 'module'),
            'can' => ['manage' => $this->canManage($request, $project)],
        ]);
    }

    /** The Excel template to fill in. */
    public function template(Project $project, RequirementSheet $sheet): HttpResponse
    {
        $this->authorize('view', $project);

        return response($sheet->template(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Requirements template - '.addslashes($project->code).'.xlsx"',
        ]);
    }

    /** The upload page: next version, template, norms, check, import. */
    public function upload(Request $request, Project $project): Response
    {
        abort_unless($this->canManage($request, $project), 403);

        return Inertia::render('projects/requirements-upload', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'next' => RequirementVersion::nextFor($project)['label'],
            'norms' => RequirementSheet::norms(),
            'headers' => RequirementSheet::HEADERS,
        ]);
    }

    /**
     * Check a workbook without importing it: every header and row problem, or
     * the points it would import.
     */
    public function check(Request $request, Project $project, RequirementSheet $sheet): JsonResponse
    {
        abort_unless($this->canManage($request, $project), 403);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls']], ['file.mimes' => 'Upload the Excel template: an .xlsx or .xls file.']);

        return response()->json($sheet->check($request->file('file')));
    }

    /** Import a workbook as the next version — only if it passes every check. */
    public function import(Request $request, Project $project, RequirementSheet $sheet): RedirectResponse
    {
        abort_unless($this->canManage($request, $project), 403);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls']], ['file.mimes' => 'Upload the Excel template: an .xlsx or .xls file.']);

        $result = $sheet->check($request->file('file'));

        if ($result['header_errors'] !== [] || $result['errors'] !== []) {
            throw ValidationException::withMessages(['file' => 'The file has problems. Check it again, fix them and upload once it is clean.']);
        }

        $version = $this->createVersion($request, $project, 'excel', $result['points'], $request->file('file')->getClientOriginalName());

        return to_route('projects.requirements.index', [$project, 'version' => $version->id])
            ->with('success', count($result['points'])." requirements imported as {$version->label()}.");
    }

    /** Typing requirements in: starts from the latest version's points. */
    public function manual(Request $request, Project $project): Response
    {
        abort_unless($this->canManage($request, $project), 403);
        $latest = RequirementVersion::latestFor($project);

        return Inertia::render('projects/requirements-manual', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'next' => RequirementVersion::nextFor($project)['label'],
            'from' => $latest?->label(),
            'points' => $latest ? $latest->points()->get(['module', 'description', 'notes']) : [],
            'limits' => RequirementSheet::LIMITS,
        ]);
    }

    public function storeManual(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->canManage($request, $project), 403);

        $limits = RequirementSheet::LIMITS;
        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:'.RequirementSheet::MAX_ROWS],
            'points.*.module' => ['required', 'string', 'max:'.$limits['module']],
            'points.*.description' => ['required', 'string', 'max:'.$limits['description']],
            'points.*.notes' => ['nullable', 'string', 'max:'.$limits['notes']],
        ], [
            'points.required' => 'Add at least one requirement.',
            'points.*.module.required' => 'Module is required.',
            'points.*.description.required' => 'Description is required.',
        ]);

        // Numbers are given here, in order: 1, 2, 3 …
        $points = array_map(
            fn ($p, $i) => ['number' => $i + 1, 'module' => trim($p['module']), 'description' => trim($p['description']), 'notes' => $p['notes'] ?? null],
            array_values($data['points']),
            array_keys(array_values($data['points'])),
        );

        $version = $this->createVersion($request, $project, 'manual', $points);

        return to_route('projects.requirements.index', [$project, 'version' => $version->id])
            ->with('success', count($points)." requirements saved as {$version->label()}.");
    }

    /**
     * Take the next version number under a lock on the project, so two people
     * saving at once cannot both claim V1.3, and write its points.
     *
     * @param  list<array{number: int, module: string, description: string, notes: ?string}>  $points
     */
    private function createVersion(Request $request, Project $project, string $source, array $points, ?string $fileName = null): RequirementVersion
    {
        return DB::transaction(function () use ($request, $project, $source, $points, $fileName) {
            Project::whereKey($project->id)->lockForUpdate()->value('id');
            $next = RequirementVersion::nextFor($project);

            $version = RequirementVersion::create([
                'project_id' => $project->id,
                'major' => $next['major'],
                'minor' => $next['minor'],
                'source' => $source,
                'file_name' => $fileName,
                'created_by' => $request->user()->id,
            ]);

            $now = now();
            foreach (array_chunk($points, 500) as $chunk) {
                RequirementPoint::insert(array_map(fn ($p) => [...$p, 'requirement_version_id' => $version->id, 'created_at' => $now, 'updated_at' => $now], $chunk));
            }

            return $version;
        });
    }

    /** Who adds versions: whoever edits projects, the project's owner and leads, and those with merge access. */
    private function canManage(Request $request, Project $project): bool
    {
        $user = $request->user();

        return $user->can('projects.edit') || $project->isLedBy($user) || $project->isDevAdmin($user);
    }
}
