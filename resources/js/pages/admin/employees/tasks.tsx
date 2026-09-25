import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import type { EmployeeProfileHeader, Option, Paginated, TaskSummary } from '@/types';
import { Link } from '@inertiajs/react';

interface Props {
    employee: EmployeeProfileHeader;
    tasks: Paginated<TaskSummary>;
    statuses: Option[];
    filters: { status?: string; search?: string };
}

export default function EmployeeTasks({ employee, tasks, statuses, filters }: Props) {
    const format = useFormat();

    return (
        <EmployeeProfileLayout employee={employee} tab="tasks">
            <FilterBar
                url={route('admin.employees.tasks', employee.id)}
                filters={filters}
                searchPlaceholder="Search tasks…"
                selects={[{ name: 'status', placeholder: 'Any stage', options: statuses }]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Task</TableHead>
                            <TableHead>Stage</TableHead>
                            <TableHead className="hidden sm:table-cell">Priority</TableHead>
                            <TableHead className="hidden md:table-cell">Due</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tasks.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={4} className="text-muted-foreground py-10 text-center">
                                    No tasks assigned.
                                </TableCell>
                            </TableRow>
                        )}

                        {tasks.data.map((task) => (
                            <TableRow key={task.id}>
                                <TableCell>
                                    <Link href={route('tasks.show', task.id)} className="font-medium hover:underline">
                                        {task.title}
                                    </Link>
                                    {task.project && <p className="text-muted-foreground text-xs">{task.project.name}</p>}
                                </TableCell>
                                <TableCell>
                                    <StageBadge status={task.status} />
                                </TableCell>
                                <TableCell className="hidden sm:table-cell">
                                    <PriorityBadge priority={task.priority} />
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    {task.due_date ? (
                                        <span className={task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground'}>
                                            {format.date(task.due_date)}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                    {task.is_overdue && (
                                        <Badge variant="destructive" className="ml-2">
                                            Overdue
                                        </Badge>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={tasks} />
        </EmployeeProfileLayout>
    );
}
