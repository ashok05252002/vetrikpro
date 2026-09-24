import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import PriorityBadge from '@/components/work/priority-badge';
import StageBadge from '@/components/work/stage-badge';
import TaskDialog from '@/components/work/task-dialog';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Option, TaskDetail, User } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    task: TaskDetail;
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    can: { update: boolean; delete: boolean };
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
                        <h1 className="text-xl font-semibold tracking-tight">{task.title}</h1>
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
                                <CardTitle className="text-base">Description</CardTitle>
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

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-5">
                                <Field label="Stage">
                                    {can.update ? (
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

                                {task.completed_at && <Field label="Completed">{format.dateTime(task.completed_at)}</Field>}
                            </dl>
                        </CardContent>
                    </Card>
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
                />
            )}
        </AppLayout>
    );
}
