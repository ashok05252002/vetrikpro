import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Meter from '@/components/viz/meter';
import PipelineBar from '@/components/viz/pipeline-bar';
import StatTile from '@/components/viz/stat-tile';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import AppLayout from '@/layouts/app-layout';
import { relativeDue } from '@/lib/dates';
import type { BreadcrumbItem, PipelineStage, ProjectSummary, SharedData, TaskSummary } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Building2, CalendarClock, FolderKanban, IdCard, ListChecks, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface Stats {
    openTasks: number;
    overdueTasks: number;
    dueThisWeek: number;
    activeProjects: number;
    users?: number;
    employees?: number;
    departments?: number;
    admins?: number;
}

interface Props {
    stats: Stats;
    taskPipeline: PipelineStage[];
    myTasks: TaskSummary[];
    projects: ProjectSummary[];
    managesPeople: boolean;
}

export default function Dashboard({ stats, taskPipeline, myTasks, projects, managesPeople }: Props) {
    const { auth } = usePage<SharedData>().props;
    const totalTasks = taskPipeline.reduce((sum, stage) => sum + stage.count, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">Welcome back, {auth.user.name.split(' ')[0]}</h1>
                    <p className="text-muted-foreground text-sm">
                        {managesPeople ? 'Everything across the organisation.' : 'Your work across the projects you are on.'}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile label="Open tasks" value={stats.openTasks} icon={ListChecks} href="/tasks" />
                    <StatTile
                        label="Overdue"
                        value={stats.overdueTasks}
                        icon={AlertTriangle}
                        href="/tasks?overdue=1"
                        note={stats.overdueTasks > 0 ? 'Past their due date' : 'Nothing is late'}
                        alert
                    />
                    <StatTile label="Due this week" value={stats.dueThisWeek} icon={CalendarClock} href="/tasks" />
                    <StatTile label="Active projects" value={stats.activeProjects} icon={FolderKanban} href="/projects" />
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="text-base">Task pipeline</CardTitle>
                            <CardDescription>{totalTasks === 0 ? 'No tasks yet.' : `Where all ${totalTasks} tasks currently sit.`}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PipelineBar stages={taskPipeline} />
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Active projects</CardTitle>
                            <CardDescription>Share of tasks completed.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {projects.length === 0 && <p className="text-muted-foreground text-sm">No active projects.</p>}

                            {projects.map((project) => (
                                <div key={project.id} className="space-y-2">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <Link href={route('projects.show', project.id)} className="truncate text-sm font-medium hover:underline">
                                            {project.name}
                                        </Link>
                                        <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                            {project.done_tasks_count}/{project.tasks_count}
                                        </span>
                                    </div>
                                    <Meter value={project.progress ?? 0} label={`${project.name}: ${project.progress}% complete`} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <div className="space-y-1.5">
                            <CardTitle className="text-base">Assigned to you</CardTitle>
                            <CardDescription>Soonest due first.</CardDescription>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/tasks">
                                All tasks <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {myTasks.length === 0 ? (
                            <p className="text-muted-foreground py-6 text-center text-sm">Nothing assigned to you right now.</p>
                        ) : (
                            <ul className="divide-y">
                                {myTasks.map((task) => (
                                    <li key={task.id}>
                                        <Link
                                            href={route('tasks.show', task.id)}
                                            className="hover:bg-muted/40 flex flex-wrap items-center gap-x-4 gap-y-2 py-3"
                                        >
                                            <span className="min-w-0 flex-1 truncate text-sm font-medium">{task.title}</span>
                                            <span className="text-muted-foreground text-xs">{task.project?.code}</span>
                                            <PriorityBadge priority={task.priority} />
                                            <StageBadge status={task.status} />
                                            <span
                                                className={`w-28 text-right text-xs ${task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground'}`}
                                            >
                                                {task.due_date ? relativeDue(task.due_date) : 'No due date'}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {managesPeople && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile label="Users" value={stats.users ?? 0} icon={Users} href="/admin/users" />
                        <StatTile label="Employees" value={stats.employees ?? 0} icon={IdCard} href="/admin/employees" />
                        <StatTile label="Departments" value={stats.departments ?? 0} icon={Building2} href="/admin/departments" />
                        <StatTile label="Administrators" value={stats.admins ?? 0} icon={Users} href="/admin/users?role=admin" />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
