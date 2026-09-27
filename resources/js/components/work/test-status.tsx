import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { TestPointStatus } from '@/types';
import { router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { CheckCircle2, Circle, CircleDot, FlaskConical, RotateCcw } from 'lucide-react';
import { useState } from 'react';

/**
 * The three working stages reuse the task stage ramp (light -> dark as the
 * bug moves along); Repeated and Closed are verdicts and use the reserved
 * status palette. Every mark ships an icon and the word, so colour never
 * carries the meaning alone.
 */
export const testStatusSpec: Record<TestPointStatus, { label: string; color: string; icon: LucideIcon }> = {
    open: { label: 'Open', color: 'var(--stage-todo)', icon: Circle },
    in_progress: { label: 'In progress', color: 'var(--stage-in-progress)', icon: CircleDot },
    ready_for_test: { label: 'Ready for test', color: 'var(--stage-in-review)', icon: FlaskConical },
    repeated: { label: 'Repeated', color: 'var(--status-critical)', icon: RotateCcw },
    closed: { label: 'Closed', color: 'var(--status-good)', icon: CheckCircle2 },
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

/**
 * The status as a dropdown, for list rows: choosing a step moves the bug to
 * the end of that column, exactly as dropping it there on the board would.
 * Shown only to people who may move it; everyone else sees the badge.
 */
export function TestStatusSelect({ status, moveUrl, reload }: { status: TestPointStatus; moveUrl: string; reload?: string[] }) {
    const [processing, setProcessing] = useState(false);

    const change = (to: string) => {
        if (to === status) {
            return;
        }

        router.patch(
            moveUrl,
            { status: to, position: 9999 },
            {
                preserveScroll: true,
                only: reload,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Select value={status} onValueChange={change} disabled={processing}>
            <SelectTrigger className="h-8 w-40 text-xs" aria-label="Change status">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {(Object.keys(testStatusSpec) as TestPointStatus[]).map((value) => (
                    <SelectItem key={value} value={value} className="text-xs">
                        <span className="inline-flex items-center gap-1.5">
                            <TestStatusMark status={value} className="size-3.5" />
                            {testStatusSpec[value].label}
                        </span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
