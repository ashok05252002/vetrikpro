import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import Meter from '@/components/viz/meter';
import PipelineBar from '@/components/viz/pipeline-bar';
import StatTile from '@/components/viz/stat-tile';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PipelineStage, ProjectSummary, SharedData, TaskSummary } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Building2,
    CalendarClock,
    FolderKanban,
    GitPullRequest,
    IdCard,
    ListChecks,
    ShieldCheck,
    Target,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface Stats {
    openTasks: number;
    overdueTasks: number;
    dueThisWeek: number;
    activeProjects: number;
    employees?: number;
    departments?: number;
    admins?: number;
}

interface Props {
    stats: Stats;
    taskPipeline: PipelineStage[];
    myTasks: TaskSummary[];
    projects: ProjectSummary[];
    orgWide: boolean;
    peopleStats: boolean;
}

function greeting(): string {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}

/** A card whose title carries an icon chip in its tone. */
function SectionCard({
    icon,
    tone,
    title,
    description,
    action,
    className,
    children,
}: {
    icon: LucideIcon;
    tone: Tone;
    title: string;
    description: string;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card className={className}>
            <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                <IconChip icon={icon} tone={tone} size="sm" />
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                {action}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

/**
 * The welcome banner: who you are, today's date, and the one sentence that
 * matters most — how much is open and whether anything is late.
 */
function Hero({ name, stats, orgWide }: { name: string; stats: Stats; orgWide: boolean }) {
    const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

    return (
        <section
            className="relative overflow-hidden rounded-2xl p-6 text-white shadow-sm md:p-8"
            style={{ background: 'linear-gradient(120deg, var(--hero-from), var(--hero-to))' }}
        >
            {/* Decorative rings, behind the text. */}
            <span aria-hidden className="absolute -top-16 -right-10 size-56 rounded-full border-[28px] border-white/10" />
            <span aria-hidden className="absolute -right-24 -bottom-24 size-72 rounded-full bg-white/5" />

            <div className="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div className="space-y-2">
                    <p className="text-sm text-white/80">{today}</p>
                    <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                        {greeting()}, {name}
                    </h1>
                    <p className="max-w-xl text-sm text-white/85">
                        {stats.openTasks === 0
                            ? 'Nothing open right now.'
                            : `${stats.openTasks} open ${stats.openTasks === 1 ? 'task' : 'tasks'} ${orgWide ? 'across the organisation' : 'on your projects'}${
                                  stats.overdueTasks > 0 ? `, ${stats.overdueTasks} of them overdue.` : ', none overdue.'
                              }`}
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button asChild className="bg-white text-indigo-700 shadow-sm hover:bg-white/90">
                        <Link href="/tasks">
                            <ListChecks className="size-4" /> My tasks
                        </Link>
                    </Button>
                    <Button asChild variant="outline" className="border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white">
                        <Link href="/merge-requests">
                            <GitPullRequest className="size-4" /> Merge requests
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}

export default function Dashboard({ stats, taskPipeline, myTasks, projects, orgWide, peopleStats }: Props) {
    const { auth } = usePage<SharedData>().props;
    const format = useFormat();
    const totalTasks = taskPipeline.reduce((sum, stage) => sum + stage.count, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Hero name={auth.user.name.split(' ')[0]} stats={stats} orgWide={orgWide} />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile label="Open tasks" value={stats.openTasks} icon={ListChecks} tone="indigo" href="/tasks" />
                    <StatTile
                        label="Overdue"
                        value={stats.overdueTasks}
                        icon={AlertTriangle}
                        tone="red"
                        href="/tasks?overdue=1"
                        note={stats.overdueTasks > 0 ? 'Past their due date' : 'Nothing is late'}
                        alert
                    />
                    <StatTile
                        label="Due this week"
                        value={stats.dueThisWeek}
                        icon={CalendarClock}
                        tone="amber"
                        href="/tasks"
                        note="In the next 7 days"
                    />
                    <StatTile label="Active projects" value={stats.activeProjects} icon={FolderKanban} tone="violet" href="/projects" />
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <SectionCard
                        className="lg:col-span-3"
                        icon={BarChart3}
                        tone="sky"
                        title="Task pipeline"
                        description={totalTasks === 0 ? 'No tasks yet.' : `Where all ${totalTasks} tasks currently sit.`}
                    >
                        <PipelineBar stages={taskPipeline} />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2" icon={Target} tone="teal" title="Active projects" description="Share of tasks completed.">
                        <div className="space-y-5">
                            {projects.length === 0 && <p className="text-muted-foreground text-sm">No active projects.</p>}

                            {projects.map((project) => (
                                <div key={project.id} className="space-y-2">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <Link href={route('projects.show', project.id)} className="truncate text-sm font-medium hover:underline">
                                            {project.name}
                                        </Link>
                                        <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                            {project.progress}% · {project.done_tasks_count}/{project.tasks_count}
                                        </span>
                                    </div>
                                    <Meter value={project.progress ?? 0} label={`${project.name}: ${project.progress}% complete`} />
                                </div>
                            ))}
                        </div>
                    </SectionCard>
                </div>

                <SectionCard
                    icon={ListChecks}
                    tone="indigo"
                    title="Assigned to you"
                    description="Soonest due first."
                    action={
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/tasks">
                                All tasks <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    }
                >
                    {myTasks.length === 0 ? (
                        <p className="text-muted-foreground py-6 text-center text-sm">Nothing assigned to you right now.</p>
                    ) : (
                        <ul className="-mx-2 divide-y">
                            {myTasks.map((task) => (
                                <li key={task.id}>
                                    <Link
                                        href={route('tasks.show', task.id)}
                                        className="hover:bg-muted/60 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md px-2 py-3"
                                    >
                                        <span className="text-muted-foreground w-12 shrink-0 font-mono text-xs">{task.reference}</span>
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">{task.title}</span>
                                        {task.project?.code && (
                                            <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-[11px]">
                                                {task.project.code}
                                            </span>
                                        )}
                                        <PriorityBadge priority={task.priority} />
                                        <StageBadge status={task.status} />
                                        <span
                                            className={`w-28 text-right text-xs ${task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground'}`}
                                        >
                                            {task.due_date ? format.due(task.due_date) : 'No due date'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </SectionCard>

                {peopleStats && (
                    <section className="space-y-3">
                        <h2 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">People</h2>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <StatTile label="Employees" value={stats.employees ?? 0} icon={IdCard} tone="teal" href="/admin/employees" />
                            <StatTile label="Departments" value={stats.departments ?? 0} icon={Building2} tone="pink" href="/admin/departments" />
                            <StatTile
                                label="Administrators"
                                value={stats.admins ?? 0}
                                icon={ShieldCheck}
                                tone="violet"
                                href="/admin/employees?role=admin"
                            />
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
