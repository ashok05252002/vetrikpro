import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import NewRunDialog, { type RunCandidate } from '@/components/work/new-run-dialog';
import { RunProgress } from '@/components/work/test-result';
import { useFormat } from '@/hooks/use-format';
import TestingLayout from '@/layouts/testing/testing-layout';
import type { Paginated, ProjectWorkspaceHeader, TestingCounts, TestRunSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { ListChecks, Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    counts: TestingCounts;
    runs: Paginated<TestRunSummary>;
    points: RunCandidate[];
    can: { create: boolean };
}

export default function TestRuns({ project, counts, runs, points, can }: Props) {
    const format = useFormat();
    const [creating, setCreating] = useState(false);

    return (
        <TestingLayout
            project={project}
            counts={counts}
            tab="runs"
            actions={
                can.create && (
                    <Button size="sm" onClick={() => setCreating(true)}>
                        <Plus className="size-4" /> New test run
                    </Button>
                )
            }
        >
            {runs.data.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <ListChecks className="text-muted-foreground size-8" />
                        <div className="space-y-1">
                            <p className="font-medium">No test runs yet</p>
                            <p className="text-muted-foreground max-w-md text-sm">
                                A run is one round of testing — a release, a regression, a re-test of failures. Each run keeps its own results.
                            </p>
                        </div>
                        {can.create && (
                            <Button size="sm" onClick={() => setCreating(true)}>
                                <Plus className="size-4" /> Start the first run
                            </Button>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-20">#</TableHead>
                                    <TableHead>Run</TableHead>
                                    <TableHead className="w-64">Progress</TableHead>
                                    <TableHead className="hidden md:table-cell">Started</TableHead>
                                    <TableHead className="hidden lg:table-cell">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {runs.data.map((run) => (
                                    <TableRow key={run.id}>
                                        <TableCell className="text-muted-foreground font-mono text-xs">{run.reference}</TableCell>
                                        <TableCell>
                                            <Link href={route('testing.runs.show', [project.id, run.id])} className="font-medium hover:underline">
                                                {run.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <RunProgress tally={run.tally} compact />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden text-xs md:table-cell">
                                            {format.date(run.created_at)}
                                            {run.creator && ` · ${run.creator.name}`}
                                        </TableCell>
                                        <TableCell className="hidden text-xs lg:table-cell">
                                            {run.status === 'open' ? (
                                                <span className="font-medium">In progress</span>
                                            ) : (
                                                <span className="text-muted-foreground">Completed {format.date(run.completed_at)}</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <Pagination meta={runs} />
                </>
            )}

            <NewRunDialog open={creating} onOpenChange={setCreating} projectId={project.id} points={points} />
        </TestingLayout>
    );
}
