import { BranchName, BranchStatusBadge, MergeStatusBadge } from '@/components/dev/dev-status';
import { LinkedTasks, LinkedTestPoints, ReadinessNote } from '@/components/dev/linked-work';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import ReferencePicker, { type Reference } from '@/components/work/reference-picker';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { BranchDetail, ProjectWorkspaceHeader, User } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { GitPullRequestArrow, Pencil } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const ANY = 'any';

interface Props {
    project: ProjectWorkspaceHeader;
    branch: BranchDetail;
    devAdmins: Pick<User, 'id' | 'name'>[];
    can: { update: boolean; requestMerge: boolean };
}

function RequestMergeDialog({
    project,
    branch,
    devAdmins,
    open,
    onOpenChange,
}: {
    project: ProjectWorkspaceHeader;
    branch: BranchDetail;
    devAdmins: Props['devAdmins'];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, processing, errors, transform } = useForm({
        title: branch.name.replace(/^[a-z]+\//, '').replace(/[-_]/g, ' '),
        description: '',
        target_branch: branch.base_branch,
        reviewer_id: ANY,
    });

    transform((payload) => ({ ...payload, reviewer_id: payload.reviewer_id === ANY ? null : Number(payload.reviewer_id) }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.merge-requests.store', [project.id, branch.id]), { onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Request merge</DialogTitle>
                        <DialogDescription>
                            Ask a dev admin to review <BranchName name={branch.name} /> and merge it on GitHub.
                        </DialogDescription>
                    </DialogHeader>

                    <ReadinessNote readiness={branch.readiness} taskCount={branch.tasks.length} testCount={branch.test_points.length} />

                    <div className="grid gap-2">
                        <Label htmlFor="mr-title">Title</Label>
                        <Input id="mr-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="mr-description">
                            Notes for the reviewer <span className="text-muted-foreground">(optional)</span>
                        </Label>
                        <Textarea
                            id="mr-description"
                            rows={3}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Anything to look at closely, or to test by hand."
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="mr-target">Merge into</Label>
                            <Input
                                id="mr-target"
                                className="font-mono"
                                value={data.target_branch}
                                onChange={(e) => setData('target_branch', e.target.value.trim())}
                                required
                            />
                            <InputError message={errors.target_branch} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="mr-reviewer">Reviewer</Label>
                            <Select value={String(data.reviewer_id)} onValueChange={(value) => setData('reviewer_id', value)}>
                                <SelectTrigger id="mr-reviewer">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY}>Any dev admin</SelectItem>
                                    {devAdmins.map((admin) => (
                                        <SelectItem key={admin.id} value={String(admin.id)}>
                                            {admin.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.reviewer_id} />
                        </div>
                    </div>

                    {devAdmins.length === 0 && (
                        <p className="text-muted-foreground text-xs">
                            This project has no dev admin yet, so only someone who may merge on any project can review it. Make someone a dev admin on
                            the Members tab.
                        </p>
                    )}

                    <DialogFooter>
                        <Button disabled={processing}>Request merge</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditBranchDialog({
    project,
    branch,
    open,
    onOpenChange,
}: {
    project: ProjectWorkspaceHeader;
    branch: BranchDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, put, processing, errors } = useForm({ base_branch: branch.base_branch, description: branch.description ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('projects.branches.update', [project.id, branch.id]), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Edit branch</DialogTitle>
                        <DialogDescription>The name is fixed: it has to match the branch on GitHub.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-base">Created from</Label>
                        <Input
                            id="edit-base"
                            className="font-mono"
                            value={data.base_branch}
                            onChange={(e) => setData('base_branch', e.target.value.trim())}
                            required
                        />
                        <InputError message={errors.base_branch} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-description">Description</Label>
                        <Textarea id="edit-description" rows={5} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <DialogFooter>
                        <Button disabled={processing}>Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function BranchPage({ project, branch, devAdmins, can }: Props) {
    const format = useFormat();
    const [requesting, setRequesting] = useState(false);
    const [editing, setEditing] = useState(false);
    const [adding, setAdding] = useState<Reference[]>([]);
    const [addingPoints, setAddingPoints] = useState<Reference[]>([]);

    const link = (payload: { task_ids?: number[]; test_point_ids?: number[] }, done: () => void) =>
        router.post(route('projects.branches.links.store', [project.id, branch.id]), payload, { preserveScroll: true, onSuccess: done });

    const unlink = (kind: 'tasks' | 'test-points', id: number) =>
        router.delete(route('projects.branches.links.destroy', [project.id, branch.id, kind, id]), { preserveScroll: true });

    const live = branch.live_merge_request;

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="git"
            crumbs={[{ title: branch.name, href: route('projects.branches.show', [project.id, branch.id]) }]}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <Link href={route('projects.git', project.id)} className="text-muted-foreground text-xs hover:underline">
                        ← All branches
                    </Link>
                    <div className="flex flex-wrap items-center gap-3">
                        <BranchName name={branch.name} className="text-sm" />
                        <BranchStatusBadge status={branch.status} />
                    </div>
                    <p className="text-muted-foreground text-xs">
                        From <code className="font-mono">{branch.base_branch}</code> · registered {format.date(branch.created_at)}
                        {branch.creator && ` by ${branch.creator.name}`}
                        {branch.merged_at && ` · merged ${format.date(branch.merged_at)}`}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {can.update && (
                        <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                            <Pencil className="size-4" /> Edit
                        </Button>
                    )}
                    {can.update && !live && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.post(route('projects.branches.close', [project.id, branch.id]), {}, { preserveScroll: true })}
                        >
                            Close without merging
                        </Button>
                    )}
                    {live ? (
                        <Button asChild size="sm">
                            <Link href={route('projects.merge-requests.show', [project.id, live.id])}>
                                {live.reference} · <MergeStatusBadge status={live.status} className="text-primary-foreground" />
                            </Link>
                        </Button>
                    ) : (
                        can.requestMerge && (
                            <Button size="sm" onClick={() => setRequesting(true)}>
                                <GitPullRequestArrow className="size-4" /> Request merge
                            </Button>
                        )
                    )}
                </div>
            </div>

            <section className="rounded-xl border p-4">
                <h3 className="mb-2 text-sm font-medium">Description</h3>
                <p className="text-sm whitespace-pre-wrap">{branch.description || <span className="text-muted-foreground">No description.</span>}</p>
            </section>

            <ReadinessNote readiness={branch.readiness} taskCount={branch.tasks.length} testCount={branch.test_points.length} />

            <div className="grid gap-4 xl:grid-cols-2">
                <LinkedTasks
                    tasks={branch.tasks}
                    onRemove={can.update ? (task) => unlink('tasks', task.id) : undefined}
                    action={
                        can.update && (
                            <ReferencePicker
                                projectId={project.id}
                                kind="tasks"
                                value={adding}
                                placeholder="Add"
                                onChange={(next) => {
                                    setAdding(next);
                                    const fresh = next.filter((n) => !branch.tasks.some((t) => t.id === n.id));
                                    if (fresh.length) {
                                        link({ task_ids: fresh.map((n) => n.id) }, () => setAdding([]));
                                    }
                                }}
                            />
                        )
                    }
                />
                <LinkedTestPoints
                    points={branch.test_points}
                    onRemove={can.update ? (point) => unlink('test-points', point.id) : undefined}
                    action={
                        can.update && (
                            <ReferencePicker
                                projectId={project.id}
                                kind="test-points"
                                value={addingPoints}
                                placeholder="Add"
                                onChange={(next) => {
                                    setAddingPoints(next);
                                    const fresh = next.filter((n) => !branch.test_points.some((p) => p.id === n.id));
                                    if (fresh.length) {
                                        link({ test_point_ids: fresh.map((n) => n.id) }, () => setAddingPoints([]));
                                    }
                                }}
                            />
                        )
                    }
                />
            </div>

            {!can.update && branch.status === 'active' && live?.status === 'approved' && (
                <p className="text-muted-foreground text-xs">Linked work is frozen while {live.reference} is approved.</p>
            )}

            {branch.merge_requests.length > 0 && (
                <section className="space-y-2">
                    <h3 className="text-sm font-medium">Merge requests</h3>
                    <ul className="divide-y rounded-xl border">
                        {branch.merge_requests.map((mr) => (
                            <li key={mr.id} className="flex flex-wrap items-center gap-3 px-4 py-2.5 text-sm">
                                <span className="text-muted-foreground font-mono text-xs">{mr.reference}</span>
                                <Link
                                    href={route('projects.merge-requests.show', [project.id, mr.id])}
                                    className="min-w-0 flex-1 truncate hover:underline"
                                >
                                    {mr.title}
                                </Link>
                                <MergeStatusBadge status={mr.status} />
                                <span className="text-muted-foreground text-xs">{format.date(mr.created_at)}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {can.requestMerge && (
                <RequestMergeDialog project={project} branch={branch} devAdmins={devAdmins} open={requesting} onOpenChange={setRequesting} />
            )}
            {can.update && <EditBranchDialog project={project} branch={branch} open={editing} onOpenChange={setEditing} />}
        </ProjectWorkspaceLayout>
    );
}
