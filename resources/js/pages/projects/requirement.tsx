import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import FileUploadDialog from '@/components/work/file-upload-dialog';
import { useFormat } from '@/hooks/use-format';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import { formatBytes } from '@/lib/files';
import type { ProjectWorkspaceHeader, RequirementDetail } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { Download, Pencil, Upload } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    project: ProjectWorkspaceHeader;
    document: RequirementDetail;
    can: { update: boolean; delete: boolean };
}

function EditDialog({
    projectId,
    document,
    open,
    onOpenChange,
}: {
    projectId: number;
    document: RequirementDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, put, processing, errors } = useForm({ title: document.title, description: document.description ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('projects.requirements.update', [projectId, document.id]), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Edit {document.reference}</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-title">Title</Label>
                        <Input id="edit-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-description">Description</Label>
                        <Textarea id="edit-description" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
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

export default function Requirement({ project, document, can }: Props) {
    const format = useFormat();
    const [uploading, setUploading] = useState(false);
    const [editing, setEditing] = useState(false);

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="requirements"
            crumbs={[{ title: document.reference, href: route('projects.requirements.show', [project.id, document.id]) }]}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <Link href={route('projects.requirements.index', project.id)} className="text-muted-foreground text-xs hover:underline">
                        ← All requirement documents
                    </Link>
                    <h2 className="text-lg font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-sm font-normal">{document.reference}</span>
                        {document.title}
                    </h2>
                    {document.description && <p className="text-muted-foreground max-w-3xl text-sm whitespace-pre-wrap">{document.description}</p>}
                    <p className="text-muted-foreground text-xs">
                        Added {format.date(document.created_at)}
                        {document.creator && ` by ${document.creator.name}`}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {can.update && (
                        <>
                            <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                                <Pencil className="size-4" /> Edit
                            </Button>
                            <Button size="sm" onClick={() => setUploading(true)}>
                                <Upload className="size-4" /> Upload new version
                            </Button>
                        </>
                    )}
                    {can.delete && (
                        <DeleteButton
                            url={route('projects.requirements.destroy', [project.id, document.id])}
                            label={`${document.reference} and all ${document.versions.length} versions`}
                            description="Every version of the file is removed permanently."
                        />
                    )}
                </div>
            </div>

            <section className="space-y-3">
                <h3 className="text-sm font-medium">Version history</h3>
                <ol className="divide-y rounded-xl border">
                    {document.versions.map((version, index) => (
                        <li key={version.id} className="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={index === 0 ? 'default' : 'outline'}>v{version.version}</Badge>
                                    {index === 0 && <span className="text-muted-foreground text-xs">Current</span>}
                                    <span className="truncate text-sm">{version.original_name}</span>
                                    <span className="text-muted-foreground text-xs">{formatBytes(version.size)}</span>
                                </div>
                                {version.change_note && <p className="text-sm whitespace-pre-wrap">{version.change_note}</p>}
                                <p className="text-muted-foreground text-xs">
                                    {format.date(version.uploaded_at)}
                                    {version.uploaded_by && ` by ${version.uploaded_by}`}
                                </p>
                            </div>
                            <Button asChild variant="outline" size="sm" className="shrink-0 self-start">
                                <a href={route('projects.requirements.versions.download', [project.id, document.id, version.id])}>
                                    <Download className="size-4" /> Download
                                </a>
                            </Button>
                        </li>
                    ))}
                </ol>
            </section>

            {can.update && (
                <>
                    <FileUploadDialog
                        open={uploading}
                        onOpenChange={setUploading}
                        url={route('projects.requirements.versions.store', [project.id, document.id])}
                        title={`New version of ${document.reference}`}
                        description={`This becomes v${(document.current?.version ?? 0) + 1}. Earlier versions stay available.`}
                        noteRequired
                        submitLabel="Upload version"
                    />
                    <EditDialog projectId={project.id} document={document} open={editing} onOpenChange={setEditing} />
                </>
            )}
        </ProjectWorkspaceLayout>
    );
}
