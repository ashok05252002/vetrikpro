<?php

namespace App\Services;

use App\Enums\BranchStatus;
use App\Enums\MergeRequestStatus;
use App\Exceptions\MergeRequestConflict;
use App\Models\Branch;
use App\Models\MergeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only way a merge request changes state.
 *
 * Every transition is checked against MergeRequestStatus::next() and applied
 * with a guarded UPDATE … WHERE status = <what the user was looking at>. If
 * two reviewers press Approve at the same moment, one wins and the other is
 * told who got there first — the request can never be approved twice, or
 * merged from a page showing a state it has already left.
 */
final class MergeRequestService
{
    /**
     * @param  array{title: string, description?: string|null, target_branch: string, reviewer_id?: int|null}  $data
     */
    public function open(Branch $branch, User $by, array $data): MergeRequest
    {
        return DB::transaction(function () use ($branch, $by, $data) {
            // Serialise requests on this branch so two clicks cannot open two.
            $locked = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BranchStatus::Active) {
                throw new MergeRequestConflict("{$locked->name} is {$locked->status->label()}; it cannot be merged again.");
            }

            $live = $locked->mergeRequests()->whereIn('status', MergeRequestStatus::live())->first();

            if ($live !== null) {
                throw new MergeRequestConflict("{$locked->name} already has {$live->reference()} in progress.");
            }

            $mergeRequest = MergeRequest::create([
                ...$data,
                'project_id' => $locked->project_id,
                'branch_id' => $locked->id,
                'requested_by' => $by->id,
            ]);

            $mergeRequest->events()->create([
                'user_id' => $by->id,
                'action' => 'opened',
                'to_status' => MergeRequestStatus::Open->value,
                'note' => $data['description'] ?? null,
            ]);

            return $mergeRequest->refresh();
        });
    }

    /**
     * @param  MergeRequestStatus  $expected  The status the user saw when they acted.
     */
    public function transition(MergeRequest $mergeRequest, MergeRequestStatus $expected, MergeRequestStatus $to, User $by, ?string $note = null): void
    {
        if (! $expected->canBecome($to)) {
            throw new MergeRequestConflict("A request that is {$expected->label()} cannot be moved to {$to->label()}.");
        }

        DB::transaction(function () use ($mergeRequest, $expected, $to, $by, $note) {
            $now = now();

            $changes = ['status' => $to->value, 'updated_at' => $now] + match ($to) {
                MergeRequestStatus::Approved, MergeRequestStatus::ChangesRequested => ['reviewed_by' => $by->id, 'reviewed_at' => $now],
                MergeRequestStatus::Merged => ['merged_by' => $by->id, 'merged_at' => $now],
                MergeRequestStatus::Closed => ['closed_at' => $now],
                MergeRequestStatus::Open => [],
            };

            $applied = MergeRequest::query()
                ->whereKey($mergeRequest->id)
                ->where('status', $expected->value)
                ->update($changes);

            if ($applied === 0) {
                throw new MergeRequestConflict($this->staleMessage($mergeRequest->fresh()));
            }

            if ($to === MergeRequestStatus::Merged) {
                $mergeRequest->branch()->update(['status' => BranchStatus::Merged->value, 'merged_at' => $now]);
            }

            $mergeRequest->events()->create([
                'user_id' => $by->id,
                'action' => match ($to) {
                    MergeRequestStatus::Approved => 'approved',
                    MergeRequestStatus::ChangesRequested => 'changes_requested',
                    MergeRequestStatus::Open => 'resubmitted',
                    MergeRequestStatus::Merged => 'merged',
                    MergeRequestStatus::Closed => 'closed',
                },
                'from_status' => $expected->value,
                'to_status' => $to->value,
                'note' => $note,
            ]);
        });

        $mergeRequest->refresh();
    }

    public function comment(MergeRequest $mergeRequest, User $by, string $note): void
    {
        $mergeRequest->events()->create(['user_id' => $by->id, 'action' => 'commented', 'note' => $note]);
    }

    private function staleMessage(MergeRequest $current): string
    {
        // reorder(): the relation sorts oldest-first, which latest() alone would not override.
        $last = $current->events()->reorder()->whereNotNull('to_status')->latest('id')->with('user:id,name')->first();
        $who = $last?->user?->name;

        return "{$current->reference()} is already {$current->status->label()}"
            .($who ? " — {$who} got there first" : '')
            .'. The page has been refreshed.';
    }
}
