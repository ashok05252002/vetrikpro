import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Meter from '@/components/viz/meter';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import type { EmployeeProfileHeader, Option, Paginated, ProjectMemberRole, ProjectSummary } from '@/types';
import { Link } from '@inertiajs/react';

type Row = ProjectSummary & { is_owner: boolean; project_role: ProjectMemberRole | null; progress: number; their_open_tasks_count: number };

export default function EmployeeProjects({
    employee,
    projects,
    roles,
}: {
    employee: EmployeeProfileHeader;
    projects: Paginated<Row>;
    roles: Option[];
}) {
    const format = useFormat();
    const roleLabel = (value: string | null) => roles.find((r) => r.value === value)?.label;

    return (
        <EmployeeProfileLayout employee={employee} tab="projects">
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

                        {projects.data.map((project) => (
                            <TableRow key={project.id}>
                                <TableCell>
                                    <Link href={route('projects.show', project.id)} className="font-medium hover:underline">
                                        {project.name}
                                    </Link>
                                    <p className="text-muted-foreground font-mono text-xs">{project.code}</p>
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
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={projects} />
        </EmployeeProfileLayout>
    );
}
