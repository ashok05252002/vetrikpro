import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CardHeading from '@/components/ui/card-heading';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import type { StatusChangeRow } from '@/components/work/status-history';
import StatusHistory from '@/components/work/status-history';
import TaskDialog from '@/components/work/task-dialog';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Option, TaskDetail, TaskStatus, User } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, AlignLeft, History, Info, ListChecks, Pencil } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    task: TaskDetail & { history: StatusChangeRow[] };
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    can: { update: boolean; delete: boolean; changeStatus: boolean };
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <dt className="text-muted-foreground text-xs tracking-wide uppercase">{label}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

export default function ShowTask({ task, statuses, priorities, assignees, can }: Props) {
    const format = useFormat();
    const [editing, setEditing] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    const comment: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('tasks.comments.store', task.id), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    /** Stage can be changed inline without opening the full edit dialog. */
    const changeStage = (status: string) => {
        router.patch(route('tasks.move', task.id), { status, position: 0 }, { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        ...(task.project ? [{ title: task.project.name, href: `/projects/${task.project.id}` }] : []),
        { title: task.title, href: `/tasks/${task.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={task.title} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            {task.project && (
                                <Link href={route('projects.show', task.project.id)}>
                                    <Badge variant="outline" className="font-mono text-[10px]">
                                        {task.project.code}
                                    </Badge>
                                </Link>
                            )}
                            {task.is_overdue && <Badge variant="destructive">Overdue</Badge>}
                        </div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {task.reference && <span className="text-muted-foreground mr-2 font-mono text-base font-normal">{task.reference}</span>}
                            {task.title}
                        </h1>
                    </div>

                    {(can.update || can.delete) && (
                        <div className="flex items-center gap-2">
                            {can.update && (
                                <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                                    <Pencil className="size-4" /> Edit
                                </Button>
                            )}
                            {can.delete && (
                                <DeleteButton
                                    url={route('tasks.destroy', task.id)}
                                    label={task.title}
                                    description="The task and its comments are removed permanently."
                                />
                            )}
                        </div>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardHeading icon={AlignLeft} tone="violet">
                                    Description
                                </CardHeading>
                            </CardHeader>
                            <CardContent>
                                {task.description ? (
                                    <p className="text-sm whitespace-pre-wrap">{task.description}</p>
                                ) : (
                                    <p className="text-muted-foreground text-sm">No description.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardHeading icon={ListChecks} tone="indigo">
                                    Requirement points
                                </CardHeading>
                            </CardHeader>
                            <CardContent>
                                {(task.requirement_points ?? []).length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        None linked.{can.update && ' Use Edit to pick the points this task delivers.'}
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {task.requirement_points!.map((p) => (
                                            <li key={p.id} className="flex items-start gap-3 py-2 text-sm first:pt-0 last:pb-0">
                                                <Link
                                                    href={route('projects.requirements.index', [
                                                        task.project_id,
                                                        { search: String(p.reference).slice(1) },
                                                    ])}
                                                    className="text-muted-foreground w-12 shrink-0 font-mono text-xs hover:underline"
                                                >
                                                    {p.reference}
                                                </Link>
                                                <div className="min-w-0 flex-1">
                                                    <span className="font-medium">{p.module}</span>
                                                    <span className="text-muted-foreground"> · {p.version}</span>
                                                    <p className="whitespace-pre-line">{p.description}</p>
                                                    {p.outdated && (
                                                        <p className="mt-1 inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                                                            <AlertTriangle className="size-3.5" aria-hidden />
                                                            Changed or removed in {task.requirements_latest}. Check it and re-link the point.
                                                        </p>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Comments{task.comments.length > 0 && ` (${task.comments.length})`}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {task.comments.length === 0 && <p className="text-muted-foreground text-sm">No comments yet.</p>}

                                <ul className="space-y-5">
                                    {task.comments.map((entry) => (
                                        <li key={entry.id} className="flex gap-3">
                                            <UserAvatar name={entry.user.name} className="mt-0.5 size-7" />
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <p className="flex flex-wrap items-baseline gap-x-2">
                                                    <span className="text-sm font-medium">{entry.user.name}</span>
                                                    <span className="text-muted-foreground text-xs">{format.dateTime(entry.created_at)}</span>
                                                </p>
                                                <p className="text-sm whitespace-pre-wrap">{entry.body}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>

                                <form onSubmit={comment} className="space-y-2 border-t pt-5">
                                    <Textarea
                                        value={data.body}
                                        onChange={(e) => setData('body', e.target.value)}
                                        placeholder="Leave a comment…"
                                        required
                                    />
                                    <InputError message={errors.body} />
                                    <Button size="sm" disabled={processing || data.body.trim() === ''}>
                                        Comment
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="h-fit">
                            <CardHeader>
                                <CardHeading icon={Info} tone="sky">
                                    Details
                                </CardHeading>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-5">
                                    <Field label="Stage">
                                        {can.changeStatus ? (
                                            <Select value={task.status} onValueChange={changeStage}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {statuses.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <StageBadge status={task.status} />
                                        )}
                                    </Field>

                                    <Field label="Priority">
                                        <PriorityBadge priority={task.priority} />
                                    </Field>

                                    <Field label="Assignee">
                                        <span className="flex items-center gap-2">
                                            <UserAvatar name={task.assignee?.name} className="size-5" />
                                            {task.assignee?.name ?? 'Unassigned'}
                                        </span>
                                    </Field>

                                    <Field label="Due">
                                        <span className={cn(task.is_overdue && 'text-destructive font-medium')}>{format.due(task.due_date)}</span>
                                    </Field>

                                    <Field label="Created by">{task.creator?.name ?? '—'}</Field>

                                    <Field label="Assigned by">{task.assignee ? (task.assigner?.name ?? '—') : '—'}</Field>

                                    {task.completed_at && <Field label="Completed">{format.dateTime(task.completed_at)}</Field>}
                                </dl>
                            </CardContent>
                        </Card>

                        <Card className="h-fit">
                            <CardHeader>
                                <CardHeading icon={History} tone="violet">
                                    Status history
                                </CardHeading>
                            </CardHeader>
                            <CardContent>
                                <StatusHistory history={task.history} badge={(status) => <StageBadge status={status as TaskStatus} />} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {can.update && (
                <TaskDialog
                    open={editing}
                    onOpenChange={setEditing}
                    projectId={task.project_id}
                    statuses={statuses}
                    priorities={priorities}
                    assignees={assignees}
                    task={task}
                    canChangeStatus={can.changeStatus}
                />
            )}
        </AppLayout>
    );
}
