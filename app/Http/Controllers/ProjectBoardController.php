<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\Cards;
use App\Support\ProjectPeople;
use App\Support\ProjectWorkspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectBoardController extends Controller
{
    /**
     * Projects the signed-in user can actually see: everything for admin/HR,
     * owned-or-joined for everyone else.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::query()
            ->with('owner:id,name')
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
                'members',
            ])
            ->unless($user->can('projects.view'), fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($user->id))))
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                ...$project->only('id', 'name', 'code', 'description', 'status', 'due_date'),
                'owner' => $project->owner?->only('id', 'name'),
                'tasks_count' => $project->tasks_count,
                'done_tasks_count' => $project->done_tasks_count,
                'members_count' => $project->members_count,
                'progress' => $project->progress(),
            ]);

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'statuses' => ProjectStatus::options(),
            'filters' => $request->only('search'),
            'canCreate' => $user->can('create', Project::class),
        ]);
    }

    /**
     * The project's Tasks tab: the Kanban board, or the same tasks as a
     * filterable, paginated list (?view=list).
     */
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $view = $request->string('view')->value() === 'list' ? 'list' : 'board';
        $base = $project->tasks()->with('assignee:id,name')->withCount('comments');

        $payload = $view === 'board'
            ? [
                // Grouped by column so the board renders without regrouping client-side.
                'columns' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'items' => (clone $base)->where('status', $status)->orderBy('position')->get()->map(fn (Task $t) => Cards::task($t))->values(),
                ]),
            ]
            : [
                'list' => (clone $base)
                    ->when($request->string('search')->trim()->value(), function ($q, string $search) {
                        $number = Task::parseReference($search);
                        $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->when($number, fn ($n) => $n->orWhere('number', $number)));
                    })
                    ->when($request->string('status')->value(), fn ($q, string $status) => $q->where('status', $status))
                    ->when($request->string('priority')->value(), fn ($q, string $priority) => $q->where('priority', $priority))
                    ->when($request->integer('assignee'), fn ($q, int $id) => $q->where('assigned_to', $id))
                    ->orderBy('number')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Task $t) => Cards::task($t)),
            ];

        return Inertia::render('projects/board', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'view' => $view,
            ...$payload,
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => ProjectPeople::assignable($project),
            'filters' => $request->only('search', 'status', 'priority', 'assignee'),
            'can' => [
                'createTask' => $request->user()->can('create', new Task(['project_id' => $project->id])),
            ],
        ]);
    }
}
