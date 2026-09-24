import { cn } from '@/lib/utils';
import type { TaskStatus } from '@/types';

export const stageColor: Record<TaskStatus, string> = {
    todo: 'var(--stage-todo)',
    in_progress: 'var(--stage-in-progress)',
    in_review: 'var(--stage-in-review)',
    done: 'var(--stage-done)',
};

export const stageLabel: Record<TaskStatus, string> = {
    todo: 'To do',
    in_progress: 'In progress',
    in_review: 'In review',
    done: 'Done',
};

/**
 * The coloured dot carries identity; the text stays in an ink token, never the
 * data colour.
 */
export default function StageBadge({ status, className }: { status: TaskStatus; className?: string }) {
    return (
        <span className={cn('text-foreground inline-flex items-center gap-1.5 text-xs font-medium', className)}>
            <span aria-hidden className="size-2 shrink-0 rounded-full" style={{ background: stageColor[status] }} />
            {stageLabel[status]}
        </span>
    );
}
