import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Meter from '@/components/viz/meter';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import { cn } from '@/lib/utils';
import type { EmployeeProfileHeader, Option, Paginated, ProjectMemberRole, ProjectSummary, TaskSummary } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ChevronRight, ExternalLink, LoaderCircle } from 'lucide-react';
import { Fragment, useState } from 'react';

type Row = ProjectSummary & { is_owner: boolean; project_role: ProjectMemberRole | null; progress: number; their_open_tasks_count: number };

interface Selected {
    id: number;
    name: string;
    code: string;
    tasks: (TaskSummary & { reference: string })[];
}

/** Their tasks in one project, shown under its row. */
function ProjectTasks({ selected, name }: { selected: Selected; name: string }) {
    const format = useFormat();
    const open = selected.tasks.filter((t) => t.status !== 'done').length;

    return (
        <div className="bg-muted/30 space-y-3 px-4 py-4 sm:px-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm">
                    <span className="font-medium">
                        {selected.tasks.length === 0 ? 'No tasks' : `${selected.tasks.length} task${selected.tasks.length === 1 ? '' : 's'}`}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        assigned to {name.split(' ')[0]} in {selected.name}
                        {selected.tasks.length > 0 && ` · ${open} open`}
                    </span>
                </p>
                <Button asChild variant="ghost" size="sm" className="text-muted-foreground">
                    <Link href={route('projects.show', selected.id)}>
                        Open project <ExternalLink className="size-3.5" />
                    </Link>
                </Button>
            </div>

            {selected.tasks.length > 0 && (
                <div className="bg-background overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-b text-left text-xs">
                                <th className="w-20 px-3 py-2 font-medium">#</th>
                                <th className="px-3 py-2 font-medium">Task</th>
                                <th className="px-3 py-2 font-medium">Stage</th>
                                <th className="px-3 py-2 font-medium">Priority</th>
                                <th className="px-3 py-2 font-medium">Due</th>
                                <th className="px-3 py-2 font-medium">Added / assigned by</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {selected.tasks.map((task) => (
                                <tr key={task.id}>
                                    <td className="text-muted-foreground px-3 py-2 font-mono text-xs">{task.reference}</td>
                                    <td className="px-3 py-2">
                                        <Link href={route('tasks.show', task.id)} className="font-medium hover:underline">
                                            {task.title}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        <StageBadge status={task.status} />
                                    </td>
                                    <td className="px-3 py-2">
                                        <PriorityBadge priority={task.priority} />
                                    </td>
                                    <td
                                        className={cn(
                                            'px-3 py-2 text-xs',
                                            task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground',
                                        )}
                                    >
                                        {task.due_date ? format.date(task.due_date) : '—'}
                                    </td>
                                    <td className="text-muted-foreground px-3 py-2 text-xs">
                                        {task.creator?.name ?? '—'}
                                        {task.assigner && <span className="block">assigned by {task.assigner.name}</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export default function EmployeeProjects({
    employee,
    projects,
    roles,
    selected,
}: {
    employee: EmployeeProfileHeader;
    projects: Paginated<Row>;
    roles: Option[];
    selected: Selected | null;
}) {
    const format = useFormat();
    const roleLabel = (value: string | null) => roles.find((r) => r.value === value)?.label;
    const [loading, setLoading] = useState<number | null>(null);

    // Opening a project loads just its tasks and keeps the URL, so the view survives a refresh.
    const toggle = (id: number) => {
        const url = new URL(window.location.href);
        if (selected?.id === id) {
            url.searchParams.delete('project');
        } else {
            url.searchParams.set('project', String(id));
        }

        router.get(
            url.pathname + url.search,
            {},
            {
                only: ['selected'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(id),
                onFinish: () => setLoading(null),
            },
        );
    };

    return (
        <EmployeeProfileLayout employee={employee} tab="projects">
            <p className="text-muted-foreground text-sm">Choose a project to see the tasks {employee.name.split(' ')[0]} holds in it.</p>

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Project</TableHead>
                            <TableHead>Their role</TableHead>
                            <TableHead className="hidden sm:table-cell">Open tasks</TableHead>
                            <TableHead className="hidden md:table-cell">Progress</TableHead>
                            <TableHead className="hidden lg:table-cell">Due</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {projects.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                    Not on any project.
                                </TableCell>
                            </TableRow>
                        )}

                        {projects.data.map((project) => {
                            const isOpen = selected?.id === project.id;

                            return (
                                <Fragment key={project.id}>
                                    <TableRow
                                        className={cn('cursor-pointer', isOpen && 'bg-muted/50 hover:bg-muted/50')}
                                        onClick={() => toggle(project.id)}
                                    >
                                        <TableCell>
                                            <button
                                                type="button"
                                                className="flex items-start gap-2 text-left"
                                                aria-expanded={isOpen}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    toggle(project.id);
                                                }}
                                            >
                                                {loading === project.id ? (
                                                    <LoaderCircle className="text-muted-foreground mt-0.5 size-4 shrink-0 animate-spin" />
                                                ) : (
                                                    <ChevronRight
                                                        className={cn(
                                                            'text-muted-foreground mt-0.5 size-4 shrink-0 transition-transform',
                                                            isOpen && 'rotate-90',
                                                        )}
                                                    />
                                                )}
                                                <span>
                                                    <span className="font-medium">{project.name}</span>
                                                    <span className="text-muted-foreground block font-mono text-xs">{project.code}</span>
                                                </span>
                                            </button>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {project.is_owner && <Badge>Owner</Badge>}
                                                {project.project_role && (
                                                    <Badge variant={project.project_role === 'dev_admin' ? 'secondary' : 'outline'}>
                                                        {roleLabel(project.project_role)}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden tabular-nums sm:table-cell">{project.their_open_tasks_count}</TableCell>
                                        <TableCell className="hidden w-40 md:table-cell">
                                            <div className="flex items-center gap-2">
                                                <Meter value={project.progress} className="flex-1" />
                                                <span className="text-muted-foreground w-9 text-right text-xs tabular-nums">{project.progress}%</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden lg:table-cell">
                                            {project.due_date ? format.date(project.due_date) : '—'}
                                        </TableCell>
                                    </TableRow>

                                    {isOpen && selected && (
                                        <TableRow className="hover:bg-transparent">
                                            <TableCell colSpan={5} className="p-0">
                                                <ProjectTasks selected={selected} name={employee.name} />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={projects} />
        </EmployeeProfileLayout>
    );
}
