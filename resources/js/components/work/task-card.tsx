import PriorityBadge from '@/components/work/priority-badge';
import UserAvatar from '@/components/work/user-avatar';
import { CardPeople } from '@/components/work/work-people';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import type { TaskSummary } from '@/types';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link } from '@inertiajs/react';
import { GripVertical, MessageSquare } from 'lucide-react';

interface Props {
    task: TaskSummary;
    /** False for a read-only viewer, which also removes the drag handle. */
    draggable?: boolean;
    /** The floating copy rendered in the DragOverlay. */
    overlay?: boolean;
}

export default function TaskCard({ task, draggable = true, overlay = false }: Props) {
    const format = useFormat();
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: task.id,
        disabled: !draggable,
    });

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
                        aria-label={`Reorder ${task.title}`}
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" />
                    </button>
                )}

                <Link href={route('tasks.show', task.id)} className="min-w-0 flex-1 text-sm font-medium hover:underline">
                    {task.reference && <span className="text-muted-foreground mr-1.5 font-mono text-xs font-normal">{task.reference}</span>}
                    {task.title}
                </Link>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                <PriorityBadge priority={task.priority} />

                {task.due_date && (
                    <span className={cn('text-xs', task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground')}>
                        {format.due(task.due_date)}
                    </span>
                )}

                {(task.comments_count ?? 0) > 0 && (
                    <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                        <MessageSquare className="size-3.5" />
                        {task.comments_count}
                    </span>
                )}

                <span className="ml-auto" title={task.assignee ? `Assigned to ${task.assignee.name}` : 'Unassigned'}>
                    <UserAvatar name={task.assignee?.name} className="size-6" />
                </span>
            </div>

            <CardPeople creator={task.creator} assigner={task.assigner} />
        </div>
    );
}
