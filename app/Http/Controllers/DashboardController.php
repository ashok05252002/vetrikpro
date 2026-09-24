<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'stats' => $this->stats($user),
            'taskPipeline' => $this->taskPipeline($user),
            'myTasks' => $this->myTasks($user),
            'projects' => $this->projectProgress($user),
            'managesPeople' => $user->managesPeople(),
        ]);
    }

    /**
     * Headline counts. People figures are only meaningful to admin/HR, so
     * everyone else gets the work-focused set.
     */
    private function stats(User $user): array
    {
        $scoped = $this->visibleTasks($user);

        $stats = [
            'openTasks' => (clone $scoped)->where('status', '!=', TaskStatus::Done)->count(),
            'overdueTasks' => (clone $scoped)->overdue()->count(),
            'dueThisWeek' => (clone $scoped)
                ->where('status', '!=', TaskStatus::Done)
                ->whereBetween('due_date', [today(), today()->addWeek()])
                ->count(),
            'activeProjects' => $this->visibleProjects($user)->where('status', 'active')->count(),
        ];

        if ($user->managesPeople()) {
            $stats += [
                'users' => User::count(),
                'employees' => Employee::count(),
                'departments' => Department::count(),
                'admins' => User::where('role', UserRole::Admin)->count(),
            ];
        }

        return $stats;
    }

    /**
     * Task counts per workflow stage, in stage order — the dashboard renders
     * this as a single part-to-whole bar.
     */
    private function taskPipeline(User $user): array
    {
        $counts = $this->visibleTasks($user)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(TaskStatus::cases())
            ->map(fn (TaskStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }

    private function myTasks(User $user)
    {
        return Task::query()
            ->with('project:id,name,code')
            ->where('assigned_to', $user->id)
            ->where('status', '!=', TaskStatus::Done)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date')
            ->take(6)
            ->get()
            ->map(fn (Task $task) => [
                ...$task->only('id', 'title', 'status', 'priority', 'due_date'),
                'project' => $task->project?->only('id', 'name', 'code'),
                'is_overdue' => $task->isOverdue(),
            ]);
    }

    private function projectProgress(User $user)
    {
        return $this->visibleProjects($user)
            ->where('status', 'active')
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
            ])
            ->orderBy('name')
            ->take(5)
            ->get()
            ->map(fn (Project $project) => [
                ...$project->only('id', 'name', 'code', 'due_date'),
                'tasks_count' => $project->tasks_count,
                'done_tasks_count' => $project->done_tasks_count,
                'progress' => $project->progress(),
            ]);
    }

    /**
     * Admin and HR see the whole organisation; everyone else sees only the
     * projects they own or belong to.
     */
    private function visibleProjects(User $user)
    {
        return Project::query()->unless($user->managesPeople(), fn ($query) => $query
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->whereKey($user->id))));
    }

    private function visibleTasks(User $user)
    {
        return Task::query()->unless($user->managesPeople(), fn ($query) => $query
            ->where(fn ($q) => $q
                ->where('assigned_to', $user->id)
                ->orWhereHas('project', fn ($p) => $p
                    ->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($user->id)))));
    }
}
