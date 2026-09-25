import DeleteButton from '@/components/admin/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import TestPointDialog from '@/components/work/test-point-dialog';
import TestStatusBadge from '@/components/work/test-status';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { Option, ProjectWorkspaceHeader, TaskStatus, TestPointDetail, User } from '@/types';
import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    point: TestPointDetail & { task: (TestPointDetail['task'] & { status?: TaskStatus }) | null };
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    can: { update: boolean; delete: boolean };
}

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

export default function TestPointPage({ project, point, statuses, priorities, assignees, can }: Props) {
    const format = useFormat();
    const [editing, setEditing] = useState(false);

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="testing"
            crumbs={[{ title: point.reference, href: route('projects.testing.show', [project.id, point.id]) }]}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <Link href={route('projects.testing.index', project.id)} className="text-muted-foreground text-xs hover:underline">
                        ← All testing points
                    </Link>
                    <h2 className="text-lg font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-sm font-normal">{point.reference}</span>
                        {point.title}
                    </h2>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                        <TestStatusBadge status={point.status} />
                        <PriorityBadge priority={point.priority} />
                        <span className="text-muted-foreground">Tester: {point.assignee?.name ?? 'Unassigned'}</span>
                        <span className="text-muted-foreground">
                            Last run:{' '}
                            {point.last_tested_at
                                ? `${format.date(point.last_tested_at)}${point.last_tester ? ` by ${point.last_tester.name}` : ''}`
                                : 'never'}
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {can.update && (
                        <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                            <Pencil className="size-4" /> Edit
                        </Button>
                    )}
                    {can.delete && <DeleteButton url={route('projects.testing.destroy', [project.id, point.id])} label={point.reference} />}
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
            />
        </ProjectWorkspaceLayout>
    );
}
