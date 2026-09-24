import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Meter from '@/components/viz/meter';
import { stageColor } from '@/components/work/stage-badge';
import TaskCard from '@/components/work/task-card';
import TaskDialog from '@/components/work/task-dialog';
import UserAvatar from '@/components/work/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { BoardColumn, BreadcrumbItem, Option, ProjectSummary, TaskStatus, TaskSummary, User } from '@/types';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCorners,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Settings2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    project: ProjectSummary;
    columns: BoardColumn[];
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    can: { createTask: boolean; updateProject: boolean };
}

/** A board column: a droppable area wrapping a vertical sortable list. */
function Column({ column, canCreate, onAdd, children }: { column: BoardColumn; canCreate: boolean; onAdd: () => void; children: React.ReactNode }) {
    const { setNodeRef, isOver } = useDroppable({ id: `column:${column.value}` });

    return (
        <section className="bg-muted/40 flex min-w-72 flex-1 flex-col rounded-xl">
            <header className="flex items-center gap-2 px-3 pt-3 pb-2">
                <span aria-hidden className="size-2.5 rounded-full" style={{ background: stageColor[column.value] }} />
                <h2 className="text-sm font-medium">{column.label}</h2>
                <span className="text-muted-foreground text-xs tabular-nums">{column.tasks.length}</span>

                {canCreate && (
                    <Button variant="ghost" size="sm" className="ml-auto size-7 p-0" onClick={onAdd} aria-label={`Add task to ${column.label}`}>
                        <Plus className="size-4" />
                    </Button>
                )}
            </header>

            <div ref={setNodeRef} className={cn('flex min-h-32 flex-1 flex-col gap-2 rounded-b-xl p-2 transition-colors', isOver && 'bg-muted')}>
                {children}

                {column.tasks.length === 0 && <p className="text-muted-foreground px-1 py-6 text-center text-xs">Nothing here.</p>}
            </div>
        </section>
    );
}

export default function Board({ project, columns: initialColumns, statuses, priorities, assignees, can }: Props) {
    const [columns, setColumns] = useState(initialColumns);
    const [activeTask, setActiveTask] = useState<TaskSummary | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogStatus, setDialogStatus] = useState<string>('todo');

    // Server is the source of truth: re-sync whenever Inertia sends new props.
    useEffect(() => setColumns(initialColumns), [initialColumns]);

    const sensors = useSensors(
        // A small distance threshold keeps a click on the card from starting a drag.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const taskIndex = useMemo(() => {
        const map = new Map<number, { task: TaskSummary; status: TaskStatus }>();
        columns.forEach((column) => column.tasks.forEach((task) => map.set(task.id, { task, status: column.value })));
        return map;
    }, [columns]);

    const columnOf = (id: string | number): TaskStatus | null => {
        if (typeof id === 'string' && id.startsWith('column:')) {
            return id.slice('column:'.length) as TaskStatus;
        }
        return taskIndex.get(Number(id))?.status ?? null;
    };

    const handleDragStart = ({ active }: DragStartEvent) => {
        setActiveTask(taskIndex.get(Number(active.id))?.task ?? null);
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        setActiveTask(null);

        if (!over) {
            return;
        }

        const from = columnOf(active.id);
        const to = columnOf(over.id);

        if (!from || !to) {
            return;
        }

        const taskId = Number(active.id);
        const source = columns.find((c) => c.value === from)!;
        const target = columns.find((c) => c.value === to)!;
        const task = source.tasks.find((t) => t.id === taskId)!;

        // Dropping on the column itself appends; dropping on a card inserts there.
        const overIndex = target.tasks.findIndex((t) => t.id === Number(over.id));
        const position = overIndex === -1 ? target.tasks.length : overIndex;

        if (from === to) {
            const oldIndex = source.tasks.findIndex((t) => t.id === taskId);
            if (oldIndex === position) {
                return;
            }

            setColumns((current) => current.map((c) => (c.value === from ? { ...c, tasks: arrayMove(c.tasks, oldIndex, position) } : c)));
        } else {
            // Move optimistically so the card doesn't snap back while the request runs.
            setColumns((current) =>
                current.map((c) => {
                    if (c.value === from) {
                        return { ...c, tasks: c.tasks.filter((t) => t.id !== taskId) };
                    }
                    if (c.value === to) {
                        const next = [...c.tasks];
                        next.splice(position, 0, { ...task, status: to });
                        return { ...c, tasks: next };
                    }
                    return c;
                }),
            );
        }

        router.patch(
            route('tasks.move', taskId),
            { status: to, position },
            {
                preserveScroll: true,
                preserveState: true,
                // On failure, the reload puts the server's truth back on screen.
                onError: () => router.reload({ only: ['columns', 'project'] }),
            },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />

            <div className="flex h-full flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight">{project.name}</h1>
                            <Badge variant="outline" className="font-mono text-[10px]">
                                {project.code}
                            </Badge>
                        </div>

                        {project.description && <p className="text-muted-foreground max-w-2xl text-sm">{project.description}</p>}

                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                            <span>Owner: {project.owner?.name ?? 'Unassigned'}</span>
                            {project.due_date && <span>Due {formatDate(project.due_date)}</span>}
                            <span className="flex items-center gap-1">
                                {project.members?.slice(0, 5).map((member) => (
                                    <span key={member.id} title={member.name}>
                                        <UserAvatar name={member.name} className="size-5" />
                                    </span>
                                ))}
                                {(project.members?.length ?? 0) > 5 && <span>+{(project.members?.length ?? 0) - 5}</span>}
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.updateProject && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={route('admin.projects.edit', project.id)}>
                                    <Settings2 className="size-4" /> Settings
                                </Link>
                            </Button>
                        )}
                        {can.createTask && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    setDialogStatus('todo');
                                    setDialogOpen(true);
                                }}
                            >
                                <Plus className="size-4" /> New task
                            </Button>
                        )}
                    </div>
                </div>

                <div className="max-w-md space-y-1.5">
                    <div className="text-muted-foreground flex items-baseline justify-between text-xs">
                        <span>Progress</span>
                        <span className="tabular-nums">{project.progress}%</span>
                    </div>
                    <Meter value={project.progress ?? 0} label={`${project.name} is ${project.progress}% complete`} />
                </div>

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCorners}
                    onDragStart={handleDragStart}
                    onDragEnd={handleDragEnd}
                    onDragCancel={() => setActiveTask(null)}
                >
                    <div className="flex flex-1 gap-4 overflow-x-auto pb-4">
                        {columns.map((column) => (
                            <Column
                                key={column.value}
                                column={column}
                                canCreate={can.createTask}
                                onAdd={() => {
                                    setDialogStatus(column.value);
                                    setDialogOpen(true);
                                }}
                            >
                                <SortableContext items={column.tasks.map((t) => t.id)} strategy={verticalListSortingStrategy}>
                                    {column.tasks.map((task) => (
                                        <TaskCard key={task.id} task={task} />
                                    ))}
                                </SortableContext>
                            </Column>
                        ))}
                    </div>

                    <DragOverlay>{activeTask && <TaskCard task={activeTask} overlay draggable={false} />}</DragOverlay>
                </DndContext>
            </div>

            <TaskDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                projectId={project.id}
                statuses={statuses}
                priorities={priorities}
                assignees={assignees}
                defaultStatus={dialogStatus}
            />
        </AppLayout>
    );
}
