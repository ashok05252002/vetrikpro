import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import KanbanBoard from '@/components/work/kanban-board';
import PriorityBadge from '@/components/work/priority-badge';
import TestPointCard from '@/components/work/test-point-card';
import TestPointDialog from '@/components/work/test-point-dialog';
import TestStatusBadge, { TestStatusMark, testStatusSpec } from '@/components/work/test-status';
import UserAvatar from '@/components/work/user-avatar';
import ViewToggle, { type WorkView } from '@/components/work/view-toggle';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { BoardColumn, Option, Paginated, ProjectWorkspaceHeader, TestPointStatus, TestPointSummary, User } from '@/types';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    view: WorkView;
    columns?: BoardColumn<TestPointStatus, TestPointSummary>[];
    list?: Paginated<TestPointSummary>;
    summary: Partial<Record<TestPointStatus, number>> | [];
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    filters: { search?: string; status?: string; priority?: string; assignee?: string };
    can: { create: boolean };
}

/** Counts per status, as words and numbers — readable without a chart. */
function Summary({ summary }: { summary: Props['summary'] }) {
    const counts = Array.isArray(summary) ? {} : summary;
    const total = Object.values(counts).reduce((a, b) => a + (b ?? 0), 0);
    const run = (counts.passed ?? 0) + (counts.failed ?? 0);

    return (
        <div className="text-muted-foreground flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
            {(Object.keys(testStatusSpec) as TestPointStatus[]).map((status) => (
                <span key={status} className="inline-flex items-center gap-1.5">
                    <TestStatusMark status={status} />
                    {testStatusSpec[status].label}
                    <span className="text-foreground font-medium tabular-nums">{counts[status] ?? 0}</span>
                </span>
            ))}
            {total > 0 && (
                <span>
                    · {run} of {total} run{run > 0 && `, ${Math.round(((counts.passed ?? 0) / run) * 100)}% passing`}
                </span>
            )}
        </div>
    );
}

function TestPointList({
    list,
    filters,
    project,
    statuses,
    priorities,
    assignees,
}: {
    list: Paginated<TestPointSummary>;
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
                url={route('projects.testing.index', project.id)}
                filters={filters}
                keep={{ view: 'list' }}
                searchPlaceholder="Title or number, e.g. TP-4…"
                selects={[
                    { name: 'status', placeholder: 'Any status', options: statuses },
                    { name: 'priority', placeholder: 'Any priority', options: priorities },
                    { name: 'assignee', placeholder: 'Any tester', options: assignees.map((a) => ({ value: String(a.id), label: a.name })) },
                ]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-20">#</TableHead>
                            <TableHead>Testing point</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="hidden sm:table-cell">Priority</TableHead>
                            <TableHead className="hidden md:table-cell">Verifies</TableHead>
                            <TableHead className="hidden md:table-cell">Tester</TableHead>
                            <TableHead className="hidden lg:table-cell">Last run</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {list.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={7} className="text-muted-foreground py-10 text-center">
                                    No testing points match.
                                </TableCell>
                            </TableRow>
                        )}
                        {list.data.map((point) => (
                            <TableRow key={point.id}>
                                <TableCell className="text-muted-foreground font-mono text-xs">{point.reference}</TableCell>
                                <TableCell>
                                    <Link href={route('projects.testing.show', [project.id, point.id])} className="font-medium hover:underline">
                                        {point.title}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <TestStatusBadge status={point.status} />
                                </TableCell>
                                <TableCell className="hidden sm:table-cell">
                                    <PriorityBadge priority={point.priority} />
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    {point.task ? (
                                        <Link
                                            href={route('tasks.show', point.task.id)}
                                            className="font-mono text-xs hover:underline"
                                            title={point.task.title}
                                        >
                                            {point.task.reference}
                                        </Link>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    {point.assignee ? (
                                        <span className="flex items-center gap-2 text-sm">
                                            <UserAvatar name={point.assignee.name} /> {point.assignee.name}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">Unassigned</span>
                                    )}
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs lg:table-cell">
                                    {point.last_tested_at ? format.date(point.last_tested_at) : 'Never'}
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

export default function Testing({ project, view, columns, list, summary, statuses, priorities, assignees, filters, can }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogStatus, setDialogStatus] = useState<string>('to_test');

    const openNew = (status: string) => {
        setDialogStatus(status);
        setDialogOpen(true);
    };

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="testing"
            actions={
                can.create && (
                    <Button size="sm" onClick={() => openNew('to_test')}>
                        <Plus className="size-4" /> New testing point
                    </Button>
                )
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Summary summary={summary} />
                <ViewToggle url={route('projects.testing.index', project.id)} view={view} />
            </div>

            {view === 'board' && columns && (
                <KanbanBoard<TestPointStatus, TestPointSummary>
                    columns={columns}
                    moveUrl={(id) => route('projects.testing.move', [project.id, id])}
                    reloadOnError={['columns', 'summary']}
                    columnMark={(status) => <TestStatusMark status={status} />}
                    onAdd={can.create ? openNew : undefined}
                    renderCard={(point, { overlay }) => (
                        <TestPointCard point={point} overlay={overlay} draggable={!overlay && Boolean(point.can_move)} />
                    )}
                />
            )}

            {view === 'list' && list && (
                <TestPointList list={list} filters={filters} project={project} statuses={statuses} priorities={priorities} assignees={assignees} />
            )}

            <TestPointDialog
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
