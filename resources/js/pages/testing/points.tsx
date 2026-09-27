import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import KanbanBoard from '@/components/work/kanban-board';
import PriorityBadge from '@/components/work/priority-badge';
import TestPointCard from '@/components/work/test-point-card';
import TestPointDialog from '@/components/work/test-point-dialog';
import TestStatusBadge, { TestStatusMark, TestStatusSelect, testStatusSpec } from '@/components/work/test-status';
import ViewToggle, { type WorkView } from '@/components/work/view-toggle';
import { AssigneeCell } from '@/components/work/work-people';
import { useFormat } from '@/hooks/use-format';
import TestingLayout from '@/layouts/testing/testing-layout';
import type { BoardColumn, Option, Paginated, ProjectWorkspaceHeader, TestingCounts, TestPointStatus, TestPointSummary, User } from '@/types';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    counts: TestingCounts;
    view: WorkView;
    columns?: BoardColumn<TestPointStatus, TestPointSummary>[];
    list?: Paginated<TestPointSummary>;
    summary: Partial<Record<TestPointStatus, number>> | [];
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    filters: { search?: string; status?: string; priority?: string; assignee?: string };
    can: { create: boolean; assign: boolean };
}

/** Counts per status, as words and numbers — readable without a chart. */
function Summary({ summary }: { summary: Props['summary'] }) {
    const counts = Array.isArray(summary) ? {} : summary;
    const total = Object.values(counts).reduce((a, b) => a + (b ?? 0), 0);
    const closed = counts.closed ?? 0;

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
                    · {closed} of {total} closed
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
                url={route('testing.points.index', project.id)}
                filters={filters}
                keep={{ view: 'list' }}
                searchPlaceholder="Title or number, e.g. TP-4…"
                selects={[
                    { name: 'status', placeholder: 'Any status', options: statuses },
                    { name: 'priority', placeholder: 'Any priority', options: priorities },
                    { name: 'assignee', placeholder: 'Anyone assigned', options: assignees.map((a) => ({ value: String(a.id), label: a.name })) },
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
                            <TableHead className="hidden md:table-cell">Assigned to</TableHead>
                            <TableHead className="hidden lg:table-cell">Reported by</TableHead>
                            <TableHead className="hidden xl:table-cell">Last tested</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {list.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={8} className="text-muted-foreground py-10 text-center">
                                    No testing points match.
                                </TableCell>
                            </TableRow>
                        )}
                        {list.data.map((point) => (
                            <TableRow key={point.id}>
                                <TableCell className="text-muted-foreground font-mono text-xs">{point.reference}</TableCell>
                                <TableCell>
                                    <Link href={route('testing.points.show', [project.id, point.id])} className="font-medium hover:underline">
                                        {point.title}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    {point.can_move ? (
                                        <TestStatusSelect
                                            status={point.status}
                                            moveUrl={route('testing.points.move', [project.id, point.id])}
                                            reload={['list', 'summary', 'counts', 'flash']}
                                        />
                                    ) : (
                                        <TestStatusBadge status={point.status} />
                                    )}
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
                                    <AssigneeCell assignee={point.assignee} assigner={point.assigner} />
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs lg:table-cell">{point.reporter?.name ?? '—'}</TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs xl:table-cell">
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

export default function TestingPoints({ project, counts, view, columns, list, summary, statuses, priorities, assignees, filters, can }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogStatus, setDialogStatus] = useState<string>('open');

    const openNew = (status: string) => {
        setDialogStatus(status);
        setDialogOpen(true);
    };

    return (
        <TestingLayout
            project={project}
            counts={counts}
            tab="points"
            actions={
                can.create && (
                    <Button size="sm" onClick={() => openNew('open')}>
                        <Plus className="size-4" /> Report bug
                    </Button>
                )
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Summary summary={summary} />
                <ViewToggle url={route('testing.points.index', project.id)} view={view} />
            </div>

            {view === 'board' && columns && (
                <KanbanBoard<TestPointStatus, TestPointSummary>
                    columns={columns}
                    moveUrl={(id) => route('testing.points.move', [project.id, id])}
                    reloadOnError={['columns', 'summary']}
                    columnMark={(status) => <TestStatusMark status={status} />}
                    columnColor={(status) => testStatusSpec[status].color}
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
                canAssign={can.assign}
            />
        </TestingLayout>
    );
}
