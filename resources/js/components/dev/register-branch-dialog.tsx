import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ReferencePicker, { type Reference } from '@/components/work/reference-picker';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

type Form = { name: string; base_branch: string; description: string; task_ids: number[]; test_point_ids: number[] };

interface Props {
    projectId: number;
    defaultBranch: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Record a branch that was just created on GitHub: its exact name, what it is
 * for, and the task and testing points it delivers.
 */
export default function RegisterBranchDialog({ projectId, defaultBranch, open, onOpenChange }: Props) {
    const [tasks, setTasks] = useState<Reference[]>([]);
    const [points, setPoints] = useState<Reference[]>([]);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<Form>({
        name: '',
        base_branch: defaultBranch,
        description: '',
        task_ids: [],
        test_point_ids: [],
    });

    useEffect(() => {
        if (open) {
            reset();
            clearErrors();
            setTasks([]);
            setPoints([]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.branches.store', projectId), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    const firstError = (prefix: string) => Object.entries(errors).find(([key]) => key.startsWith(prefix))?.[1];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Register a branch</DialogTitle>
                        <DialogDescription>
                            Create the branch on GitHub first, then record it here with exactly the same name. Nothing is sent to GitHub.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="grid gap-4 sm:grid-cols-[2fr_1fr]">
                            <div className="grid gap-2">
                                <Label htmlFor="branch-name">Branch name</Label>
                                <Input
                                    id="branch-name"
                                    className="font-mono"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value.trim())}
                                    required
                                    autoFocus
                                    placeholder="feature/leave-approvals"
                                    autoComplete="off"
                                    spellCheck={false}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="branch-base">Created from</Label>
                                <Input
                                    id="branch-base"
                                    className="font-mono"
                                    value={data.base_branch}
                                    onChange={(e) => setData('base_branch', e.target.value.trim())}
                                    required
                                    spellCheck={false}
                                />
                                <InputError message={errors.base_branch} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="branch-description">Description</Label>
                            <Textarea
                                id="branch-description"
                                rows={4}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                required
                                placeholder="What this branch changes and why."
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Task points</Label>
                            <ReferencePicker
                                warnBranches
                                projectId={projectId}
                                kind="tasks"
                                value={tasks}
                                onChange={(next) => {
                                    setTasks(next);
                                    setData(
                                        'task_ids',
                                        next.map((t) => t.id),
                                    );
                                }}
                            />
                            <InputError message={firstError('task_ids')} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Testing points</Label>
                            <ReferencePicker
                                warnBranches
                                projectId={projectId}
                                kind="test-points"
                                value={points}
                                onChange={(next) => {
                                    setPoints(next);
                                    setData(
                                        'test_point_ids',
                                        next.map((p) => p.id),
                                    );
                                }}
                            />
                            <p className="text-muted-foreground text-xs">Pick the points a reviewer should see passing before this is merged.</p>
                            <InputError message={firstError('test_point_ids')} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>Register branch</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
