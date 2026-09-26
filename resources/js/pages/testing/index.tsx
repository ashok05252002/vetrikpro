import PageHeader from '@/components/admin/page-header';
import SearchFilter from '@/components/admin/search-filter';
import { Card, CardContent } from '@/components/ui/card';
import { RunProgress } from '@/components/work/test-result';
import { TestStatusMark } from '@/components/work/test-status';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, TestResult, TestRunStatus } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Testing', href: '/testing' }];

interface ProjectTesting {
    id: number;
    name: string;
    code: string;
    status: string;
    points_count: number;
    open_count: number;
    ready_count: number;
    repeated_count: number;
    open_runs_count: number;
    latest_run: {
        id: number;
        reference: string;
        name: string;
        status: TestRunStatus;
        created_at: string;
        completed_at: string | null;
        tally: Record<TestResult, number>;
    } | null;
}

interface Props {
    projects: ProjectTesting[];
    filters: { search?: string };
}

export default function TestingIndex({ projects, filters }: Props) {
    const format = useFormat();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Testing" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader title="Testing" description="Pick a project to see its testing points and test runs." />

                <SearchFilter url={route('testing.index')} initial={filters.search ?? ''} placeholder="Search projects…" />

                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <FlaskConical className="text-muted-foreground size-8" />
                            <div className="space-y-1">
                                <p className="font-medium">No projects to test</p>
                                <p className="text-muted-foreground text-sm">You will see a project here once you are added to one.</p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <Card key={project.id} className="hover:border-foreground/20 transition-colors">
                                <Link
                                    href={route('testing.points.index', project.id)}
                                    className="focus-visible:ring-ring block h-full rounded-xl focus-visible:ring-2 focus-visible:outline-hidden"
                                >
                                    <CardContent className="space-y-4 p-5">
                                        <div className="min-w-0 space-y-1">
                                            <h2 className="truncate font-medium">{project.name}</h2>
                                            <p className="text-muted-foreground font-mono text-[11px]">{project.code}</p>
                                        </div>

                                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                            <span>
                                                <span className="text-foreground font-medium tabular-nums">{project.open_count}</span> open of{' '}
                                                {project.points_count}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <TestStatusMark status="ready_for_test" className="size-3" />
                                                <span className="tabular-nums">{project.ready_count}</span> ready for test
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <TestStatusMark status="repeated" className="size-3" />
                                                <span className="tabular-nums">{project.repeated_count}</span> repeated
                                            </span>
                                        </div>

                                        <div className="space-y-2 border-t pt-3">
                                            {project.latest_run ? (
                                                <>
                                                    <div className="flex items-baseline justify-between gap-2 text-xs">
                                                        <span className="min-w-0 truncate">
                                                            <span className="text-muted-foreground mr-1.5 font-mono">
                                                                {project.latest_run.reference}
                                                            </span>
                                                            {project.latest_run.name}
                                                        </span>
                                                        <span className="text-muted-foreground shrink-0">
                                                            {project.latest_run.status === 'open'
                                                                ? 'In progress'
                                                                : `Completed ${format.date(project.latest_run.completed_at)}`}
                                                        </span>
                                                    </div>
                                                    <RunProgress tally={project.latest_run.tally} compact />
                                                </>
                                            ) : (
                                                <p className="text-muted-foreground text-xs">No test runs yet.</p>
                                            )}
                                            {project.open_runs_count > 1 && (
                                                <p className="text-muted-foreground text-xs">{project.open_runs_count} runs in progress</p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Link>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
