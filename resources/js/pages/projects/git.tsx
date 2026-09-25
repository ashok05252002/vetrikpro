import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { BranchName, BranchStatusBadge, MergeStatusBadge } from '@/components/dev/dev-status';
import MergeRequestTable from '@/components/dev/merge-request-table';
import RegisterBranchDialog from '@/components/dev/register-branch-dialog';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { BranchRow, MergeRequestRow, Option, Paginated, ProjectWorkspaceHeader } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ExternalLink, GitBranchPlus } from 'lucide-react';
import { useState } from 'react';

type Show = 'branches' | 'merge-requests';

interface Props {
    project: ProjectWorkspaceHeader;
    show: Show;
    branches?: Paginated<BranchRow>;
    mergeRequests?: Paginated<MergeRequestRow>;
    branchStatuses: Option[];
    mergeStatuses: Option[];
    filters: { search?: string; status?: string };
    can: { create: boolean };
}

function Branches({ project, branches }: { project: ProjectWorkspaceHeader; branches: Paginated<BranchRow> }) {
    const format = useFormat();

    return (
        <div className="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Branch</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="hidden md:table-cell">Linked work</TableHead>
                        <TableHead>Merge request</TableHead>
                        <TableHead className="hidden lg:table-cell">Registered</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {branches.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                No branches registered.
                            </TableCell>
                        </TableRow>
                    )}
                    {branches.data.map((branch) => (
                        <TableRow key={branch.id}>
                            <TableCell className="max-w-72">
                                <Link href={route('projects.branches.show', [project.id, branch.id])} className="hover:underline">
                                    <BranchName name={branch.name} />
                                </Link>
                                <p className="text-muted-foreground mt-1 text-xs">from {branch.base_branch}</p>
                            </TableCell>
                            <TableCell>
                                <BranchStatusBadge status={branch.status} />
                            </TableCell>
                            <TableCell className="text-muted-foreground hidden text-xs md:table-cell">
                                {branch.tasks_count} task{branch.tasks_count === 1 ? '' : 's'} · {branch.test_points_count} testing
                            </TableCell>
                            <TableCell>
                                {branch.live_merge_request ? (
                                    <Link
                                        href={route('projects.merge-requests.show', [project.id, branch.live_merge_request.id])}
                                        className="inline-flex items-center gap-2 hover:underline"
                                    >
                                        <span className="font-mono text-xs">{branch.live_merge_request.reference}</span>
                                        <MergeStatusBadge status={branch.live_merge_request.status} />
                                    </Link>
                                ) : (
                                    <span className="text-muted-foreground text-xs">—</span>
                                )}
                            </TableCell>
                            <TableCell className="text-muted-foreground hidden text-xs lg:table-cell">
                                {format.date(branch.created_at)}
                                {branch.creator && ` by ${branch.creator.name}`}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

export default function Git({ project, show, branches, mergeRequests, branchStatuses, mergeStatuses, filters, can }: Props) {
    const [registering, setRegistering] = useState(false);
    const url = route('projects.git', project.id);

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="git"
            actions={
                can.create && (
                    <Button size="sm" onClick={() => setRegistering(true)}>
                        <GitBranchPlus className="size-4" /> Register branch
                    </Button>
                )
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <ToggleGroup
                    type="single"
                    size="sm"
                    variant="outline"
                    value={show}
                    onValueChange={(next) => next && next !== show && router.get(url, next === 'merge-requests' ? { show: next } : {})}
                >
                    <ToggleGroupItem value="branches" className="px-3 text-xs">
                        Branches
                    </ToggleGroupItem>
                    <ToggleGroupItem value="merge-requests" className="px-3 text-xs">
                        Merge requests
                    </ToggleGroupItem>
                </ToggleGroup>

                <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-xs">
                    <span>
                        Merges into <code className="font-mono">{project.default_branch}</code> by default
                    </span>
                    {project.repository_url && (
                        <a href={project.repository_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 hover:underline">
                            Repository <ExternalLink className="size-3" />
                        </a>
                    )}
                </div>
            </div>

            <FilterBar
                key={show}
                url={url}
                filters={filters}
                keep={show === 'merge-requests' ? { show } : {}}
                searchPlaceholder={show === 'branches' ? 'Branch name…' : 'Title, branch or MR-3…'}
                selects={[{ name: 'status', placeholder: 'Any status', options: show === 'branches' ? branchStatuses : mergeStatuses }]}
            />

            {show === 'branches' && branches && (
                <>
                    <Branches project={project} branches={branches} />
                    <Pagination meta={branches} />
                </>
            )}

            {show === 'merge-requests' && mergeRequests && (
                <>
                    <MergeRequestTable rows={mergeRequests.data} />
                    <Pagination meta={mergeRequests} />
                </>
            )}

            <RegisterBranchDialog projectId={project.id} defaultBranch={project.default_branch} open={registering} onOpenChange={setRegistering} />
        </ProjectWorkspaceLayout>
    );
}
