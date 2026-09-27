import { cn } from '@/lib/utils';
import type { BranchStatus, MergeRequestStatus } from '@/types';
import type { LucideIcon } from 'lucide-react';
import { CheckCircle2, GitBranch, GitMerge, GitPullRequest, MessageSquareWarning, XCircle } from 'lucide-react';

/**
 * Git states in GitHub's own colours, drawn as github.com draws them: a solid
 * pill with an icon and the word — open green, merged purple, closed red,
 * changes requested amber. `color` is the icon-only colour for dots and
 * timelines; `bg` the pill.
 */
type Spec = { label: string; color: string; bg: string; icon: LucideIcon };

export const mergeStatusSpec: Record<MergeRequestStatus, Spec> = {
    open: { label: 'Awaiting review', color: 'var(--gh-open)', bg: 'var(--gh-open-bg)', icon: GitPullRequest },
    changes_requested: { label: 'Changes requested', color: 'var(--gh-attention)', bg: 'var(--gh-attention-bg)', icon: MessageSquareWarning },
    approved: { label: 'Approved', color: 'var(--gh-open)', bg: 'var(--gh-open-bg)', icon: CheckCircle2 },
    merged: { label: 'Merged', color: 'var(--gh-merged)', bg: 'var(--gh-merged-bg)', icon: GitMerge },
    closed: { label: 'Closed', color: 'var(--gh-closed)', bg: 'var(--gh-closed-bg)', icon: XCircle },
};

export const branchStatusSpec: Record<BranchStatus, Spec> = {
    active: { label: 'Active', color: 'var(--gh-open)', bg: 'var(--gh-open-bg)', icon: GitBranch },
    merged: { label: 'Merged', color: 'var(--gh-merged)', bg: 'var(--gh-merged-bg)', icon: GitMerge },
    closed: { label: 'Closed', color: 'var(--gh-draft)', bg: 'var(--gh-draft-bg)', icon: XCircle },
};

function StatusBadge({ spec, className }: { spec: Spec; className?: string }) {
    const Icon = spec.icon;

    return (
        <span
            className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap text-white', className)}
            style={{ background: spec.bg }}
        >
            <Icon aria-hidden className="size-3.5 shrink-0" />
            {spec.label}
        </span>
    );
}

export function MergeStatusBadge({ status, className }: { status: MergeRequestStatus; className?: string }) {
    return <StatusBadge spec={mergeStatusSpec[status]} className={className} />;
}

export function BranchStatusBadge({ status, className }: { status: BranchStatus; className?: string }) {
    return <StatusBadge spec={branchStatusSpec[status]} className={className} />;
}

export function BranchName({ name, className }: { name: string; className?: string }) {
    return (
        <code
            className={cn('inline-flex max-w-full items-center gap-1 truncate rounded-md px-1.5 py-0.5 font-mono text-xs', className)}
            style={{ background: 'var(--gh-branch-bg)', color: 'var(--gh-branch-fg)' }}
        >
            <GitBranch aria-hidden className="size-3 shrink-0" />
            <span className="truncate">{name}</span>
        </code>
    );
}
