import PriorityBadge from '@/components/work/priority-badge';
import UserAvatar from '@/components/work/user-avatar';
import { CardPeople } from '@/components/work/work-people';
import { cn } from '@/lib/utils';
import type { TestPointSummary } from '@/types';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link } from '@inertiajs/react';
import { GripVertical, Link2 } from 'lucide-react';

export default function TestPointCard({
    point,
    draggable = true,
    overlay = false,
}: {
    point: TestPointSummary;
    draggable?: boolean;
    overlay?: boolean;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: point.id, disabled: !draggable });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            className={cn('group bg-card rounded-lg border p-3 shadow-xs', isDragging && 'opacity-40', overlay && 'rotate-1 shadow-lg')}
        >
            <div className="flex items-start gap-2">
                {draggable && (
                    <button
                        type="button"
                        className="text-muted-foreground/50 focus-visible:ring-ring -ml-1 cursor-grab touch-none rounded p-0.5 opacity-0 transition group-hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:outline-hidden active:cursor-grabbing"
                        aria-label={`Reorder ${point.reference}`}
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" />
                    </button>
                )}

                <Link
                    href={route('testing.points.show', [point.project_id, point.id])}
                    className="min-w-0 flex-1 text-sm font-medium hover:underline"
                >
                    <span className="text-muted-foreground mr-1.5 font-mono text-xs font-normal">{point.reference}</span>
                    {point.title}
                </Link>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                <PriorityBadge priority={point.priority} />

                {point.task && (
                    <span className="text-muted-foreground inline-flex items-center gap-1 text-xs" title={`Verifies ${point.task.title}`}>
                        <Link2 className="size-3.5" />
                        <span className="font-mono">{point.task.reference}</span>
                    </span>
                )}

                <span className="ml-auto" title={point.assignee ? `Assigned to ${point.assignee.name}` : 'Not assigned yet'}>
                    <UserAvatar name={point.assignee?.name} className="size-6" />
                </span>
            </div>

            <CardPeople creator={point.reporter} assigner={point.assigner} addedVerb="Reported" />
        </div>
    );
}
