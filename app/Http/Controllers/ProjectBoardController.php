<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
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
            ->unless($user->managesPeople(), fn ($query) => $query
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
     * The Kanban board for one project.
     */
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['owner:id,name', 'members:id,name,email']);

        $tasks = $project->tasks()
            ->with('assignee:id,name')
            ->withCount('comments')
            ->orderBy('position')
            ->get()
            ->map(fn (Task $task) => [
                ...$task->only('id', 'title', 'status', 'priority', 'due_date', 'position'),
                'assignee' => $task->assignee?->only('id', 'name'),
                'comments_count' => $task->comments_count,
                'is_overdue' => $task->isOverdue(),
            ]);

        return Inertia::render('projects/board', [
            'project' => [
                ...$project->only('id', 'name', 'code', 'description', 'status', 'start_date', 'due_date'),
                'owner' => $project->owner?->only('id', 'name'),
                'members' => $project->members->map->only('id', 'name', 'email'),
                'progress' => $project->progress(),
            ],
            // Grouped by column so the board renders without regrouping client-side.
            'columns' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'tasks' => $tasks->where('status', $status->value)->values(),
            ]),
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => $project->members
                ->push($project->owner)
                ->filter()
                ->unique('id')
                ->map->only('id', 'name')
                ->values(),
            'can' => [
                'createTask' => $request->user()->can('create', new Task(['project_id' => $project->id])),
                'updateProject' => $request->user()->can('update', $project),
            ],
        ]);
    }
}
