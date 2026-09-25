import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import ReferencePicker, { type Reference } from '@/components/work/reference-picker';
import type { Option, TestPointDetail, User } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

const UNASSIGNED = 'unassigned';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    projectId: number;
    statuses: Option[];
    priorities: Option[];
    assignees: Pick<User, 'id' | 'name'>[];
    point?: TestPointDetail | null;
    defaultStatus?: string;
}

export default function TestPointDialog({
    open,
    onOpenChange,
    projectId,
    statuses,
    priorities,
    assignees,
    point = null,
    defaultStatus = 'to_test',
}: Props) {
    const editing = Boolean(point);
    const [task, setTask] = useState<Reference[]>([]);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        title: '',
        steps: '',
        expected_result: '',
        actual_result: '',
        status: defaultStatus,
        priority: 'medium',
        assigned_to: UNASSIGNED,
        task_id: '' as string | number,
    });

    transform((payload) => ({ ...payload, assigned_to: payload.assigned_to === UNASSIGNED ? '' : payload.assigned_to }));

    // Refill whenever the dialog opens, so it never shows the previous point.
    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setTask(point?.task ? [point.task] : []);
        setData({
            title: point?.title ?? '',
            steps: point?.steps ?? '',
            expected_result: point?.expected_result ?? '',
            actual_result: point?.actual_result ?? '',
            status: point?.status ?? defaultStatus,
            priority: point?.priority ?? 'medium',
            assigned_to: point?.assigned_to ? String(point.assigned_to) : UNASSIGNED,
            task_id: point?.task_id ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, point?.id, defaultStatus]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        };

        if (editing && point) {
            put(route('projects.testing.update', [projectId, point.id]), done);
        } else {
            post(route('projects.testing.store', projectId), done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{editing ? `Edit ${point?.reference}` : 'New testing point'}</DialogTitle>
                        <DialogDescription>What to check, how, and what should happen. It gets a TP number when saved.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="tp-title">Title</Label>
                            <Input
                                id="tp-title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                required
                                autoFocus
                                placeholder="Leave request rejects overlapping dates"
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="tp-steps">Steps</Label>
                                <Textarea
                                    id="tp-steps"
                                    rows={5}
                                    value={data.steps}
                                    onChange={(e) => setData('steps', e.target.value)}
                                    placeholder={'1. Sign in as an employee\n2. Request leave for 3–5 May\n3. Request 4–6 May'}
                                />
                                <InputError message={errors.steps} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tp-expected">Expected result</Label>
                                <Textarea
                                    id="tp-expected"
                                    rows={5}
                                    value={data.expected_result}
                                    onChange={(e) => setData('expected_result', e.target.value)}
                                    placeholder="The second request is refused with a clear message."
                                />
                                <InputError message={errors.expected_result} />
                            </div>
                        </div>

                        {editing && (
                            <div className="grid gap-2">
                                <Label htmlFor="tp-actual">
                                    Actual result <span className="text-muted-foreground">(what happened on the last run)</span>
                                </Label>
                                <Textarea
                                    id="tp-actual"
                                    rows={3}
                                    value={data.actual_result}
                                    onChange={(e) => setData('actual_result', e.target.value)}
                                />
                                <InputError message={errors.actual_result} />
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="tp-status">Status</Label>
                                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                    <SelectTrigger id="tp-status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tp-priority">Priority</Label>
                                <Select value={data.priority} onValueChange={(value) => setData('priority', value)}>
                                    <SelectTrigger id="tp-priority">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priorities.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tp-assignee">Tester</Label>
                                <Select value={data.assigned_to} onValueChange={(value) => setData('assigned_to', value)}>
                                    <SelectTrigger id="tp-assignee">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={UNASSIGNED}>Unassigned</SelectItem>
                                        {assignees.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.assigned_to} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label>
                                Verifies task <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <ReferencePicker
                                projectId={projectId}
                                kind="tasks"
                                single
                                value={task}
                                placeholder="Link a task"
                                onChange={(next) => {
                                    setTask(next);
                                    setData('task_id', next[0]?.id ?? '');
                                }}
                            />
                            <InputError message={errors.task_id} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{editing ? 'Save changes' : 'Create testing point'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
