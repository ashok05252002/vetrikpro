import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Option, TaskDetail, User } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

const UNASSIGNED = 'unassigned';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    projectId: number;
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    /** Present when editing; absent when creating. */
    task?: TaskDetail | null;
    /** Pre-selected column when opened from a board column's "+" button. */
    defaultStatus?: string;
    /** False locks the stage: only the creator, assignee, owner or an admin may change it. */
    canChangeStatus?: boolean;
}

export default function TaskDialog({
    open,
    onOpenChange,
    projectId,
    statuses,
    priorities,
    assignees,
    task = null,
    defaultStatus = 'todo',
    canChangeStatus = true,
}: Props) {
    const editing = Boolean(task);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        project_id: projectId,
        title: '',
        description: '',
        status: defaultStatus,
        priority: 'medium',
        assigned_to: UNASSIGNED,
        due_date: '',
    });

    // Radix Select cannot hold an empty value, so "unassigned" rides as a
    // sentinel and is blanked on the way to the server.
    transform((payload) => ({
        ...payload,
        assigned_to: payload.assigned_to === UNASSIGNED ? '' : payload.assigned_to,
    }));

    // Refill whenever the dialog opens, so it never shows the previous task.
    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setData({
            project_id: projectId,
            title: task?.title ?? '',
            description: task?.description ?? '',
            status: task?.status ?? defaultStatus,
            priority: task?.priority ?? 'medium',
            assigned_to: task?.assignee ? String(task.assignee.id) : UNASSIGNED,
            due_date: task?.due_date ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, task?.id, defaultStatus]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const done = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        };

        if (editing && task) {
            put(route('tasks.update', task.id), done);
        } else {
            post(route('tasks.store'), done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit task' : 'New task'}</DialogTitle>
                        <DialogDescription>{editing ? 'Update the details of this task.' : 'Add a task to this project.'}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                required
                                autoFocus
                                placeholder="Ship the payroll export"
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                Description <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="status">Stage</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) => setData('status', value)}
                                    disabled={Boolean(task) && !canChangeStatus}
                                >
                                    <SelectTrigger id="status">
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
                                <InputError message={errors.status} />
                                {task && !canChangeStatus && (
                                    <p className="text-muted-foreground text-xs">
                                        Only the creator, assignee, project owner or an admin can change this.
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="priority">Priority</Label>
                                <Select value={data.priority} onValueChange={(value) => setData('priority', value)}>
                                    <SelectTrigger id="priority">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priorities.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.priority} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="assigned_to">Assignee</Label>
                                <Select value={data.assigned_to} onValueChange={(value) => setData('assigned_to', value)}>
                                    <SelectTrigger id="assigned_to">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={UNASSIGNED}>Unassigned</SelectItem>
                                        {assignees.map((user) => (
                                            <SelectItem key={user.id} value={String(user.id)}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.assigned_to} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="due_date">Due date</Label>
                                <DatePicker id="due_date" value={data.due_date ?? ''} onChange={(v) => setData('due_date', v)} />
                                <InputError message={errors.due_date} />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{editing ? 'Save changes' : 'Create task'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
