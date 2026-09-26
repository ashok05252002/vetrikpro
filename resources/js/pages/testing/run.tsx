import DeleteButton from '@/components/admin/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import PriorityBadge from '@/components/work/priority-badge';
import TestResultBadge, { RESULT_ORDER, RunProgress, TestResultMark, testResultSpec } from '@/components/work/test-result';
import { useFormat } from '@/hooks/use-format';
import TestingLayout from '@/layouts/testing/testing-layout';
import { cn } from '@/lib/utils';
import type { Option, ProjectWorkspaceHeader, TestingCounts, TestResult, TestRunResultRow, TestRunSummary, User } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ChevronDown, CircleCheck, RotateCcw } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    counts: TestingCounts;
    run: TestRunSummary & { description: string | null; completer: Pick<User, 'id' | 'name'> | null };
    results: TestRunResultRow[];
    outcomes: Option[];
    can: { complete: boolean; delete: boolean };
}

type Filter = TestResult | 'all';

/** The buttons a tester presses, in the order they are most often needed. */
const ACTIONS: TestResult[] = ['passed', 'failed', 'blocked'];

function ResultRow({
    row,
    projectId,
    runId,
    runOpen,
    expanded,
    onToggle,
    onRecorded,
}: {
    row: TestRunResultRow;
    projectId: number;
    runId: number;
    runOpen: boolean;
    expanded: boolean;
    onToggle: () => void;
    onRecorded: () => void;
}) {
    const format = useFormat();
    const [notes, setNotes] = useState(row.notes ?? '');
    const [saving, setSaving] = useState(false);

    const record = (result: TestResult) => {
        setSaving(true);
        router.patch(
            route('testing.runs.results.update', [projectId, runId, row.id]),
            { result, notes },
            {
                preserveScroll: true,
                onSuccess: () => result !== 'not_run' && onRecorded(),
                onFinish: () => setSaving(false),
            },
        );
    };

    const why = !runOpen
        ? 'This run is completed, so its results are fixed.'
        : !row.point
          ? 'This testing point has been deleted; the run keeps what was recorded.'
          : 'Only the point’s creator, its tester, the project owner or an administrator record its result.';

    return (
        <li className={cn('border-b last:border-b-0', expanded && 'bg-muted/30')}>
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={expanded}
                className="hover:bg-muted/50 focus-visible:ring-ring flex w-full items-center gap-3 px-4 py-3 text-left focus-visible:ring-2 focus-visible:outline-hidden focus-visible:ring-inset"
            >
                <span className="text-muted-foreground w-12 shrink-0 font-mono text-xs">{row.reference}</span>
                <span className="min-w-0 flex-1 truncate text-sm font-medium">{row.title}</span>
                <span className="text-muted-foreground hidden shrink-0 text-xs md:inline">{row.point?.assignee?.name ?? ''}</span>
                <TestResultBadge result={row.result} className="w-20 shrink-0" />
                <ChevronDown className={cn('text-muted-foreground size-4 shrink-0 transition-transform', expanded && 'rotate-180')} />
            </button>

            {expanded && (
                <div className="space-y-4 px-4 pt-1 pb-4">
                    {row.point ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs font-medium">Steps</p>
                                <p className="text-sm whitespace-pre-wrap">{row.point.steps || '—'}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs font-medium">Expected result</p>
                                <p className="text-sm whitespace-pre-wrap">{row.point.expected_result || '—'}</p>
                            </div>
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">This testing point has since been deleted.</p>
                    )}

                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                        {row.point && <PriorityBadge priority={row.point.priority} />}
                        {row.tested_at && (
                            <span>
                                Recorded {format.dateTime(row.tested_at)}
                                {row.tester && ` by ${row.tester.name}`}
                            </span>
                        )}
                        {row.point && (
                            <Link href={route('testing.points.show', [projectId, row.point.id])} className="hover:underline">
                                Open {row.reference} →
                            </Link>
                        )}
                    </div>

                    {row.can_record ? (
                        <div className="space-y-3">
                            <Textarea
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={2}
                                placeholder="What actually happened — required reading for whoever fixes a failure"
                                aria-label={`Notes for ${row.reference}`}
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                {ACTIONS.map((result) => (
                                    <Button
                                        key={result}
                                        type="button"
                                        size="sm"
                                        variant={row.result === result ? 'default' : 'outline'}
                                        disabled={saving}
                                        onClick={() => record(result)}
                                    >
                                        <TestResultMark result={result} className={cn(row.result === result && 'text-current')} />
                                        {testResultSpec[result].label}
                                    </Button>
                                ))}
                                {row.result !== 'not_run' && (
                                    <Button type="button" size="sm" variant="ghost" disabled={saving} onClick={() => record('not_run')}>
                                        <RotateCcw className="size-4" /> Clear result
                                    </Button>
                                )}
                            </div>
                            <p className="text-muted-foreground text-xs">Passed or Failed also moves {row.reference} on the testing board.</p>
                        </div>
                    ) : (
                        <>
                            {row.notes && <p className="text-sm whitespace-pre-wrap">{row.notes}</p>}
                            <p className="text-muted-foreground text-xs">{why}</p>
                        </>
                    )}
                </div>
            )}
        </li>
    );
}

export default function TestRunPage({ project, counts, run, results, can }: Props) {
    const format = useFormat();
    const [filter, setFilter] = useState<Filter>('all');
    const [openId, setOpenId] = useState<number | null>(null);
    const runOpen = run.status === 'open';

    const visible = filter === 'all' ? results : results.filter((r) => r.result === filter);

    // After recording, move straight on to the next point nobody has run yet.
    const openNext = (afterId: number) => {
        const index = results.findIndex((r) => r.id === afterId);
        const next = [...results.slice(index + 1), ...results.slice(0, index)].find((r) => r.result === 'not_run' && r.can_record);
        setOpenId(next?.id ?? null);
    };

    const transition = (action: 'complete' | 'reopen') =>
        router.post(route(`testing.runs.${action}`, [project.id, run.id]), {}, { preserveScroll: true });

    return (
        <TestingLayout
            project={project}
            counts={counts}
            tab="runs"
            crumbs={[{ title: run.reference, href: route('testing.runs.show', [project.id, run.id]) }]}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <Link href={route('testing.runs.index', project.id)} className="text-muted-foreground text-xs hover:underline">
                        ← All test runs
                    </Link>
                    <h2 className="text-lg font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-sm font-normal">{run.reference}</span>
                        {run.name}
                    </h2>
                    <p className="text-muted-foreground text-xs">
                        Started {format.date(run.created_at)}
                        {run.creator && ` by ${run.creator.name}`}
                        {run.completed_at && ` · Completed ${format.date(run.completed_at)}${run.completer ? ` by ${run.completer.name}` : ''}`}
                    </p>
                    {run.description && <p className="max-w-2xl text-sm whitespace-pre-wrap">{run.description}</p>}
                </div>

                <div className="flex items-center gap-2">
                    {can.complete &&
                        (runOpen ? (
                            <Button size="sm" onClick={() => transition('complete')}>
                                <CircleCheck className="size-4" /> Complete run
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" onClick={() => transition('reopen')}>
                                <RotateCcw className="size-4" /> Reopen
                            </Button>
                        ))}
                    {can.delete && (
                        <DeleteButton
                            url={route('testing.runs.destroy', [project.id, run.id])}
                            label={run.reference}
                            description="The run and its recorded results are removed. The testing points themselves are not touched."
                        />
                    )}
                </div>
            </div>

            <Card>
                <CardContent className="space-y-1 p-4">
                    {!runOpen && <p className="text-muted-foreground pb-2 text-xs">Completed — these results are the record of this round.</p>}
                    <RunProgress tally={run.tally} />
                </CardContent>
            </Card>

            <div className="flex flex-wrap gap-1" role="group" aria-label="Show results">
                {(['all', ...RESULT_ORDER] as Filter[]).map((value) => (
                    <Button
                        key={value}
                        type="button"
                        size="sm"
                        variant={filter === value ? 'secondary' : 'ghost'}
                        aria-pressed={filter === value}
                        onClick={() => setFilter(value)}
                        className="h-8"
                    >
                        {value !== 'all' && <TestResultMark result={value} className="size-3.5" />}
                        {value === 'all' ? 'All' : testResultSpec[value].label}
                        <span className="text-muted-foreground tabular-nums">{value === 'all' ? run.total : run.tally[value]}</span>
                    </Button>
                ))}
            </div>

            <div className="overflow-hidden rounded-xl border">
                {visible.length === 0 ? (
                    <p className="text-muted-foreground py-10 text-center text-sm">Nothing here.</p>
                ) : (
                    <ul>
                        {visible.map((row) => (
                            <ResultRow
                                key={row.id}
                                row={row}
                                projectId={project.id}
                                runId={run.id}
                                runOpen={runOpen}
                                expanded={openId === row.id}
                                onToggle={() => setOpenId(openId === row.id ? null : row.id)}
                                onRecorded={() => openNext(row.id)}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </TestingLayout>
    );
}
