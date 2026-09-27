import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import KanbanBoard from '@/components/work/kanban-board';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge, { stageColor } from '@/components/work/stage-badge';
import TaskCard from '@/components/work/task-card';
import TaskDialog from '@/components/work/task-dialog';
import ViewToggle, { type WorkView } from '@/components/work/view-toggle';
import { AssigneeCell } from '@/components/work/work-people';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { BoardColumn, Option, Paginated, ProjectWorkspaceHeader, TaskStatus, TaskSummary, User } from '@/types';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    view: WorkView;
    columns?: BoardColumn<TaskStatus, TaskSummary>[];
    list?: Paginated<TaskSummary>;
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    filters: { search?: string; status?: string; priority?: string; assignee?: string };
    can: { createTask: boolean };
}

function TaskList({
    list,
    filters,
    project,
    statuses,
    priorities,
    assignees,
}: {
    list: Paginated<TaskSummary>;
    filters: Props['filters'];
    project: ProjectWorkspaceHeader;
    statuses: Option[];
    priorities: Option[];
    assignees: Props['assignees'];
}) {
    const format = useFormat();

    return (
        <>
            <FilterBar
                url={route('projects.show', project.id)}
                filters={filters}
                keep={{ view: 'list' }}
                searchPlaceholder="Title or number, e.g. T-12…"
                selects={[
                    { name: 'status', placeholder: 'Any stage', options: statuses },
                    { name: 'priority', placeholder: 'Any priority', options: priorities },
                    { name: 'assignee', placeholder: 'Anyone', options: assignees.map((a) => ({ value: String(a.id), label: a.name })) },
                ]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-20">#</TableHead>
                            <TableHead>Task</TableHead>
                            <TableHead>Stage</TableHead>
                            <TableHead className="hidden sm:table-cell">Priority</TableHead>
                            <TableHead className="hidden md:table-cell">Assignee</TableHead>
                            <TableHead className="hidden lg:table-cell">Added by</TableHead>
                            <TableHead className="hidden lg:table-cell">Due</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {list.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={7} className="text-muted-foreground py-10 text-center">
                                    No tasks match.
                                </TableCell>
                            </TableRow>
                        )}
                        {list.data.map((task) => (
                            <TableRow key={task.id}>
                                <TableCell className="text-muted-foreground font-mono text-xs">{task.reference}</TableCell>
                                <TableCell>
                                    <Link href={route('tasks.show', task.id)} className="font-medium hover:underline">
                                        {task.title}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <StageBadge status={task.status} />
                                </TableCell>
                                <TableCell className="hidden sm:table-cell">
                                    <PriorityBadge priority={task.priority} />
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    <AssigneeCell assignee={task.assignee} assigner={task.assigner} />
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs lg:table-cell">{task.creator?.name ?? '—'}</TableCell>
                                <TableCell className="hidden lg:table-cell">
                                    {task.due_date ? (
                                        <span className={task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground'}>
                                            {format.date(task.due_date)}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                    {task.is_overdue && (
                                        <Badge variant="destructive" className="ml-2">
                                            Overdue
                                        </Badge>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={list} />
        </>
    );
}

export default function Board({ project, view, columns, list, statuses, priorities, assignees, filters, can }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogStatus, setDialogStatus] = useState<string>('todo');

    const openNew = (status: string) => {
        setDialogStatus(status);
        setDialogOpen(true);
    };

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="tasks"
            actions={
                can.createTask && (
                    <Button size="sm" onClick={() => openNew('todo')}>
                        <Plus className="size-4" /> New task
                    </Button>
                )
            }
        >
            <div className="flex justify-end">
                <ViewToggle url={route('projects.show', project.id)} view={view} />
            </div>

            {view === 'board' && columns && (
                <KanbanBoard<TaskStatus, TaskSummary>
                    columns={columns}
                    moveUrl={(id) => route('tasks.move', id)}
                    reloadOnError={['columns', 'project']}
                    columnMark={(status) => <span aria-hidden className="size-2.5 rounded-full" style={{ background: stageColor[status] }} />}
                    onAdd={can.createTask ? openNew : undefined}
                    renderCard={(task, { overlay }) => <TaskCard task={task} overlay={overlay} draggable={!overlay && Boolean(task.can_move)} />}
                />
            )}

            {view === 'list' && list && (
                <TaskList list={list} filters={filters} project={project} statuses={statuses} priorities={priorities} assignees={assignees} />
            )}

            <TaskDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                projectId={project.id}
                statuses={statuses}
                priorities={priorities}
                assignees={assignees}
                defaultStatus={dialogStatus}
            />
        </ProjectWorkspaceLayout>
    );
}
