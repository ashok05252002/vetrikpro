import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import TestStatusBadge from '@/components/work/test-status';
import type { TestPointStatus } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export interface RunCandidate {
    id: number;
    reference: string;
    title: string;
    status: TestPointStatus;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    projectId: number;
    points: RunCandidate[];
}

/** Quick picks, so a retest round or a full regression is one click. */
const PICKS: { label: string; match: (p: RunCandidate) => boolean }[] = [
    { label: 'Ready for test', match: (p) => p.status === 'ready_for_test' },
    { label: 'Not closed', match: (p) => p.status !== 'closed' },
    { label: 'All', match: () => true },
    { label: 'None', match: () => false },
];

export default function NewRunDialog({ open, onOpenChange, projectId, points }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        description: '',
        points: [] as number[],
    });

    useEffect(() => {
        if (open) {
            reset();
            clearErrors();
            // A retest round is the usual run: start from what developers have handed back.
            setData(
                'points',
                points.filter(PICKS[0].match).map((p) => p.id),
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const toggle = (id: number, checked: boolean) =>
        setData('points', checked ? [...data.points, id] : data.points.filter((existing) => existing !== id));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('testing.runs.store', projectId), { onSuccess: () => onOpenChange(false) });
    };

    const pointsError = errors.points ?? Object.entries(errors).find(([key]) => key.startsWith('points.'))?.[1];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>New test run</DialogTitle>
                        <DialogDescription>
                            A named round of testing. Each point gets its own result in this run, so earlier rounds are kept.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="run-name">Name</Label>
                        <Input
                            id="run-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Release 1.2 regression"
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="run-description">Notes (optional)</Label>
                        <Textarea
                            id="run-description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={2}
                            placeholder="Build, environment, anything testers should know"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label>
                                Testing points <span className="text-muted-foreground font-normal">({data.points.length} chosen)</span>
                            </Label>
                            <div className="flex flex-wrap gap-1">
                                {PICKS.map((pick) => (
                                    <Button
                                        key={pick.label}
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() =>
                                            setData(
                                                'points',
                                                points.filter(pick.match).map((p) => p.id),
                                            )
                                        }
                                    >
                                        {pick.label}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        {points.length === 0 ? (
                            <p className="text-muted-foreground rounded-md border p-4 text-sm">
                                This project has no testing points yet. Add some on the Testing points tab first.
                            </p>
                        ) : (
                            <ul className="max-h-64 divide-y overflow-y-auto rounded-md border">
                                {points.map((point) => (
                                    <li key={point.id}>
                                        <label className="hover:bg-muted/50 flex cursor-pointer items-center gap-3 px-3 py-2 text-sm">
                                            <Checkbox
                                                checked={data.points.includes(point.id)}
                                                onCheckedChange={(checked) => toggle(point.id, checked === true)}
                                            />
                                            <span className="text-muted-foreground w-12 shrink-0 font-mono text-xs">{point.reference}</span>
                                            <span className="min-w-0 flex-1 truncate">{point.title}</span>
                                            <TestStatusBadge status={point.status} className="shrink-0" />
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <InputError message={pointsError} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing || data.points.length === 0}>Start run</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
