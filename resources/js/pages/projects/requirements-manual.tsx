import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { ProjectWorkspaceHeader } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, Lock, Plus, Save, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo } from 'react';

type Point = { module: string; description: string; notes: string };

interface Props {
    project: ProjectWorkspaceHeader;
    next: string;
    /** The version these points start from, if any. */
    from: string | null;
    points: { module: string; description: string; notes: string | null }[];
    limits: { module: number; description: number; notes: number };
}

const blank = (): Point => ({ module: '', description: '', notes: '' });

/**
 * Typing a version in by hand. It starts as a copy of the latest version's
 * points, to change what changed; numbers are given in order as you go.
 */
export default function RequirementsManual({ project, next, from, points, limits }: Props) {
    const { data, setData, post, processing, errors } = useForm<{ points: Point[] }>({
        points: points.length ? points.map((p) => ({ module: p.module, description: p.description, notes: p.notes ?? '' })) : [blank()],
    });

    const err = (key: string) => (errors as Record<string, string | undefined>)[key];
    const modules = useMemo(() => [...new Set(data.points.map((p) => p.module.trim()).filter(Boolean))].sort(), [data.points]);

    const set = (index: number, patch: Partial<Point>) =>
        setData(
            'points',
            data.points.map((p, i) => (i === index ? { ...p, ...patch } : p)),
        );
    const move = (index: number, by: -1 | 1) => {
        const list = [...data.points];
        const [row] = list.splice(index, 1);
        list.splice(index + by, 0, row);
        setData('points', list);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.requirements.manual.store', project.id), { preserveScroll: true });
    };

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="requirements"
            actions={
                <Button asChild size="sm" variant="ghost">
                    <Link href={route('projects.requirements.index', project.id)}>
                        <ArrowLeft className="size-4" /> Requirements
                    </Link>
                </Button>
            }
        >
            <form onSubmit={submit} className="flex max-w-5xl flex-col gap-4">
                <div className="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4">
                    <span className="bg-primary/10 text-primary inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-lg font-bold">
                        <Lock className="size-3.5" aria-hidden /> {next}
                    </span>
                    <p className="text-muted-foreground flex-1 text-sm">
                        {from ? `Starts from the ${from} points — change, add or remove what changed. ` : ''}S.No is given automatically, in the order
                        below.
                    </p>
                </div>

                <datalist id="modules">
                    {modules.map((m) => (
                        <option key={m} value={m} />
                    ))}
                </datalist>

                <InputError message={errors.points} />

                <ol className="space-y-2">
                    {data.points.map((point, index) => (
                        <li
                            key={index}
                            className="bg-card grid gap-3 rounded-xl border p-3 sm:grid-cols-[3.5rem_minmax(0,12rem)_minmax(0,1fr)] lg:grid-cols-[3.5rem_minmax(0,12rem)_minmax(0,2fr)_minmax(0,1.3fr)_auto]"
                        >
                            <div className="flex items-center gap-1 sm:flex-col sm:items-start">
                                <span className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">S.No</span>
                                <span className="font-mono text-lg font-bold">{index + 1}</span>
                            </div>
                            <div className="space-y-1">
                                <label className="text-muted-foreground text-xs font-medium" htmlFor={`m-${index}`}>
                                    Module
                                </label>
                                <Input
                                    id={`m-${index}`}
                                    list="modules"
                                    maxLength={limits.module}
                                    value={point.module}
                                    onChange={(e) => set(index, { module: e.target.value })}
                                    placeholder="e.g. Login"
                                    required
                                />
                                <InputError message={err(`points.${index}.module`)} />
                            </div>
                            <div className="space-y-1">
                                <label className="text-muted-foreground text-xs font-medium" htmlFor={`d-${index}`}>
                                    Description
                                </label>
                                <Textarea
                                    id={`d-${index}`}
                                    rows={2}
                                    maxLength={limits.description}
                                    value={point.description}
                                    onChange={(e) => set(index, { description: e.target.value })}
                                    placeholder="What the requirement is"
                                    required
                                />
                                <InputError message={err(`points.${index}.description`)} />
                            </div>
                            <div className="space-y-1 sm:col-start-3 lg:col-start-auto">
                                <label className="text-muted-foreground text-xs font-medium" htmlFor={`n-${index}`}>
                                    Additional notes <span className="font-normal">(optional)</span>
                                </label>
                                <Textarea
                                    id={`n-${index}`}
                                    rows={2}
                                    maxLength={limits.notes}
                                    value={point.notes}
                                    onChange={(e) => set(index, { notes: e.target.value })}
                                />
                            </div>
                            <div className="flex items-start justify-end gap-0.5 sm:col-span-3 lg:col-span-1 lg:flex-col">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="size-8 p-0"
                                    disabled={index === 0}
                                    onClick={() => move(index, -1)}
                                    aria-label={`Move ${index + 1} up`}
                                >
                                    <ArrowUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="size-8 p-0"
                                    disabled={index === data.points.length - 1}
                                    onClick={() => move(index, 1)}
                                    aria-label={`Move ${index + 1} down`}
                                >
                                    <ArrowDown className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-muted-foreground hover:text-destructive size-8 p-0"
                                    disabled={data.points.length === 1}
                                    onClick={() =>
                                        setData(
                                            'points',
                                            data.points.filter((_, i) => i !== index),
                                        )
                                    }
                                    aria-label={`Remove ${index + 1}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ol>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button type="button" variant="outline" onClick={() => setData('points', [...data.points, blank()])}>
                        <Plus className="size-4" /> Add requirement {data.points.length + 1}
                    </Button>
                    <Button disabled={processing}>
                        <Save className="size-4" /> Save {data.points.length} requirements as {next}
                    </Button>
                </div>
            </form>
        </ProjectWorkspaceLayout>
    );
}
