<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\Project;
use App\Models\RequirementDocument;
use App\Models\RequirementVersion;
use App\Support\ProjectWorkspace;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The project's Requirements tab. Documents are numbered and versioned: an
 * updated requirement is uploaded as a new version of the same document, and
 * every earlier version stays downloadable.
 */
class RequirementController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $documents = $project->requirements()
            ->with(['latestVersion.uploader:id,name'])
            ->withCount('versions')
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $number = RequirementDocument::parseReference($search);
                $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->when($number, fn ($n) => $n->orWhere('number', $number)));
            })
            ->orderBy('number')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RequirementDocument $document) => $this->row($document));

        return Inertia::render('projects/requirements', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'documents' => $documents,
            'filters' => $request->only('search'),
            'can' => ['create' => $request->user()->can('create', new RequirementDocument(['project_id' => $project->id]))],
        ]);
    }

    public function show(Request $request, Project $project, RequirementDocument $requirement): Response
    {
        $this->authorize('view', $requirement);

        $requirement->load(['creator:id,name', 'versions.uploader:id,name', 'latestVersion']);

        return Inertia::render('projects/requirement', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'document' => [
                ...$this->row($requirement),
                'description' => $requirement->description,
                'creator' => $requirement->creator?->only('id', 'name'),
                'created_at' => $requirement->created_at,
                'versions' => $requirement->versions->map(fn (RequirementVersion $version) => $this->version($version)),
            ],
            'can' => [
                'update' => $request->user()->can('update', $requirement),
                'delete' => $request->user()->can('delete', $requirement),
            ],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $document = new RequirementDocument(['project_id' => $project->id]);
        $this->authorize('create', $document);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => Uploads::documentRule(),
            'change_note' => ['nullable', 'string', 'max:2000'],
        ]);

        // One transaction: the REQ number and version 1 are allocated under
        // lock, and a failed upload leaves no half-made document behind.
        DB::transaction(function () use ($document, $data, $request) {
            $document->fill([...$data, 'created_by' => $request->user()->id])->save();
            $document->addVersion($request->file('file'), $data['change_note'] ?? 'First version', $request->user());
        });

        return to_route('projects.requirements.show', [$project, $document])
            ->with('success', "{$document->reference()} “{$document->title}” added.");
    }

    public function update(Request $request, Project $project, RequirementDocument $requirement): RedirectResponse
    {
        $this->authorize('update', $requirement);

        $requirement->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('success', "{$requirement->reference()} updated.");
    }

    public function storeVersion(Request $request, Project $project, RequirementDocument $requirement): RedirectResponse
    {
        $this->authorize('update', $requirement);

        $data = $request->validate([
            'file' => Uploads::documentRule(),
            'change_note' => ['required', 'string', 'max:2000'],
        ]);

        $version = DB::transaction(fn () => $requirement->addVersion($request->file('file'), $data['change_note'], $request->user()));

        return back()->with('success', "{$requirement->reference()} is now at version {$version->version}.");
    }

    public function download(Request $request, Project $project, RequirementDocument $requirement, RequirementVersion $version): StreamedResponse
    {
        $this->authorize('view', $requirement);

        return Storage::disk(EmployeeDocument::DISK)->download($version->file_path, $version->downloadName());
    }

    public function destroy(Project $project, RequirementDocument $requirement): RedirectResponse
    {
        $this->authorize('delete', $requirement);

        $reference = $requirement->reference();
        $requirement->delete();

        return to_route('projects.requirements.index', $project)->with('success', "{$reference} and all its versions were deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RequirementDocument $document): array
    {
        return [
            ...$document->only('id', 'project_id', 'number', 'title'),
            'reference' => $document->reference(),
            'versions_count' => $document->versions_count ?? $document->versions->count(),
            'current' => $document->latestVersion ? $this->version($document->latestVersion) : null,
            'updated_at' => $document->latestVersion?->created_at ?? $document->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function version(RequirementVersion $version): array
    {
        return [
            ...$version->only('id', 'version', 'original_name', 'mime_type', 'size', 'change_note'),
            'uploaded_by' => $version->uploader?->name,
            'uploaded_at' => $version->created_at,
        ];
    }
}
