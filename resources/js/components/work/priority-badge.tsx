import { cn } from '@/lib/utils';
import type { TaskPriority } from '@/types';
import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, ArrowDown, ArrowUp, Minus } from 'lucide-react';

/**
 * Urgency uses the reserved status palette. Colour never carries the meaning
 * alone — every badge ships an icon and the word.
 */
const spec: Record<TaskPriority, { label: string; color: string; icon: LucideIcon }> = {
    low: { label: 'Low', color: 'var(--status-good)', icon: ArrowDown },
    medium: { label: 'Medium', color: 'var(--status-warning)', icon: Minus },
    high: { label: 'High', color: 'var(--status-serious)', icon: ArrowUp },
    urgent: { label: 'Urgent', color: 'var(--status-critical)', icon: AlertTriangle },
};

export default function PriorityBadge({ priority, className }: { priority: TaskPriority; className?: string }) {
    const { label, color, icon: Icon } = spec[priority];

    return (
        <span className={cn('text-foreground inline-flex items-center gap-1.5 text-xs font-medium', className)}>
            <Icon className="size-3.5 shrink-0" style={{ color }} />
            {label}
        </span>
    );
}

export { spec as prioritySpec };
