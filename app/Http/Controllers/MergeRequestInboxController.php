<?php

namespace App\Http\Controllers;

use App\Enums\MergeRequestStatus;
use App\Models\MergeRequest;
use App\Models\Project;
use App\Support\DevPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Merge requests across every project the viewer is on: what is waiting for
 * their review, what they asked for, or everything they can see.
 */
class MergeRequestInboxController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $scope = in_array($request->string('scope')->value(), ['mine', 'all'], true) ? $request->string('scope')->value() : 'review';
        $reviewsAnything = $user->can('merge_requests.review');

        $visible = fn (Builder $q) => $user->can('projects.view') ? $q : $q->where(fn ($w) => $w
            ->whereHas('project', fn ($p) => $p->where('owner_id', $user->id))
            ->orWhereHas('project.members', fn ($m) => $m->whereKey($user->id)));

        $awaitingReview = fn (Builder $q) => $q
            ->whereIn('status', [MergeRequestStatus::Open->value, MergeRequestStatus::Approved->value])
            ->where('requested_by', '!=', $user->id)
            ->where(fn ($w) => $w
                ->where('reviewer_id', $user->id)
                ->orWhere(fn ($any) => $any
                    ->whereNull('reviewer_id')
                    ->when(! $reviewsAnything, fn ($d) => $d->whereHas('project.members', fn ($m) => $m->whereKey($user->id)->where('project_user.role', 'dev_admin')))));

        $mergeRequests = MergeRequest::query()
            ->with(['project:id,name,code', 'branch:id,name', 'requester:id,name', 'reviewer:id,name'])
            ->tap($visible)
            ->when($scope === 'review', $awaitingReview)
            ->when($scope === 'mine', fn ($q) => $q->where('requested_by', $user->id))
            ->when($request->string('search')->trim()->value(), fn ($q, string $s) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$s}%")
                ->orWhereHas('branch', fn ($b) => $b->where('name', 'like', "%{$s}%"))))
            ->when($request->string('status')->value(), fn ($q, string $s) => $q->where('status', $s))
            ->when($request->integer('project'), fn ($q, int $id) => $q->where('project_id', $id))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MergeRequest $mr) => DevPresenter::mergeRequestRow($mr));

        return Inertia::render('merge-requests/index', [
            'mergeRequests' => $mergeRequests,
            'scope' => $scope,
            'awaitingCount' => MergeRequest::query()->tap($visible)->tap($awaitingReview)->count(),
            'statuses' => MergeRequestStatus::options(),
            'projects' => Project::query()
                ->when(! $user->can('projects.view'), fn ($q) => $q->where(fn ($w) => $w->where('owner_id', $user->id)->orWhereHas('members', fn ($m) => $m->whereKey($user->id))))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name]),
            'filters' => $request->only('search', 'status', 'project'),
        ]);
    }
}
