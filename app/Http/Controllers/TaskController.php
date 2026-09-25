<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * "My tasks" — everything assigned to the signed-in user, plus, for admin
     * and HR, an option to widen to the whole organisation.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $scope = $request->string('scope')->value() ?: 'mine';
        $showAll = $scope === 'all' && $user->can('projects.view_all');

        $tasks = Task::query()
            ->with(['project:id,name,code', 'assignee:id,name'])
            ->withCount('comments')
            ->unless($showAll, fn ($query) => $query->where('assigned_to', $user->id))
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query
                ->where('title', 'like', "%{$search}%"))
            ->when($request->string('status')->value(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('priority')->value(), fn ($query, string $p) => $query->where('priority', $p))
            ->when($request->boolean('overdue'), fn ($query) => $query->overdue())
            // Workflow order, then soonest due first with undated tasks last.
            // Written as CASE rather than MySQL's FIELD() so SQLite works too.
            ->orderByRaw("CASE status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'in_review' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Task $task) => [
                ...$task->only('id', 'title', 'status', 'priority', 'due_date'),
                'project' => $task->project?->only('id', 'name', 'code'),
                'assignee' => $task->assignee?->only('id', 'name'),
                'comments_count' => $task->comments_count,
                'is_overdue' => $task->isOverdue(),
            ]);

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'filters' => [
                ...$request->only('search', 'status', 'priority'),
                'scope' => $showAll ? 'all' : 'mine',
                'overdue' => $request->boolean('overdue'),
            ],
            'canSeeAll' => $user->can('projects.view_all'),
        ]);
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'project:id,name,code,owner_id',
            'assignee:id,name',
            'creator:id,name',
            'comments.user:id,name',
        ]);

        return Inertia::render('tasks/show', [
            'task' => [
                ...$task->only('id', 'project_id', 'title', 'description', 'status', 'priority', 'due_date', 'completed_at'),
                'project' => $task->project?->only('id', 'name', 'code'),
                'assignee' => $task->assignee?->only('id', 'name'),
                'creator' => $task->creator?->only('id', 'name'),
                'is_overdue' => $task->isOverdue(),
                'created_at' => $task->created_at,
                'comments' => $task->comments->map(fn ($comment) => [
                    ...$comment->only('id', 'body'),
                    'user' => $comment->user->only('id', 'name'),
                    'created_at' => $comment->created_at,
                ]),
            ],
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'assignees' => $this->assigneesFor($task->project),
            'can' => [
                'update' => $request->user()->can('update', $task),
                'delete' => $request->user()->can('delete', $task),
            ],
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $task = new Task($data);
        $this->authorize('create', $task);

        $task->created_by = $request->user()->id;
        $task->position = Task::nextPosition($task->project_id, $task->status);
        $task->save();

        return back()->with('success', "Task “{$task->title}” created.");
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return back()->with('success', 'Task updated.');
    }

    /**
     * Board drag-and-drop: move a card to a column and a position within it.
     */
    public function move(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('move', $task);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $status = TaskStatus::from($data['status']);

        // Reindex the destination column so positions stay dense and stable.
        $siblings = Task::where('project_id', $task->project_id)
            ->where('status', $status)
            ->whereKeyNot($task->id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        array_splice($siblings, min($data['position'], count($siblings)), 0, [$task->id]);

        foreach ($siblings as $index => $id) {
            Task::whereKey($id)->update(['position' => $index]);
        }

        // Go through the model so completed_at stays in step with the column.
        $task->update(['status' => $status, 'position' => array_search($task->id, $siblings, true)]);

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $project = $task->project_id;
        $title = $task->title;
        $task->delete();

        return to_route('projects.show', $project)->with('success', "Task “{$title}” deleted.");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assigneesFor(?Project $project)
    {
        if ($project === null) {
            return collect();
        }

        return $project->members()
            ->get(['users.id', 'users.name'])
            ->push($project->owner)
            ->filter()
            ->unique('id')
            ->map->only('id', 'name')
            ->values();
    }
}
