import PageHeader from '@/components/admin/page-header';
import SearchFilter from '@/components/admin/search-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import Meter from '@/components/viz/meter';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option, ProjectSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FolderKanban, Plus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Projects', href: '/projects' }];

interface Props {
    projects: ProjectSummary[];
    statuses: Option[];
    filters: { search?: string };
    canCreate: boolean;
}

export default function ProjectsIndex({ projects, statuses, filters, canCreate }: Props) {
    const format = useFormat();
    const statusLabel = (value?: string) => statuses.find((s) => s.value === value)?.label ?? value;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Projects"
                    description="Boards you own or belong to."
                    action={
                        canCreate && (
                            <Button asChild>
                                <Link href={route('admin.projects.create')}>
                                    <Plus className="size-4" /> New project
                                </Link>
                            </Button>
                        )
                    }
                />

                <SearchFilter url={route('projects.index')} initial={filters.search ?? ''} placeholder="Search projects…" />

                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <FolderKanban className="text-muted-foreground size-8" />
                            <div className="space-y-1">
                                <p className="font-medium">No projects yet</p>
                                <p className="text-muted-foreground text-sm">
                                    {canCreate
                                        ? 'Create one to start tracking work on a board.'
                                        : 'You will see a project here once you are added to one.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <Card key={project.id} className="hover:border-foreground/20 transition-colors">
                                <Link
                                    href={route('projects.show', project.id)}
                                    className="focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:outline-hidden"
                                >
                                    <CardContent className="space-y-4 p-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 space-y-1">
                                                <h2 className="truncate font-medium">{project.name}</h2>
                                                <p className="text-muted-foreground font-mono text-[11px]">{project.code}</p>
                                            </div>
                                            <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>
                                                {statusLabel(project.status)}
                                            </Badge>
                                        </div>

                                        {project.description && <p className="text-muted-foreground line-clamp-2 text-sm">{project.description}</p>}

                                        <div className="space-y-1.5">
                                            <div className="text-muted-foreground flex items-baseline justify-between text-xs">
                                                <span>
                                                    {project.done_tasks_count} of {project.tasks_count} done
                                                </span>
                                                <span className="tabular-nums">{project.progress}%</span>
                                            </div>
                                            <Meter value={project.progress ?? 0} label={`${project.name}: ${project.progress}% complete`} />
                                        </div>

                                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                                            <span className="inline-flex items-center gap-1.5">
                                                <Users className="size-3.5" />
                                                {project.members_count} member{project.members_count === 1 ? '' : 's'}
                                            </span>
                                            {project.due_date && <span>Due {format.date(project.due_date)}</span>}
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
