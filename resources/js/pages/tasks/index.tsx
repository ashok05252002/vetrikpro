import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Option, Paginated, TaskSummary } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ListChecks, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My tasks', href: '/tasks' }];
const ALL = 'all';

interface Filters {
    search?: string;
    status?: string;
    priority?: string;
    scope: string;
    overdue: boolean;
}

interface Props {
    tasks: Paginated<TaskSummary>;
    statuses: Option[];
    priorities: Option[];
    filters: Filters;
    canSeeAll: boolean;
}

export default function TasksIndex({ tasks, statuses, priorities, filters, canSeeAll }: Props) {
    const format = useFormat();
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    /** One filter row drives the whole query string. */
    const apply = (changes: Record<string, string | boolean | undefined>) => {
        const next: Record<string, string> = {};
        const merged = { ...filters, search, ...changes };

        if (merged.search) next.search = String(merged.search);
        if (merged.status && merged.status !== ALL) next.status = String(merged.status);
        if (merged.priority && merged.priority !== ALL) next.priority = String(merged.priority);
        if (merged.scope === 'all') next.scope = 'all';
        if (merged.overdue) next.overdue = '1';

        router.get(route('tasks.index'), next, { preserveState: true, replace: true });
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => apply({ search }), 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={filters.scope === 'all' ? 'All tasks' : 'My tasks'} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={filters.scope === 'all' ? 'All tasks' : 'My tasks'}
                    description={filters.scope === 'all' ? 'Every task across the organisation.' : 'Everything assigned to you, in workflow order.'}
                />

                {/* Filters sit in one row above everything they scope. */}
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input className="pl-9" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search tasks…" />
                    </div>

                    <Select value={filters.status || ALL} onValueChange={(value) => apply({ status: value })}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Any stage" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Any stage</SelectItem>
                            {statuses.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={filters.priority || ALL} onValueChange={(value) => apply({ priority: value })}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Any priority" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Any priority</SelectItem>
                            {priorities.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button variant={filters.overdue ? 'default' : 'outline'} size="sm" onClick={() => apply({ overdue: !filters.overdue })}>
                        Overdue only
                    </Button>

                    {canSeeAll && (
                        <Button
                            variant={filters.scope === 'all' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => apply({ scope: filters.scope === 'all' ? 'mine' : 'all' })}
                        >
                            Everyone
                        </Button>
                    )}
                </div>

                {tasks.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <ListChecks className="text-muted-foreground size-8" />
                            <div className="space-y-1">
                                <p className="font-medium">No tasks match</p>
                                <p className="text-muted-foreground text-sm">Try clearing a filter, or pick a different stage.</p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Task</TableHead>
                                    <TableHead>Project</TableHead>
                                    <TableHead>Stage</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Assignee</TableHead>
                                    <TableHead className="text-right">Due</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks.data.map((task) => (
                                    <TableRow key={task.id}>
                                        <TableCell className="max-w-sm">
                                            <Link href={route('tasks.show', task.id)} className="font-medium hover:underline">
                                                {task.title}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {task.project && (
                                                <Link
                                                    href={route('projects.show', task.project.id)}
                                                    className="text-muted-foreground text-sm hover:underline"
                                                >
                                                    {task.project.code}
                                                </Link>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StageBadge status={task.status} />
                                        </TableCell>
                                        <TableCell>
                                            <PriorityBadge priority={task.priority} />
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-muted-foreground flex items-center gap-2 text-sm">
                                                <UserAvatar name={task.assignee?.name} className="size-5" />
                                                {task.assignee?.name ?? 'Unassigned'}
                                            </span>
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right text-sm',
                                                task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground',
                                            )}
                                        >
                                            {format.due(task.due_date)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination meta={tasks} />
            </div>
        </AppLayout>
    );
}
