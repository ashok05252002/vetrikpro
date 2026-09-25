import { BranchName, MergeStatusBadge } from '@/components/dev/dev-status';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFormat } from '@/hooks/use-format';
import type { MergeRequestRow } from '@/types';
import { Link } from '@inertiajs/react';

/** Merge requests as rows, for a project's Git tab and the cross-project inbox. */
export default function MergeRequestTable({ rows, showProject = false }: { rows: MergeRequestRow[]; showProject?: boolean }) {
    const format = useFormat();

    return (
        <div className="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-20">#</TableHead>
                        <TableHead>Merge request</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="hidden md:table-cell">Requested by</TableHead>
                        <TableHead className="hidden lg:table-cell">Reviewer</TableHead>
                        <TableHead className="hidden lg:table-cell">Opened</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                No merge requests here.
                            </TableCell>
                        </TableRow>
                    )}
                    {rows.map((mr) => (
                        <TableRow key={mr.id}>
                            <TableCell className="text-muted-foreground font-mono text-xs">{mr.reference}</TableCell>
                            <TableCell className="max-w-96">
                                <Link href={route('projects.merge-requests.show', [mr.project_id, mr.id])} className="font-medium hover:underline">
                                    {mr.title}
                                </Link>
                                <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                    {showProject && mr.project && <span>{mr.project.name} ·</span>}
                                    {mr.branch && <BranchName name={mr.branch.name} />}
                                    <span>→ {mr.target_branch}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <MergeStatusBadge status={mr.status} />
                            </TableCell>
                            <TableCell className="text-muted-foreground hidden md:table-cell">{mr.requester?.name ?? '—'}</TableCell>
                            <TableCell className="text-muted-foreground hidden lg:table-cell">{mr.reviewer?.name ?? 'Any dev admin'}</TableCell>
                            <TableCell className="text-muted-foreground hidden text-xs lg:table-cell">{format.date(mr.created_at)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
