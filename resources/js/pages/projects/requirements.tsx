import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import FileUploadDialog from '@/components/work/file-upload-dialog';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import { formatBytes } from '@/lib/files';
import type { Paginated, ProjectWorkspaceHeader, RequirementSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { Download, FilePlus2, FileText } from 'lucide-react';
import { useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    documents: Paginated<RequirementSummary>;
    filters: { search?: string };
    can: { create: boolean };
}

export default function Requirements({ project, documents, filters, can }: Props) {
    const format = useFormat();
    const [adding, setAdding] = useState(false);

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="requirements"
            actions={
                can.create && (
                    <Button size="sm" onClick={() => setAdding(true)}>
                        <FilePlus2 className="size-4" /> New document
                    </Button>
                )
            }
        >
            <p className="text-muted-foreground max-w-3xl text-sm">
                Each document keeps every version. When a requirement changes, upload the new file to the same document rather than adding a new one —
                earlier versions stay available.
            </p>

            <FilterBar url={route('projects.requirements.index', project.id)} filters={filters} searchPlaceholder="Title or number, e.g. REQ-2…" />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-20">#</TableHead>
                            <TableHead>Document</TableHead>
                            <TableHead>Version</TableHead>
                            <TableHead className="hidden md:table-cell">Last updated</TableHead>
                            <TableHead className="text-right">Latest</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {documents.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                    {filters.search ? 'No documents match.' : 'No requirement documents yet.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {documents.data.map((document) => (
                            <TableRow key={document.id}>
                                <TableCell className="text-muted-foreground font-mono text-xs">{document.reference}</TableCell>
                                <TableCell>
                                    <div className="flex items-start gap-3">
                                        <FileText className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                        <div className="min-w-0">
                                            <Link
                                                href={route('projects.requirements.show', [project.id, document.id])}
                                                className="font-medium hover:underline"
                                            >
                                                {document.title}
                                            </Link>
                                            {document.current && (
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {document.current.original_name} · {formatBytes(document.current.size)}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">v{document.current?.version ?? 0}</Badge>
                                    {document.versions_count > 1 && (
                                        <span className="text-muted-foreground ml-2 text-xs">{document.versions_count} versions</span>
                                    )}
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs md:table-cell">
                                    {format.date(document.updated_at)}
                                    {document.current?.uploaded_by && ` by ${document.current.uploaded_by}`}
                                </TableCell>
                                <TableCell className="text-right">
                                    {document.current && (
                                        <Button asChild variant="ghost" size="sm">
                                            <a
                                                href={route('projects.requirements.versions.download', [
                                                    project.id,
                                                    document.id,
                                                    document.current.id,
                                                ])}
                                            >
                                                <Download className="size-4" />
                                                <span className="sr-only">Download latest version of {document.title}</span>
                                            </a>
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={documents} />

            <FileUploadDialog
                open={adding}
                onOpenChange={setAdding}
                url={route('projects.requirements.store', project.id)}
                title="New requirement document"
                description="It gets the next REQ number and starts at version 1."
                withTitle
                submitLabel="Add document"
            />
        </ProjectWorkspaceLayout>
    );
}
