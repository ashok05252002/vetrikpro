import Pill from '@/components/ui/pill';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TaskStatus } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';

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
        <Pill color={stageColor[status]} dot className={className}>
            {stageLabel[status]}
        </Pill>
    );
}

/**
 * The stage as a dropdown, for list rows: choosing one moves the task to the
 * end of that column, exactly as dropping it there on the board would. Only
 * for people who may move it; everyone else sees the badge.
 */
export function StageSelect({ status, moveUrl, reload }: { status: TaskStatus; moveUrl: string; reload?: string[] }) {
    const [processing, setProcessing] = useState(false);

    const change = (to: string) => {
        if (to === status) {
            return;
        }

        router.patch(
            moveUrl,
            { status: to, position: 9999 },
            { preserveScroll: true, only: reload, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Select value={status} onValueChange={change} disabled={processing}>
            <SelectTrigger className="h-8 w-36 text-xs" aria-label="Change stage">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {(Object.keys(stageLabel) as TaskStatus[]).map((value) => (
                    <SelectItem key={value} value={value} className="text-xs">
                        <span className="inline-flex items-center gap-1.5">
                            <span aria-hidden className="size-2 shrink-0 rounded-full" style={{ background: stageColor[value] }} />
                            {stageLabel[value]}
                        </span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
