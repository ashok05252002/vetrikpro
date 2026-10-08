<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
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
            'pipelineOrgWide' => $user->isSuper(),
            'myTasks' => $this->myTasks($user),
            'projects' => $this->projectProgress($user),
            'orgWide' => $user->can('projects.view'),
            'peopleStats' => $user->can('employees.view'),
        ]);
    }

    /**
     * Headline counts. Someone who sees every project (admin, HR) gets the
     * organisation's figures, each linking to the organisation-wide list, plus
     * their own; everyone else gets only their own work. People figures only
     * go to those who manage accounts.
     */
    private function stats(User $user): array
    {
        $scoped = $this->visibleTasks($user);
        $mine = Task::query()->where('assigned_to', $user->id);

        $stats = [
            'openTasks' => (clone $scoped)->where('status', '!=', TaskStatus::Done)->count(),
            'overdueTasks' => (clone $scoped)->overdue()->count(),
            'dueThisWeek' => (clone $scoped)->dueThisWeek()->count(),
            'activeProjects' => $this->visibleProjects($user)->where('status', 'active')->count(),
            'mine' => [
                'open' => (clone $mine)->where('status', '!=', TaskStatus::Done)->count(),
                'overdue' => (clone $mine)->overdue()->count(),
            ],
        ];

        if ($user->can('employees.view')) {
            $stats += [
                'employees' => Employee::current()->count(),
                'departments' => Department::count(),
                'admins' => User::whereHas('role', fn ($query) => $query->where('is_super', true))->count(),
            ];
        }

        return $stats;
    }

    /**
     * Task counts per workflow stage, in stage order — the dashboard renders
     * this as a single part-to-whole bar. Only the Administrator sees the whole
     * organisation's; everyone else, whatever projects they may see, gets the
     * tasks assigned to them.
     */
    private function taskPipeline(User $user): array
    {
        $counts = Task::query()
            ->unless($user->isSuper(), fn ($query) => $query->where('assigned_to', $user->id))
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
                // Plain Y-m-d: a Carbon reaches the page as a UTC ISO string, a day early in Indian time.
                'due_date' => $task->due_date?->toDateString(),
                'reference' => $task->reference(),
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
                'tasks as my_open_tasks_count' => fn ($query) => $query->where('assigned_to', $user->id)->where('status', '!=', TaskStatus::Done),
            ])
            ->orderBy('name')
            ->take(5)
            ->get()
            ->map(fn (Project $project) => [
                ...$project->only('id', 'name', 'code', 'due_date'),
                // Plain Y-m-d: a Carbon reaches the page as a UTC ISO string, a day early in Indian time.
                'due_date' => $project->due_date?->toDateString(),
                'tasks_count' => $project->tasks_count,
                'done_tasks_count' => $project->done_tasks_count,
                'my_open_tasks_count' => $project->my_open_tasks_count,
                'progress' => $project->progress(),
            ]);
    }

    /**
     * Admin and HR see the whole organisation; everyone else sees only the
     * projects they own or belong to.
     */
    private function visibleProjects(User $user)
    {
        return Project::query()->unless($user->can('projects.view'), fn ($query) => $query
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->whereKey($user->id))));
    }

    /**
     * The tasks the headline figures count: every task for someone who sees
     * every project, otherwise only the tasks assigned to this person — the
     * same set their My tasks list shows, so a tile and its list agree.
     */
    private function visibleTasks(User $user)
    {
        return Task::query()->unless($user->can('projects.view'), fn ($query) => $query->where('assigned_to', $user->id));
    }
}
