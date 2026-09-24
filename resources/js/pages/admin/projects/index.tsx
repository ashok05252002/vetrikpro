import DeleteButton from '@/components/admin/delete-button';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import SearchFilter from '@/components/admin/search-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Meter from '@/components/viz/meter';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import type { BreadcrumbItem, Option, Paginated, ProjectSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { LayoutGrid, Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manage projects', href: '/admin/projects' },
];

interface Props {
    projects: Paginated<ProjectSummary>;
    statuses: Option[];
    filters: { search?: string; status?: string };
}

export default function AdminProjectsIndex({ projects, statuses, filters }: Props) {
    const statusLabel = (value?: string) => statuses.find((s) => s.value === value)?.label ?? value;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage projects" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Manage projects"
                    description="Create projects, set an owner, and choose who is on them."
                    action={
                        <Button asChild>
                            <Link href={route('admin.projects.create')}>
                                <Plus className="size-4" /> New project
                            </Link>
                        </Button>
                    }
                />

                <SearchFilter url={route('admin.projects.index')} initial={filters.search ?? ''} placeholder="Search name or code…" />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Project</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Members</TableHead>
                                <TableHead className="w-48">Progress</TableHead>
                                <TableHead>Due</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {projects.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-10 text-center">
                                        No projects yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {projects.data.map((project) => (
                                <TableRow key={project.id}>
                                    <TableCell>
                                        <div className="font-medium">{project.name}</div>
                                        <div className="text-muted-foreground font-mono text-[11px]">{project.code}</div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{project.owner?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>{statusLabel(project.status)}</Badge>
                                    </TableCell>
                                    <TableCell className="tabular-nums">{project.members_count}</TableCell>
                                    <TableCell>
                                        <div className="space-y-1.5">
                                            <div className="text-muted-foreground text-xs tabular-nums">
                                                {project.done_tasks_count}/{project.tasks_count} · {project.progress}%
                                            </div>
                                            <Meter value={project.progress ?? 0} label={`${project.name}: ${project.progress}% complete`} />
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{formatDate(project.due_date)}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={route('projects.show', project.id)}>
                                                    <LayoutGrid className="size-4" />
                                                    <span className="sr-only">Open board</span>
                                                </Link>
                                            </Button>
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={route('admin.projects.edit', project.id)}>
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Link>
                                            </Button>
                                            <DeleteButton
                                                url={route('admin.projects.destroy', project.id)}
                                                label={project.name}
                                                description="Every task and comment in this project is deleted too."
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={projects} />
            </div>
        </AppLayout>
    );
}
