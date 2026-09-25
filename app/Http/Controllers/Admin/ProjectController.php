<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = Project::query()
            ->with('owner:id,name')
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
                'members',
            ])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->string('status')->value(), fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Project $project) => [
                ...$project->only('id', 'name', 'code', 'status', 'start_date', 'due_date'),
                'owner' => $project->owner?->only('id', 'name'),
                'tasks_count' => $project->tasks_count,
                'done_tasks_count' => $project->done_tasks_count,
                'members_count' => $project->members_count,
                'progress' => $project->progress(),
            ]);

        return Inertia::render('admin/projects/index', [
            'projects' => $projects,
            'statuses' => ProjectStatus::options(),
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/projects/create', $this->formOptions());
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        // Members are managed on the project's own Members tab, which is where
        // a new project needs to go next.
        return to_route('projects.members.index', $project)->with('success', "Project “{$project->name}” created. Add its members here.");
    }

    public function edit(Project $project): Response
    {
        return Inertia::render('admin/projects/edit', [
            'project' => [
                ...$project->only('id', 'name', 'code', 'description', 'repository_url', 'default_branch', 'status', 'owner_id', 'start_date', 'due_date'),
                'members_count' => $project->members()->count(),
                'owner' => $project->owner?->only('id', 'name', 'email'),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return to_route('admin.projects.index')->with('success', "Project “{$project->name}” updated.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $name = $project->name;
        $project->delete();

        return to_route('admin.projects.index')->with('success', "Project “{$name}” and its tasks were deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => ProjectStatus::options(),
        ];
    }
}
