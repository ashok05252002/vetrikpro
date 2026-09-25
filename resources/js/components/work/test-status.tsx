import { cn } from '@/lib/utils';
import type { TestPointStatus } from '@/types';
import type { LucideIcon } from 'lucide-react';
import { CheckCircle2, Circle, FlaskConical, XCircle } from 'lucide-react';

/**
 * The two waiting stages reuse the task stage ramp; the two outcomes use the
 * reserved status palette. Every mark ships an icon and the word, so colour
 * never carries pass/fail alone.
 */
export const testStatusSpec: Record<TestPointStatus, { label: string; color: string; icon: LucideIcon }> = {
    to_test: { label: 'To test', color: 'var(--stage-todo)', icon: Circle },
    testing: { label: 'In testing', color: 'var(--stage-in-progress)', icon: FlaskConical },
    passed: { label: 'Passed', color: 'var(--status-good)', icon: CheckCircle2 },
    failed: { label: 'Failed', color: 'var(--status-critical)', icon: XCircle },
};

export function TestStatusMark({ status, className }: { status: TestPointStatus; className?: string }) {
    const { color, icon: Icon } = testStatusSpec[status];

    return <Icon aria-hidden className={cn('size-4 shrink-0', className)} style={{ color }} />;
}

export default function TestStatusBadge({ status, className }: { status: TestPointStatus; className?: string }) {
    return (
        <span className={cn('text-foreground inline-flex items-center gap-1.5 text-xs font-medium', className)}>
            <TestStatusMark status={status} className="size-3.5" />
            {testStatusSpec[status].label}
        </span>
    );
}
