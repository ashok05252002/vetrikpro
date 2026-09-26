import { cn } from '@/lib/utils';
import type { TestResult } from '@/types';
import type { LucideIcon } from 'lucide-react';
import { Ban, CheckCircle2, CircleDashed, XCircle } from 'lucide-react';

/**
 * A point's outcome within one run. Same palette as the testing board —
 * passed and failed are the reserved status colours — plus Blocked in the
 * warning colour. Every mark carries its word, never colour alone.
 */
export const testResultSpec: Record<TestResult, { label: string; color: string; icon: LucideIcon }> = {
    not_run: { label: 'Not run', color: 'var(--stage-todo)', icon: CircleDashed },
    passed: { label: 'Passed', color: 'var(--status-good)', icon: CheckCircle2 },
    failed: { label: 'Failed', color: 'var(--status-critical)', icon: XCircle },
    blocked: { label: 'Blocked', color: 'var(--status-warning)', icon: Ban },
};

export const RESULT_ORDER: TestResult[] = ['passed', 'failed', 'blocked', 'not_run'];

export function TestResultMark({ result, className }: { result: TestResult; className?: string }) {
    const { color, icon: Icon } = testResultSpec[result];

    return <Icon aria-hidden className={cn('size-4 shrink-0', className)} style={{ color }} />;
}

export default function TestResultBadge({ result, className }: { result: TestResult; className?: string }) {
    return (
        <span className={cn('text-foreground inline-flex items-center gap-1.5 text-xs font-medium', className)}>
            <TestResultMark result={result} className="size-3.5" />
            {testResultSpec[result].label}
        </span>
    );
}

/**
 * How far a run has got: one thin stacked bar (outcomes only; what is not run
 * yet is the empty track) and the counts as words beneath it.
 */
export function RunProgress({ tally, compact = false }: { tally: Record<TestResult, number>; compact?: boolean }) {
    const total = RESULT_ORDER.reduce((sum, r) => sum + (tally[r] ?? 0), 0);
    const done = total - (tally.not_run ?? 0);
    const segments = (['passed', 'failed', 'blocked'] as TestResult[]).filter((r) => tally[r] > 0);

    return (
        <div className="space-y-1.5">
            <div
                className="flex h-1.5 w-full gap-[2px] overflow-hidden rounded-full"
                style={{ background: 'var(--viz-track)' }}
                role="img"
                aria-label={`${done} of ${total} run: ${tally.passed} passed, ${tally.failed} failed, ${tally.blocked} blocked`}
            >
                {segments.map((r) => (
                    <div key={r} style={{ width: `${(tally[r] / Math.max(total, 1)) * 100}%`, background: testResultSpec[r].color }} />
                ))}
            </div>
            <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span className="text-foreground font-medium tabular-nums">
                    {done} of {total} run
                </span>
                {(compact ? (['passed', 'failed', 'blocked'] as TestResult[]) : RESULT_ORDER).map((r) => (
                    <span key={r} className="inline-flex items-center gap-1">
                        <TestResultMark result={r} className="size-3" />
                        <span className="tabular-nums">{tally[r] ?? 0}</span>
                        <span className={cn(compact && 'sr-only')}>{testResultSpec[r].label.toLowerCase()}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}
