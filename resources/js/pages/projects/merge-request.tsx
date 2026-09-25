import { BranchName, MergeStatusBadge, mergeStatusSpec } from '@/components/dev/dev-status';
import { LinkedTasks, LinkedTestPoints, ReadinessNote } from '@/components/dev/linked-work';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { MergeRequestDetail, MergeRequestEvent, MergeRequestStatus, ProjectWorkspaceHeader, SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, GitMerge, MessageSquareWarning, RotateCcw, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    mergeRequest: MergeRequestDetail;
    can: { review: boolean; isReviewer: boolean; resubmit: boolean; close: boolean; comment: boolean };
}

interface Action {
    to: MergeRequestStatus;
    label: string;
    icon: typeof CheckCircle2;
    variant: 'default' | 'outline' | 'destructive';
    /** Opens a dialog asking for a note; `required` makes it mandatory. */
    note?: { required: boolean; title: string; description: string; placeholder: string };
}

const eventText: Record<MergeRequestEvent['action'], string> = {
    opened: 'opened this merge request',
    approved: 'approved',
    changes_requested: 'requested changes',
    resubmitted: 'sent it back for review',
    merged: 'marked it as merged',
    closed: 'closed it',
    commented: 'commented',
};

function actionsFor(mr: MergeRequestDetail, can: Props['can']): Action[] {
    const next = new Set(mr.next);
    const actions: Action[] = [];

    if (can.review && next.has('approved')) {
        actions.push({ to: 'approved', label: 'Approve', icon: CheckCircle2, variant: 'default' });
    }
    if (can.review && next.has('merged')) {
        actions.push({
            to: 'merged',
            label: 'Mark as merged',
            icon: GitMerge,
            variant: 'default',
            note: {
                required: false,
                title: `Mark ${mr.reference} as merged`,
                description: `Do this after merging ${mr.branch.name} into ${mr.target_branch} on GitHub. The branch is then closed here.`,
                placeholder: 'Merge commit or anything worth recording (optional).',
            },
        });
    }
    if (can.review && next.has('changes_requested')) {
        actions.push({
            to: 'changes_requested',
            label: mr.status === 'approved' ? 'Withdraw approval' : 'Request changes',
            icon: MessageSquareWarning,
            variant: 'outline',
            note: {
                required: true,
                title: 'Request changes',
                description: 'Say what needs to change. The requester sees this on the timeline.',
                placeholder: 'Handle half-day leave before this goes in.',
            },
        });
    }
    if (can.resubmit && next.has('open')) {
        actions.push({
            to: 'open',
            label: 'Send back for review',
            icon: RotateCcw,
            variant: 'default',
            note: {
                required: false,
                title: 'Send back for review',
                description: 'Tell the reviewer what you changed.',
                placeholder: 'Half-day leave handled; TP-3 passes now.',
            },
        });
    }
    if (can.close && next.has('closed')) {
        actions.push({
            to: 'closed',
            label: 'Close',
            icon: XCircle,
            variant: 'outline',
            note: {
                required: false,
                title: `Close ${mr.reference}`,
                description: 'The branch stays registered and can ask to merge again later.',
                placeholder: 'Why it is being closed (optional).',
            },
        });
    }

    return actions;
}

function TransitionDialog({
    project,
    mr,
    action,
    onClose,
}: {
    project: ProjectWorkspaceHeader;
    mr: MergeRequestDetail;
    action: Action;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({ to: action.to, expected: mr.status, note: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.merge-requests.transition', [project.id, mr.id]), { preserveScroll: true, onFinish: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{action.note!.title}</DialogTitle>
                        <DialogDescription>{action.note!.description}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="transition-note">
                            Note {!action.note!.required && <span className="text-muted-foreground">(optional)</span>}
                        </Label>
                        <Textarea
                            id="transition-note"
                            rows={4}
                            autoFocus
                            required={action.note!.required}
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            placeholder={action.note!.placeholder}
                        />
                        <InputError message={errors.note} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button variant={action.variant === 'destructive' ? 'destructive' : 'default'} disabled={processing}>
                            {action.label}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Timeline({ events }: { events: MergeRequestEvent[] }) {
    const format = useFormat();

    return (
        <ol className="space-y-4">
            {events.map((event) => {
                const status = event.to_status ? mergeStatusSpec[event.to_status] : null;
                const Icon = status?.icon;

                return (
                    <li key={event.id} className="flex gap-3">
                        <UserAvatar name={event.user?.name} className="mt-0.5 size-7" />
                        <div className="min-w-0 flex-1 space-y-1">
                            <p className="flex flex-wrap items-center gap-1.5 text-sm">
                                <span className="font-medium">{event.user?.name ?? 'Someone'}</span>
                                <span className="text-muted-foreground">{eventText[event.action]}</span>
                                {Icon && event.action !== 'opened' && <Icon aria-hidden className="size-3.5" style={{ color: status.color }} />}
                                <span className="text-muted-foreground text-xs">· {format.date(event.created_at)}</span>
                            </p>
                            {event.note && <p className="bg-muted/50 rounded-lg px-3 py-2 text-sm whitespace-pre-wrap">{event.note}</p>}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

function CommentBox({ project, mr }: { project: ProjectWorkspaceHeader; mr: MergeRequestDetail }) {
    const { data, setData, post, processing, errors, reset } = useForm({ note: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.merge-requests.comments.store', [project.id, mr.id]), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <Label htmlFor="mr-comment" className="sr-only">
                Comment
            </Label>
            <Textarea id="mr-comment" rows={3} value={data.note} onChange={(e) => setData('note', e.target.value)} placeholder="Add a comment…" />
            <InputError message={errors.note} />
            <div className="flex justify-end">
                <Button size="sm" disabled={processing || data.note.trim() === ''}>
                    Comment
                </Button>
            </div>
        </form>
    );
}

export default function MergeRequestPage({ project, mergeRequest: mr, can }: Props) {
    const format = useFormat();
    const { auth } = usePage<SharedData>().props;
    const [pending, setPending] = useState<Action | null>(null);
    const quick = useForm({ to: '' as MergeRequestStatus | '', expected: mr.status, note: '' });

    const actions = actionsFor(mr, can);
    const ownRequest = mr.requester?.id === auth.user.id;

    const run = (action: Action) => {
        if (action.note) {
            setPending(action);
            return;
        }
        quick.transform(() => ({ to: action.to, expected: mr.status, note: '' }));
        quick.post(route('projects.merge-requests.transition', [project.id, mr.id]), { preserveScroll: true });
    };

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="git"
            crumbs={[{ title: mr.reference, href: route('projects.merge-requests.show', [project.id, mr.id]) }]}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <Link
                        href={route('projects.git', [project.id, { show: 'merge-requests' }])}
                        className="text-muted-foreground text-xs hover:underline"
                    >
                        ← All merge requests
                    </Link>
                    <h2 className="text-lg font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-sm font-normal">{mr.reference}</span>
                        {mr.title}
                    </h2>
                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                        <MergeStatusBadge status={mr.status} />
                        <span>·</span>
                        <Link href={route('projects.branches.show', [project.id, mr.branch.id])} className="hover:underline">
                            <BranchName name={mr.branch.name} />
                        </Link>
                        <span>→</span>
                        <code className="font-mono">{mr.target_branch}</code>
                        <span>· by {mr.requester?.name ?? 'someone'}</span>
                        <span>· reviewer: {mr.reviewer?.name ?? 'any dev admin'}</span>
                    </div>
                    {mr.merged_at && (
                        <p className="text-muted-foreground text-xs">
                            Merged {format.date(mr.merged_at)}
                            {mr.merged_by && ` by ${mr.merged_by.name}`}
                        </p>
                    )}
                </div>

                {actions.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions.map((action) => (
                            <Button key={action.to} size="sm" variant={action.variant} onClick={() => run(action)} disabled={quick.processing}>
                                <action.icon className="size-4" /> {action.label}
                            </Button>
                        ))}
                    </div>
                )}
            </div>

            {can.isReviewer && ownRequest && mr.status === 'open' && (
                <p className="text-muted-foreground text-xs">You asked for this merge, so another dev admin has to review it.</p>
            )}

            <ReadinessNote readiness={mr.readiness} taskCount={mr.tasks.length} testCount={mr.test_points.length} />

            <div className="grid gap-6 xl:grid-cols-[3fr_2fr]">
                <div className="space-y-4">
                    {(mr.description || mr.branch.description) && (
                        <section className="space-y-3 rounded-xl border p-4">
                            {mr.branch.description && (
                                <div>
                                    <h3 className="mb-1 text-sm font-medium">What the branch does</h3>
                                    <p className="text-sm whitespace-pre-wrap">{mr.branch.description}</p>
                                </div>
                            )}
                            {mr.description && (
                                <div>
                                    <h3 className="mb-1 text-sm font-medium">Notes for the reviewer</h3>
                                    <p className="text-sm whitespace-pre-wrap">{mr.description}</p>
                                </div>
                            )}
                        </section>
                    )}
                    <LinkedTasks tasks={mr.tasks} />
                    <LinkedTestPoints points={mr.test_points} />
                </div>

                <section className="space-y-4">
                    <h3 className="text-sm font-medium">Timeline</h3>
                    <Timeline events={mr.events} />
                    {can.comment && <CommentBox project={project} mr={mr} />}
                </section>
            </div>

            {pending && <TransitionDialog project={project} mr={mr} action={pending} onClose={() => setPending(null)} />}
        </ProjectWorkspaceLayout>
    );
}
