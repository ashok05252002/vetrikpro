import { cn } from '@/lib/utils';
import type { BranchStatus, MergeRequestStatus } from '@/types';
import type { LucideIcon } from 'lucide-react';
import { CheckCircle2, GitBranch, GitMerge, GitPullRequest, MessageSquareWarning, XCircle } from 'lucide-react';

/**
 * Merge-request states each carry an icon and the word, never colour alone.
 * Approved and changes-requested take the reserved status palette because
 * that is what they are: good to go, and needs attention.
 */
export const mergeStatusSpec: Record<MergeRequestStatus, { label: string; color: string; icon: LucideIcon }> = {
    open: { label: 'Awaiting review', color: 'var(--stage-in-progress)', icon: GitPullRequest },
    changes_requested: { label: 'Changes requested', color: 'var(--status-serious)', icon: MessageSquareWarning },
    approved: { label: 'Approved', color: 'var(--status-good)', icon: CheckCircle2 },
    merged: { label: 'Merged', color: 'var(--stage-done)', icon: GitMerge },
    closed: { label: 'Closed', color: 'var(--muted-foreground)', icon: XCircle },
};

export const branchStatusSpec: Record<BranchStatus, { label: string; color: string; icon: LucideIcon }> = {
    active: { label: 'Active', color: 'var(--stage-in-progress)', icon: GitBranch },
    merged: { label: 'Merged', color: 'var(--stage-done)', icon: GitMerge },
    closed: { label: 'Closed', color: 'var(--muted-foreground)', icon: XCircle },
};

function StatusBadge({ spec, className }: { spec: { label: string; color: string; icon: LucideIcon }; className?: string }) {
    const Icon = spec.icon;

    return (
        <span className={cn('text-foreground inline-flex items-center gap-1.5 text-xs font-medium whitespace-nowrap', className)}>
            <Icon aria-hidden className="size-3.5 shrink-0" style={{ color: spec.color }} />
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
        <code className={cn('bg-muted inline-flex max-w-full items-center gap-1 truncate rounded px-1.5 py-0.5 font-mono text-xs', className)}>
            <GitBranch aria-hidden className="size-3 shrink-0" />
            <span className="truncate">{name}</span>
        </code>
    );
}
