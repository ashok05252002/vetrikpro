import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import TestStatusBadge from '@/components/work/test-status';
import type { Readiness, TaskSummary, TestPointSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, X } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Whether a branch's linked work is finished and tested — what a reviewer
 * wants to know before merging.
 */
export function ReadinessNote({ readiness, taskCount, testCount }: { readiness: Readiness; taskCount: number; testCount: number }) {
    if (taskCount + testCount === 0) {
        return (
            <Alert>
                <AlertTriangle className="size-4" />
                <AlertDescription>No task or testing points are linked, so there is nothing to check this branch against.</AlertDescription>
            </Alert>
        );
    }

    if (readiness.ready) {
        return (
            <Alert>
                <CheckCircle2 className="size-4" style={{ color: 'var(--status-good)' }} />
                <AlertDescription>Every linked task is done and every testing point has passed.</AlertDescription>
            </Alert>
        );
    }

    const notRun = readiness.tests_not_passed - readiness.tests_failed;
    const parts = [
        readiness.tasks_open > 0 && `${readiness.tasks_open} linked task${readiness.tasks_open === 1 ? ' is' : 's are'} not done`,
        readiness.tests_failed > 0 && `${readiness.tests_failed} testing point${readiness.tests_failed === 1 ? ' has' : 's have'} failed`,
        notRun > 0 && `${notRun} testing point${notRun === 1 ? ' has' : 's have'} not passed yet`,
    ].filter(Boolean);

    return (
        <Alert variant={readiness.tests_failed > 0 ? 'destructive' : 'default'}>
            <AlertTriangle className="size-4" />
            <AlertDescription>Not ready: {parts.join('; ')}.</AlertDescription>
        </Alert>
    );
}

function Row({
    reference,
    title,
    href,
    status,
    priority,
    onRemove,
}: {
    reference: string;
    title: string;
    href: string;
    status: ReactNode;
    priority: ReactNode;
    onRemove?: () => void;
}) {
    return (
        <li className="flex items-center gap-3 px-4 py-2.5">
            <span className="text-muted-foreground w-14 shrink-0 font-mono text-xs">{reference}</span>
            <Link href={href} className="min-w-0 flex-1 truncate text-sm hover:underline">
                {title}
            </Link>
            <span className="hidden sm:inline">{priority}</span>
            <span className="w-32 shrink-0">{status}</span>
            {onRemove && (
                <Button variant="ghost" size="sm" className="size-7 p-0" onClick={onRemove} aria-label={`Unlink ${reference}`}>
                    <X className="size-4" />
                </Button>
            )}
        </li>
    );
}

function Section({ title, count, action, children }: { title: string; count: number; action?: ReactNode; children: ReactNode }) {
    return (
        <section className="overflow-hidden rounded-xl border">
            <header className="bg-muted/50 flex items-center justify-between gap-2 px-4 py-2">
                <h3 className="text-sm font-medium">
                    {title} <span className="text-muted-foreground tabular-nums">({count})</span>
                </h3>
                {action}
            </header>
            {count === 0 ? (
                <p className="text-muted-foreground px-4 py-6 text-center text-sm">None linked.</p>
            ) : (
                <ul className="divide-y">{children}</ul>
            )}
        </section>
    );
}

export function LinkedTasks({ tasks, onRemove, action }: { tasks: TaskSummary[]; onRemove?: (task: TaskSummary) => void; action?: ReactNode }) {
    return (
        <Section title="Task points" count={tasks.length} action={action}>
            {tasks.map((task) => (
                <Row
                    key={task.id}
                    reference={task.reference ?? `#${task.id}`}
                    title={task.title}
                    href={route('tasks.show', task.id)}
                    status={<StageBadge status={task.status} />}
                    priority={<PriorityBadge priority={task.priority} />}
                    onRemove={onRemove ? () => onRemove(task) : undefined}
                />
            ))}
        </Section>
    );
}

export function LinkedTestPoints({
    points,
    onRemove,
    action,
}: {
    points: TestPointSummary[];
    onRemove?: (point: TestPointSummary) => void;
    action?: ReactNode;
}) {
    return (
        <Section title="Testing points" count={points.length} action={action}>
            {points.map((point) => (
                <Row
                    key={point.id}
                    reference={point.reference}
                    title={point.title}
                    href={route('projects.testing.show', [point.project_id, point.id])}
                    status={<TestStatusBadge status={point.status} />}
                    priority={<PriorityBadge priority={point.priority} />}
                    onRemove={onRemove ? () => onRemove(point) : undefined}
                />
            ))}
        </Section>
    );
}
