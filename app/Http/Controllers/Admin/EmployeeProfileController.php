<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectMemberRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccessRequest;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\EmployeeProfile;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tabs of a staff profile beyond Overview (which is EmployeeController@show).
 * Each tab is a route of its own and loads only what it shows.
 */
class EmployeeProfileController extends Controller
{
    public function documents(Request $request, Employee $employee): Response
    {
        $documents = $employee->documents()
            ->with(['uploader:id,name', 'type:id,name'])
            ->when($request->integer('type'), fn ($query, int $type) => $query->where('document_type_id', $type))
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query
                ->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('original_name', 'like', "%{$search}%")))
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EmployeeDocument $document) => [
                ...$document->only('id', 'document_type_id', 'title', 'original_name', 'mime_type', 'size', 'expires_at'),
                'type_label' => $document->type?->name,
                'is_expired' => $document->isExpired(),
                'uploaded_by' => $document->uploader?->name,
                'uploaded_at' => $document->created_at->toDateString(),
            ]);

        return Inertia::render('admin/employees/documents', [
            'employee' => EmployeeProfile::header($employee, $request->user()),
            'documents' => $documents,
            'types' => DocumentType::options(),
            'filters' => $request->only('type', 'search'),
        ]);
    }

    public function projects(Request $request, Employee $employee): Response
    {
        $user = $employee->user;

        $projects = Project::query()
            ->where(fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->whereKey($user->id)))
            ->with(['owner:id,name', 'members' => fn ($m) => $m->whereKey($user->id)])
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
                'tasks as their_open_tasks_count' => fn ($query) => $query->where('assigned_to', $user->id)->where('status', '!=', TaskStatus::Done),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Project $project) => [
                ...$project->only('id', 'name', 'code', 'status', 'due_date'),
                'owner' => $project->owner?->only('id', 'name'),
                'is_owner' => $project->owner_id === $user->id,
                'project_role' => $project->members->first()?->pivot->role,
                'progress' => $project->progress(),
                'their_open_tasks_count' => $project->their_open_tasks_count,
            ]);

        return Inertia::render('admin/employees/projects', [
            'employee' => EmployeeProfile::header($employee, $request->user()),
            'projects' => $projects,
            'roles' => ProjectMemberRole::options(),
            // The project opened on this page: their tasks in it, shown in place.
            'selected' => fn () => $this->selectedProject($request, $user),
        ]);
    }

    /**
     * The tasks this person holds on one of their projects, for the Projects
     * tab to show without leaving the profile. Null when no project is open or
     * it isn't one of theirs.
     *
     * @return array<string, mixed>|null
     */
    private function selectedProject(Request $request, User $user): ?array
    {
        $project = Project::query()
            ->whereKey($request->integer('project'))
            ->where(fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->whereKey($user->id)))
            ->first(['id', 'name', 'code']);

        if ($project === null) {
            return null;
        }

        $tasks = $project->tasks()
            ->where('assigned_to', $user->id)
            ->with(['creator:id,name', 'assigner:id,name'])
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date')
            ->get()
            ->map(fn (Task $task) => [
                ...$task->only('id', 'title', 'status', 'priority', 'due_date'),
                'reference' => $task->reference(),
                'is_overdue' => $task->isOverdue(),
                'creator' => $task->creator?->only('id', 'name'),
                'assigner' => $task->assigner?->only('id', 'name'),
            ]);

        return [...$project->only('id', 'name', 'code'), 'tasks' => $tasks];
    }

    public function tasks(Request $request, Employee $employee): Response
    {
        $tasks = $employee->user->assignedTasks()
            ->with('project:id,name,code')
            ->when($request->string('status')->value(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            // Open work first, soonest due first; finished work sinks.
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderByRaw('due_date IS NULL, due_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Task $task) => [
                ...$task->only('id', 'title', 'status', 'priority', 'due_date'),
                'project' => $task->project?->only('id', 'name', 'code'),
                'is_overdue' => $task->isOverdue(),
            ]);

        return Inertia::render('admin/employees/tasks', [
            'employee' => EmployeeProfile::header($employee, $request->user()),
            'tasks' => $tasks,
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'filters' => $request->only('status', 'search'),
        ]);
    }

    public function access(Request $request, Employee $employee): Response
    {
        $viewer = $request->user();
        $user = $employee->user;

        abort_unless($viewer->canGrant($user->permissions()), 403, 'This person has access you do not have.');

        return Inertia::render('admin/employees/access', [
            'employee' => EmployeeProfile::header($employee, $viewer),
            'access' => [
                'role_id' => $user->role_id,
                'overrides' => $user->permissionOverrides()->get()
                    ->mapWithKeys(fn ($override) => [$override->permission => $override->granted ? 'allow' : 'deny']),
                'effective' => $user->permissions(),
            ],
            'roles' => Role::with('permissionRows')->orderBy('name')->get()
                ->filter(fn (Role $role) => $viewer->canAssignRole($role))
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_super' => $role->is_super,
                    'permissions' => $role->permissionKeys(),
                ])
                ->values(),
            'permissionGroups' => Permissions::forEditor(),
        ]);
    }

    public function updateAccess(UpdateAccessRequest $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        DB::transaction(function () use ($request, $user) {
            $user->update(['role_id' => $request->integer('role_id')]);
            $user->syncPermissionOverrides($request->overrides());
        });

        return back()->with('success', "Access for {$user->name} updated.");
    }
}
