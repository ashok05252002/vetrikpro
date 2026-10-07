<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function create(Request $request): Response
    {
        return Inertia::render('admin/projects/create', [
            ...$this->formOptions(),
            // Whoever can't see every project joins the ones they create; see store().
            'joinsAsMember' => ! $request->user()->can('projects.view'),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request) {
            $project = Project::create($request->safe()->except('lead_ids'));
            self::syncLeads($project, $request->validated('lead_ids') ?? []);

            // Someone who only sees their own projects would otherwise be shut
            // out of the one they just made. Picking themselves as a lead
            // above already added them, with that role.
            $creator = $request->user();
            if (! $creator->can('projects.view') && ! $project->hasMember($creator)) {
                $project->members()->attach($creator->id, ['role' => ProjectMemberRole::Member->value]);
            }

            return $project;
        });

        // Members are managed on the project's own Members tab, which is where
        // a new project needs to go next.
        return to_route('projects.members.index', $project)->with('success', "Project “{$project->name}” created. Add its members here.");
    }

    public function edit(Project $project): Response
    {
        return Inertia::render('admin/projects/edit', [
            'project' => [
                ...$project->only('id', 'name', 'code', 'description', 'repository_url', 'default_branch', 'status', 'owner_id', 'start_date', 'due_date'),
                // Plain Y-m-d, so the date input shows it and it saves back unchanged.
                'start_date' => $project->start_date?->toDateString(),
                'due_date' => $project->due_date?->toDateString(),
                'members_count' => $project->members()->count(),
                'owner' => $project->owner?->only('id', 'name', 'email'),
                'lead_ids' => $project->leads()->pluck('users.id')->map(fn ($id) => (string) $id)->all(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $project->update($request->safe()->except('lead_ids'));
            self::syncLeads($project, $request->validated('lead_ids') ?? []);
        });

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
            // Only people whose role (or extra access) makes them eligible.
            'eligibleLeads' => ProjectRoles::eligible(ProjectMemberRole::Lead),
        ];
    }

    /**
     * Make exactly these people the project's leads. New leads join as members
     * if they weren't; leads no longer chosen stay on the project as members.
     * Other members' roles are untouched.
     *
     * @param  list<int|string>  $leadIds
     */
    private static function syncLeads(Project $project, array $leadIds): void
    {
        $leadIds = array_map('intval', $leadIds);

        $project->leads()->whereNotIn('users.id', $leadIds)->get()
            ->each(fn ($user) => $project->members()->updateExistingPivot($user->id, ['role' => ProjectMemberRole::Member->value]));

        foreach ($leadIds as $id) {
            $project->hasMember(User::find($id))
                ? $project->members()->updateExistingPivot($id, ['role' => ProjectMemberRole::Lead->value])
                : $project->members()->attach($id, ['role' => ProjectMemberRole::Lead->value]);
        }
    }
}
