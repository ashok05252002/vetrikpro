import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import Pill from '@/components/ui/pill';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import IconChip from '@/components/viz/icon-chip';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import { cn } from '@/lib/utils';
import type { Paginated, ProjectWorkspaceHeader, User } from '@/types';
import { Link } from '@inertiajs/react';
import { Download, FileSpreadsheet, ListPlus, PencilLine } from 'lucide-react';

interface Version {
    id: number;
    label: string;
    source: 'excel' | 'manual';
    file_name: string | null;
    created_at: string;
    points_count: number;
    creator: Pick<User, 'id' | 'name'> | null;
}

interface Point {
    id: number;
    number: number;
    module: string;
    description: string;
    notes: string | null;
}

interface Props {
    project: ProjectWorkspaceHeader;
    versions: Version[];
    current: { id: number } | null;
    points: Paginated<Point> | null;
    modules: string[];
    next: string;
    filters: { search?: string; module?: string };
    can: { manage: boolean };
}

/** Each module gets a steady colour, so a module reads the same down the table. */
const MODULE_TONES = ['indigo', 'violet', 'sky', 'teal', 'pink', 'green', 'blue'];
const moduleColor = (name: string) => {
    let h = 0;
    for (const c of name) h = (h * 31 + c.charCodeAt(0)) >>> 0;
    return `var(--tone-${MODULE_TONES[h % MODULE_TONES.length]})`;
};

export default function Requirements({ project, versions, current, points, modules, next, filters, can }: Props) {
    const format = useFormat();
    const selected = versions.find((v) => v.id === current?.id) ?? null;

    const actions = can.manage && (
        <div className="flex flex-wrap gap-2">
            <Button asChild size="sm" variant="outline">
                <a href={route('projects.requirements.template', project.id)}>
                    <Download className="size-4" /> Template
                </a>
            </Button>
            <Button asChild size="sm" variant="outline">
                <Link href={route('projects.requirements.manual', project.id)}>
                    <PencilLine className="size-4" /> Add manually
                </Link>
            </Button>
            <Button asChild size="sm">
                <Link href={route('projects.requirements.upload', project.id)}>
                    <FileSpreadsheet className="size-4" /> Upload Excel
                </Link>
            </Button>
        </div>
    );

    if (versions.length === 0) {
        return (
            <ProjectWorkspaceLayout project={project} tab="requirements">
                <div className="bg-card flex flex-col items-center gap-4 rounded-xl border px-6 py-14 text-center">
                    <IconChip icon={ListPlus} tone="violet" />
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">No requirements yet</h2>
                        <p className="text-muted-foreground max-w-md text-sm">
                            Requirements are kept as numbered points, in versions starting at {next}. Import them from the Excel template, or type
                            them in.
                        </p>
                    </div>
                    {actions || <p className="text-muted-foreground text-sm">The project's owner or leads add them.</p>}
                </div>
            </ProjectWorkspaceLayout>
        );
    }

    return (
        <ProjectWorkspaceLayout project={project} tab="requirements" actions={actions}>
            <div className="space-y-2">
                <p className="text-muted-foreground text-xs">
                    Versions
                    {can.manage && (
                        <>
                            {' '}
                            · the next will be <strong className="text-foreground">{next}</strong>
                        </>
                    )}
                </p>
                <div className="flex gap-2 overflow-x-auto pb-1">
                    {versions.map((v) => (
                        <Link
                            key={v.id}
                            href={route('projects.requirements.index', [project.id, { version: v.id }])}
                            preserveScroll
                            className={cn(
                                'flex shrink-0 flex-col rounded-lg border px-3 py-2 text-left transition-colors',
                                v.id === selected?.id ? 'border-primary bg-primary/5' : 'hover:bg-muted',
                            )}
                        >
                            <span className={cn('text-sm font-semibold', v.id === selected?.id && 'text-primary')}>{v.label}</span>
                            <span className="text-muted-foreground text-[11px]">
                                {v.points_count} points · {format.date(v.created_at)}
                            </span>
                        </Link>
                    ))}
                </div>
            </div>

            {selected && (
                <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                    {selected.source === 'excel' ? (
                        <Pill color="var(--tone-green)" icon={FileSpreadsheet}>
                            Imported from Excel
                        </Pill>
                    ) : (
                        <Pill color="var(--tone-violet)" icon={PencilLine}>
                            Entered by hand
                        </Pill>
                    )}
                    {selected.file_name && <span className="font-mono">{selected.file_name}</span>}
                    {selected.creator && <span>by {selected.creator.name}</span>}
                </div>
            )}

            <FilterBar
                url={route('projects.requirements.index', project.id)}
                filters={filters}
                keep={current ? { version: String(current.id) } : undefined}
                searchPlaceholder="Search description, notes or S.No…"
                selects={[{ name: 'module', placeholder: 'All modules', options: modules.map((m) => ({ value: m, label: m })) }]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-24">S.No</TableHead>
                            <TableHead className="w-44">Module</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead className="hidden lg:table-cell">Additional notes</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {points?.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={4} className="text-muted-foreground py-10 text-center">
                                    No requirements match.
                                </TableCell>
                            </TableRow>
                        )}
                        {points?.data.map((point) => (
                            <TableRow key={point.id} className="align-top">
                                <TableCell className="font-mono text-xs font-semibold">{point.number}</TableCell>
                                <TableCell>
                                    <Pill color={moduleColor(point.module)}>{point.module}</Pill>
                                </TableCell>
                                <TableCell className="text-sm whitespace-pre-line">{point.description}</TableCell>
                                <TableCell className="text-muted-foreground hidden text-sm whitespace-pre-line lg:table-cell">
                                    {point.notes ?? '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {points && <Pagination meta={points} />}
        </ProjectWorkspaceLayout>
    );
}
