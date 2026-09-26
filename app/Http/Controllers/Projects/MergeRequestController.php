<?php

namespace App\Http\Controllers\Projects;

use App\Enums\MergeRequestStatus;
use App\Exceptions\MergeRequestConflict;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MergeRequest;
use App\Models\Project;
use App\Policies\MergeRequestPolicy;
use App\Services\MergeRequestService;
use App\Support\Cards;
use App\Support\DevPresenter;
use App\Support\ProjectWorkspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MergeRequestController extends Controller
{
    public function __construct(private readonly MergeRequestService $service) {}

    public function store(Request $request, Project $project, Branch $branch): RedirectResponse
    {
        $this->authorize('requestMerge', $branch);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_branch' => ['required', 'string', 'max:200', 'regex:'.Branch::NAME_PATTERN],
            // Only someone who can actually review may be asked to.
            'reviewer_id' => ['nullable', 'integer', Rule::exists('project_user', 'user_id')->where('project_id', $project->id)->where('role', 'dev_admin')],
        ], ['reviewer_id.exists' => 'Pick one of this project’s dev admins.']);

        try {
            $mergeRequest = $this->service->open($branch, $request->user(), $data);
        } catch (MergeRequestConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('projects.merge-requests.show', [$project, $mergeRequest])
            ->with('success', "{$mergeRequest->reference()} opened. The project’s dev admins can review it now.");
    }

    public function show(Request $request, Project $project, MergeRequest $mergeRequest): Response
    {
        $this->authorize('view', $mergeRequest);

        $mergeRequest->load([
            'branch.tasks' => fn ($q) => $q->with('assignee:id,name')->orderBy('number'),
            'branch.testPoints' => fn ($q) => $q->with(['assignee:id,name', 'creator:id,name', 'task:id,number,title'])->orderBy('number'),
            'requester:id,name',
            'reviewer:id,name',
            'reviewedBy:id,name',
            'mergedBy:id,name',
            'events.user:id,name',
        ]);

        $user = $request->user();
        $branch = $mergeRequest->branch;

        return Inertia::render('projects/merge-request', [
            'project' => ProjectWorkspace::header($project, $user),
            'mergeRequest' => [
                ...DevPresenter::mergeRequestRow($mergeRequest),
                'description' => $mergeRequest->description,
                'branch' => [...$branch->only('id', 'name', 'base_branch', 'status'), 'description' => $branch->description],
                'reviewed_by' => $mergeRequest->reviewedBy?->only('id', 'name'),
                'reviewed_at' => $mergeRequest->reviewed_at,
                'merged_by' => $mergeRequest->mergedBy?->only('id', 'name'),
                'tasks' => $branch->tasks->map(fn ($t) => Cards::task($t)),
                'test_points' => $branch->testPoints->map(fn ($p) => Cards::testPoint($p)),
                'readiness' => DevPresenter::readiness($branch),
                'events' => $mergeRequest->events->map(fn ($e) => [
                    ...$e->only('id', 'action', 'from_status', 'to_status', 'note', 'created_at'),
                    'user' => $e->user?->only('id', 'name'),
                ]),
                'next' => array_map(fn (MergeRequestStatus $s) => $s->value, $mergeRequest->status->next()),
            ],
            'statuses' => MergeRequestStatus::options(),
            'can' => [
                'review' => $user->can('review', $mergeRequest),
                'isReviewer' => MergeRequestPolicy::isReviewer($user, $mergeRequest),
                'resubmit' => $user->can('resubmit', $mergeRequest),
                'close' => $user->can('close', $mergeRequest),
                'comment' => $user->can('comment', $mergeRequest),
            ],
        ]);
    }

    /**
     * Every state change arrives here with the status the user was looking at,
     * so the service can refuse one that has gone stale.
     */
    public function transition(Request $request, Project $project, MergeRequest $mergeRequest): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', Rule::enum(MergeRequestStatus::class)],
            'expected' => ['required', Rule::enum(MergeRequestStatus::class)],
            'note' => [Rule::requiredIf($request->input('to') === MergeRequestStatus::ChangesRequested->value), 'nullable', 'string', 'max:5000'],
        ], ['note.required' => 'Say what needs to change.']);

        $to = MergeRequestStatus::from($data['to']);

        $this->authorizeTransition($request, $mergeRequest, $to);

        try {
            $this->service->transition($mergeRequest, MergeRequestStatus::from($data['expected']), $to, $request->user(), $data['note'] ?? null);
        } catch (MergeRequestConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($to) {
            MergeRequestStatus::Approved => "{$mergeRequest->reference()} approved. Merge it on GitHub, then mark it merged here.",
            MergeRequestStatus::ChangesRequested => "Changes requested on {$mergeRequest->reference()}.",
            MergeRequestStatus::Open => "{$mergeRequest->reference()} is back with the reviewers.",
            MergeRequestStatus::Merged => "{$mergeRequest->reference()} marked as merged.",
            MergeRequestStatus::Closed => "{$mergeRequest->reference()} closed.",
        });
    }

    public function comment(Request $request, Project $project, MergeRequest $mergeRequest): RedirectResponse
    {
        $this->authorize('comment', $mergeRequest);

        $data = $request->validate(['note' => ['required', 'string', 'max:5000']]);
        $this->service->comment($mergeRequest, $request->user(), $data['note']);

        return back();
    }

    private function authorizeTransition(Request $request, MergeRequest $mergeRequest, MergeRequestStatus $to): void
    {
        $ability = match ($to) {
            MergeRequestStatus::Approved, MergeRequestStatus::ChangesRequested, MergeRequestStatus::Merged => 'review',
            MergeRequestStatus::Open => 'resubmit',
            MergeRequestStatus::Closed => 'close',
        };

        if ($request->user()->cannot($ability, $mergeRequest)) {
            throw new AuthorizationException($ability === 'review' && $mergeRequest->requested_by === $request->user()->id
                ? 'You cannot review your own merge request.'
                : 'You cannot do that on this merge request.');
        }
    }
}
