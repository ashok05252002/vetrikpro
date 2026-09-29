<?php

namespace App\Http\Controllers\Projects;

use App\Enums\BranchStatus;
use App\Enums\MergeRequestStatus;
use App\Enums\ProjectMemberRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MergeRequest;
use App\Models\Project;
use App\Support\Cards;
use App\Support\DevPresenter;
use App\Support\ProjectRoles;
use App\Support\ProjectWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project's Git tab and branch pages. Branches are made on GitHub and
 * registered here by hand, with the work they carry.
 */
class BranchController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $show = $request->string('show')->value() === 'merge-requests' ? 'merge-requests' : 'branches';

        $payload = $show === 'branches'
            ? [
                'branches' => $project->branches()
                    ->with(['creator:id,name', 'liveMergeRequest'])
                    ->withCount(['tasks', 'testPoints'])
                    ->when($request->string('search')->trim()->value(), fn ($q, string $s) => $q->where('name', 'like', "%{$s}%"))
                    ->when($request->string('status')->value(), fn ($q, string $s) => $q->where('status', $s))
                    ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', $request->user()->id))
                    ->latest('id')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Branch $b) => DevPresenter::branch($b)),
            ]
            : [
                'mergeRequests' => $project->mergeRequests()
                    ->with(['branch:id,name', 'requester:id,name', 'reviewer:id,name'])
                    ->when($request->string('search')->trim()->value(), function ($q, string $s) {
                        $number = MergeRequest::parseReference($s);
                        $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")
                            ->orWhereHas('branch', fn ($b) => $b->where('name', 'like', "%{$s}%"))
                            ->when($number, fn ($n) => $n->orWhere('number', $number)));
                    })
                    ->when($request->string('status')->value(), fn ($q, string $s) => $q->where('status', $s))
                    ->latest('id')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (MergeRequest $mr) => DevPresenter::mergeRequestRow($mr)),
            ];

        return Inertia::render('projects/git', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'show' => $show,
            ...$payload,
            'branchStatuses' => BranchStatus::options(),
            'mergeStatuses' => MergeRequestStatus::options(),
            'filters' => $request->only('search', 'status', 'mine'),
            'mergeAccess' => fn () => $this->mergeAccess($project),
            'can' => [
                'create' => $request->user()->can('create', new Branch(['project_id' => $project->id])),
                // The owner and leads decide who may merge.
                'manageMergeAccess' => $request->user()->can('manageMembers', $project),
            ],
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $branch = new Branch(['project_id' => $project->id]);
        $this->authorize('create', $branch);

        $data = $request->validate([
            ...$this->branchRules($project),
            'name' => ['required', 'string', 'max:200', 'regex:'.Branch::NAME_PATTERN, Rule::unique('branches')->where('project_id', $project->id)],
        ], $this->messages());

        DB::transaction(function () use ($branch, $data, $request) {
            $branch->fill([
                ...collect($data)->only('name', 'base_branch', 'description')->all(),
                'status' => BranchStatus::Active,
                'created_by' => $request->user()->id,
            ])->save();

            $branch->tasks()->sync($data['task_ids'] ?? []);
            $branch->testPoints()->sync($data['test_point_ids'] ?? []);
        });

        return to_route('projects.branches.show', [$project, $branch])->with('success', "Branch {$branch->name} registered.");
    }

    public function show(Request $request, Project $project, Branch $branch): Response
    {
        $this->authorize('view', $branch);

        $branch->load([
            'creator:id,name',
            'liveMergeRequest',
            'tasks' => fn ($q) => $q->with('assignee:id,name')->orderBy('number'),
            'testPoints' => fn ($q) => $q->with(['assignee:id,name', 'creator:id,name', 'task:id,number,title'])->orderBy('number'),
            'mergeRequests.requester:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('projects/branch', [
            'project' => ProjectWorkspace::header($project, $user),
            'branch' => [
                ...DevPresenter::branch($branch),
                'description' => $branch->description,
                'tasks' => $branch->tasks->map(fn ($t) => Cards::task($t)),
                'test_points' => $branch->testPoints->map(fn ($p) => Cards::testPoint($p)),
                'merge_requests' => $branch->mergeRequests->map(fn ($mr) => DevPresenter::mergeRequestRow($mr)),
                'readiness' => DevPresenter::readiness($branch),
            ],
            'devAdmins' => $project->devAdmins()->orderBy('users.name')->get(['users.id', 'users.name'])->map->only('id', 'name'),
            'can' => [
                'update' => $user->can('update', $branch),
                'requestMerge' => $user->can('requestMerge', $branch),
            ],
        ]);
    }

    public function update(Request $request, Project $project, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update($request->validate([
            'base_branch' => $this->branchRules($project)['base_branch'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('success', 'Branch updated.');
    }

    /**
     * Add linked work. Additive, so two people linking at once both land.
     */
    public function link(Request $request, Project $project, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'task_ids' => ['array'],
            'task_ids.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'test_point_ids' => ['array'],
            'test_point_ids.*' => ['integer', Rule::exists('test_points', 'id')->where('project_id', $project->id)],
        ], $this->messages());

        $branch->tasks()->syncWithoutDetaching($data['task_ids'] ?? []);
        $branch->testPoints()->syncWithoutDetaching($data['test_point_ids'] ?? []);

        return back();
    }

    public function unlink(Project $project, Branch $branch, string $kind, int $id): RedirectResponse
    {
        $this->authorize('update', $branch);

        match ($kind) {
            'tasks' => $branch->tasks()->detach($id),
            'test-points' => $branch->testPoints()->detach($id),
        };

        return back();
    }

    public function close(Project $project, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        if ($branch->liveMergeRequest !== null) {
            return back()->with('error', "Close {$branch->liveMergeRequest->reference()} first.");
        }

        $branch->update(['status' => BranchStatus::Closed]);

        return back()->with('success', "Branch {$branch->name} closed without merging.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function branchRules(Project $project): array
    {
        return [
            'base_branch' => ['required', 'string', 'max:200', 'regex:'.Branch::NAME_PATTERN],
            'description' => ['required', 'string', 'max:5000'],
            'task_ids' => ['array'],
            'task_ids.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'test_point_ids' => ['array'],
            'test_point_ids.*' => ['integer', Rule::exists('test_points', 'id')->where('project_id', $project->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.regex' => 'That is not a valid git branch name: no spaces, “..”, “//”, or leading/trailing slash.',
            'name.unique' => 'That branch is already registered on this project.',
            'base_branch.regex' => 'That is not a valid git branch name.',
            'task_ids.*.exists' => 'Every task must be on this project.',
            'test_point_ids.*.exists' => 'Every testing point must be on this project.',
        ];
    }

    /**
     * Who may review and merge on this project: the owner and leads always,
     * plus members given merge access — and which other members are eligible
     * to be given it (Roles & access → Project roles).
     *
     * @return array<string, mixed>
     */
    private function mergeAccess(Project $project): array
    {
        $members = $project->members()->get(['users.id', 'users.name', 'users.email']);
        $role = fn ($user) => $user->pivot->role;

        $people = collect();

        if ($project->owner) {
            $people->push([...$project->owner->only('id', 'name', 'email'), 'via' => 'owner']);
        }

        foreach ($members as $member) {
            if ($member->id === $project->owner_id) {
                continue;
            }
            if (in_array($role($member), [ProjectMemberRole::Lead->value, ProjectMemberRole::DevAdmin->value], true)) {
                $people->push([...$member->only('id', 'name', 'email'), 'via' => $role($member) === ProjectMemberRole::Lead->value ? 'lead' : 'granted']);
            }
        }

        $plainMembers = $members->filter(fn ($m) => $role($m) === ProjectMemberRole::Member->value && $m->id !== $project->owner_id)->pluck('id')->all();

        return [
            'people' => $people->values(),
            'eligible' => ProjectRoles::eligible(ProjectMemberRole::DevAdmin, $plainMembers),
        ];
    }
}
