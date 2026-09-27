import DeleteButton from '@/components/admin/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ImageAttachments, { type ImageAttachment } from '@/components/work/image-attachments';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import StatusHistory, { type StatusChangeRow } from '@/components/work/status-history';
import TestPointDialog from '@/components/work/test-point-dialog';
import TestResultBadge from '@/components/work/test-result';
import TestStatusBadge, { TestStatusMark } from '@/components/work/test-status';
import { useFormat } from '@/hooks/use-format';
import TestingLayout from '@/layouts/testing/testing-layout';
import type {
    Option,
    ProjectWorkspaceHeader,
    TaskStatus,
    TestingCounts,
    TestPointDetail,
    TestPointStatus,
    TestResult,
    TestRunStatus,
    User,
} from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

interface RunHistoryRow {
    id: number;
    result: TestResult;
    notes: string | null;
    tested_at: string | null;
    run: { id: number; reference: string; name: string; status: TestRunStatus };
    tester: Pick<User, 'id' | 'name'> | null;
}

interface Props {
    project: ProjectWorkspaceHeader;
    counts: TestingCounts;
    point: TestPointDetail & {
        task: (TestPointDetail['task'] & { status?: TaskStatus }) | null;
        history: StatusChangeRow[];
        attachments: ImageAttachment[];
        runs: RunHistoryRow[];
    };
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    can: { update: boolean; delete: boolean; changeStatus: boolean; assign: boolean };
}

/**
 * The next steps from each status, as the flow runs: the developer starts
 * work and hands it back; the tester closes it or sends it back as Repeated.
 * Any status can still be chosen in Edit or by dragging on the board.
 */
const NEXT: Record<TestPointStatus, { to: TestPointStatus; label: string }[]> = {
    open: [{ to: 'in_progress', label: 'Start work' }],
    in_progress: [{ to: 'ready_for_test', label: 'Ready for test' }],
    ready_for_test: [
        { to: 'closed', label: 'Passed — close' },
        { to: 'repeated', label: 'Failed — repeated' },
    ],
    repeated: [{ to: 'in_progress', label: 'Start work again' }],
    closed: [{ to: 'repeated', label: 'Reopen as repeated' }],
};

function Block({ title, text }: { title: string; text: string | null }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {text ? <p className="text-sm whitespace-pre-wrap">{text}</p> : <p className="text-muted-foreground text-sm">Not written yet.</p>}
            </CardContent>
        </Card>
    );
}

export default function TestPointPage({ project, counts, point, statuses, priorities, assignees, can }: Props) {
    const format = useFormat();
    const [editing, setEditing] = useState(false);

    return (
        <TestingLayout
            project={project}
            counts={counts}
            tab="points"
            crumbs={[{ title: point.reference, href: route('testing.points.show', [project.id, point.id]) }]}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <Link href={route('testing.points.index', project.id)} className="text-muted-foreground text-xs hover:underline">
                        ← All testing points
                    </Link>
                    <h2 className="text-lg font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-sm font-normal">{point.reference}</span>
                        {point.title}
                    </h2>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                        <TestStatusBadge status={point.status} />
                        <PriorityBadge priority={point.priority} />
                        <span className="text-muted-foreground">
                            Reported by <span className="text-foreground">{point.creator?.name ?? 'someone since removed'}</span>
                        </span>
                        <span className="text-muted-foreground">
                            Assigned to <span className="text-foreground">{point.assignee?.name ?? 'nobody yet'}</span>
                            {point.assignee && point.assigner && (
                                <>
                                    {' '}
                                    by <span className="text-foreground">{point.assigner.name}</span>
                                </>
                            )}
                        </span>
                        <span className="text-muted-foreground">
                            Last tested:{' '}
                            {point.last_tested_at
                                ? `${format.date(point.last_tested_at)}${point.last_tester ? ` by ${point.last_tester.name}` : ''}`
                                : 'never'}
                        </span>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {can.changeStatus &&
                        NEXT[point.status].map((step) => (
                            <Button
                                key={step.to}
                                size="sm"
                                variant={step.to === 'repeated' ? 'outline' : 'default'}
                                onClick={() =>
                                    // Placed at the end of the target column.
                                    router.patch(
                                        route('testing.points.move', [project.id, point.id]),
                                        { status: step.to, position: 9999 },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <TestStatusMark status={step.to} className={step.to === 'repeated' ? undefined : 'text-current'} />
                                {step.label}
                            </Button>
                        ))}
                    {can.update && (
                        <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                            <Pencil className="size-4" /> Edit
                        </Button>
                    )}
                    {can.delete && <DeleteButton url={route('testing.points.destroy', [project.id, point.id])} label={point.reference} />}
                </div>
            </div>

            {point.task && (
                <p className="text-sm">
                    Verifies{' '}
                    <Link href={route('tasks.show', point.task.id)} className="font-medium hover:underline">
                        <span className="font-mono">{point.task.reference}</span> {point.task.title}
                    </Link>
                    {point.task.status && <StageBadge status={point.task.status} className="ml-2" />}
                </p>
            )}

            <div className="grid gap-4 lg:grid-cols-3">
                <Block title="Steps" text={point.steps} />
                <Block title="Expected result" text={point.expected_result} />
                <Block title="Actual result" text={point.actual_result} />
            </div>

            <ImageAttachments
                attachments={point.attachments}
                uploadUrl={route('testing.points.attachments.store', [project.id, point.id])}
                deleteUrl={(id) => route('testing.points.attachments.destroy', [project.id, point.id, id])}
                canUpload={can.update}
            />

            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">Test runs</CardTitle>
                </CardHeader>
                <CardContent>
                    {point.runs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Not part of any test run yet.</p>
                    ) : (
                        <ul className="divide-y">
                            {point.runs.map((row) => (
                                <li key={row.id} className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-2.5 first:pt-0 last:pb-0">
                                    <div className="min-w-0 space-y-0.5">
                                        <Link href={route('testing.runs.show', [project.id, row.run.id])} className="text-sm hover:underline">
                                            <span className="text-muted-foreground mr-1.5 font-mono text-xs">{row.run.reference}</span>
                                            {row.run.name}
                                        </Link>
                                        {row.notes && <p className="text-muted-foreground text-xs whitespace-pre-wrap">{row.notes}</p>}
                                    </div>
                                    <div className="flex items-center gap-3 text-xs">
                                        {row.tested_at && (
                                            <span className="text-muted-foreground">
                                                {format.date(row.tested_at)}
                                                {row.tester && ` · ${row.tester.name}`}
                                            </span>
                                        )}
                                        <TestResultBadge result={row.result} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">Status history</CardTitle>
                </CardHeader>
                <CardContent>
                    <StatusHistory history={point.history} badge={(status) => <TestStatusBadge status={status as TestPointStatus} />} />
                </CardContent>
            </Card>

            <p className="text-muted-foreground text-xs">
                Created {format.date(point.created_at)}
                {point.creator && ` by ${point.creator.name}`}
            </p>

            <TestPointDialog
                open={editing}
                onOpenChange={setEditing}
                projectId={project.id}
                statuses={statuses}
                priorities={priorities}
                assignees={assignees}
                point={point}
                canChangeStatus={can.changeStatus}
                canAssign={can.assign}
            />
        </TestingLayout>
    );
}
