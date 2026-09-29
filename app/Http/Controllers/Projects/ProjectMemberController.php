<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectMemberRole;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectRoles;
use App\Support\ProjectWorkspace;
use App\Support\UserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Members are added, re-roled and removed one change at a time rather than
 * by saving a whole list, so two people managing the same team at once never
 * overwrite each other's changes.
 */
class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $members = UserDirectory::filter($project->members(), $request)
            ->with(UserDirectory::with())
            ->withCount(['assignedTasks as open_tasks_count' => fn ($query) => $query
                ->where('project_id', $project->id)
                ->where('status', '!=', TaskStatus::Done)])
            // The closure receives the underlying builder, not the relation, so
            // the pivot column is named directly rather than via wherePivot().
            ->when($request->string('role')->value(), fn ($query, string $role) => $query->where('project_user.role', $role))
            ->orderByRaw("CASE WHEN project_user.role = 'dev_admin' THEN 0 ELSE 1 END")
            ->orderBy('users.name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                ...UserDirectory::row($user),
                'role' => $user->pivot->role,
                'joined_at' => $user->pivot->created_at?->toDateString(),
                'open_tasks_count' => $user->open_tasks_count,
            ]);

        return Inertia::render('projects/members', [
            'project' => ProjectWorkspace::header($project, $request->user()),
            'members' => $members,
            'roles' => ProjectMemberRole::options(),
            'filters' => $request->only('search', 'department', 'designation', 'role'),
            ...UserDirectory::filterOptions(),
        ]);
    }

    /**
     * People who could be added: active accounts not already on the project.
     */
    public function candidates(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $users = UserDirectory::filter(User::query(), $request)
            ->with(UserDirectory::with())
            ->where('is_active', true)
            ->whereDoesntHave('projects', fn ($query) => $query->whereKey($project->id))
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (User $user) => UserDirectory::row($user));

        return response()->json(['data' => $users]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id'), ProjectRoles::eligibleRule($request->input('role'))],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ]);

        // Only people not already on the project are attached. Anyone already
        // there keeps the role they have — syncWithoutDetaching would quietly
        // overwrite it, turning a dev admin back into a member.
        $existing = $project->members()->whereIn('users.id', $data['user_ids'])->pluck('users.id')->all();
        $added = array_values(array_diff($data['user_ids'], $existing));

        $project->members()->attach(array_fill_keys($added, ['role' => $data['role']]));

        $count = count($added);

        return back()->with('success', $count === 0
            ? 'Everyone selected was already on the project.'
            : "{$count} ".str('member')->plural($count).' added.');
    }

    public function update(Request $request, Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        abort_unless($project->hasMember($user), 404);

        $data = $request->validate(['role' => ['required', Rule::enum(ProjectMemberRole::class)]]);

        if (! ProjectRoles::isEligible($user, ProjectMemberRole::from($data['role']))) {
            return back()->with('error', "{$user->name} can't be given “".ProjectMemberRole::from($data['role'])->label().'”: their role doesn\'t allow it. Change that in Roles & access.');
        }

        $project->members()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('success', "{$user->name} is now ".ProjectMemberRole::from($data['role'])->label().'.');
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $project->members()->detach($user->id);

        return back()->with('success', "{$user->name} was removed from the project. Tasks assigned to them keep their assignee.");
    }
}
