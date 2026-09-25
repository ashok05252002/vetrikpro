import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import { formatBytes } from '@/lib/files';
import type { EmployeeDocument, EmployeeProfileHeader, Option, Paginated } from '@/types';
import { useForm } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    employee: EmployeeProfileHeader;
    documents: Paginated<EmployeeDocument>;
    types: Option[];
    filters: { type?: string; search?: string };
}

type UploadForm = { document_type_id: string; title: string; file: File | null; expires_at: string };

function UploadDialog({
    employeeId,
    types,
    open,
    onOpenChange,
}: {
    employeeId: number;
    types: Option[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, processing, errors, reset, progress } = useForm<UploadForm>({
        document_type_id: types[0]?.value ?? '',
        title: '',
        file: null,
        expires_at: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.employees.documents.store', employeeId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Upload a document</DialogTitle>
                        <DialogDescription>
                            PDF, image, Word, Excel or text, up to 10 MB. Only people with document access can open it.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="doc-type">Type</Label>
                        <Select value={data.document_type_id} onValueChange={(value) => setData('document_type_id', value)}>
                            <SelectTrigger id="doc-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.document_type_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="doc-title">Title</Label>
                        <Input
                            id="doc-title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                            placeholder="Aadhaar card"
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="doc-file">File</Label>
                        <Input
                            id="doc-file"
                            type="file"
                            required
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt"
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                setData((current) => ({
                                    ...current,
                                    file,
                                    // Suggest a title from the file name if none was typed.
                                    title: current.title || (file ? file.name.replace(/\.[^.]+$/, '') : ''),
                                }));
                            }}
                        />
                        <InputError message={errors.file} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="doc-expiry">
                            Expires on <span className="text-muted-foreground">(optional)</span>
                        </Label>
                        <Input id="doc-expiry" type="date" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} />
                        <InputError message={errors.expires_at} />
                    </div>

                    {progress && (
                        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                            <div className="bg-primary h-full transition-[width]" style={{ width: `${progress.percentage ?? 0}%` }} />
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Upload
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeDocuments({ employee, documents, types, filters }: Props) {
    const format = useFormat();
    const [uploading, setUploading] = useState(false);

    return (
        <EmployeeProfileLayout
            employee={employee}
            tab="documents"
            actions={
                employee.viewer.can_upload && (
                    <Button size="sm" onClick={() => setUploading(true)}>
                        <Upload className="size-4" /> Upload
                    </Button>
                )
            }
        >
            <FilterBar
                url={route('admin.employees.documents.index', employee.id)}
                filters={filters}
                searchPlaceholder="Search title or file name…"
                selects={[{ name: 'type', placeholder: 'All types', options: types }]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Document</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead className="hidden md:table-cell">Expires</TableHead>
                            <TableHead className="hidden lg:table-cell">Uploaded</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {documents.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                    {Object.values(filters).some(Boolean) ? 'No documents match.' : 'No documents yet.'}
                                </TableCell>
                            </TableRow>
                        )}

                        {documents.data.map((document) => (
                            <TableRow key={document.id}>
                                <TableCell>
                                    <div className="flex items-start gap-3">
                                        <FileText className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                        <div className="min-w-0">
                                            <p className="font-medium">{document.title}</p>
                                            <p className="text-muted-foreground truncate text-xs">
                                                {document.original_name} · {formatBytes(document.size)}
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">{document.type_label}</Badge>
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    {document.expires_at ? (
                                        <span className={document.is_expired ? 'text-destructive font-medium' : 'text-muted-foreground'}>
                                            {document.is_expired ? 'Expired ' : ''}
                                            {format.date(document.expires_at)}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs lg:table-cell">
                                    {format.date(document.uploaded_at)}
                                    {document.uploaded_by && ` by ${document.uploaded_by}`}
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        <Button asChild variant="ghost" size="sm">
                                            {/* A plain link, not an Inertia visit: the response is a file. */}
                                            <a href={route('admin.employees.documents.download', [employee.id, document.id])}>
                                                <Download className="size-4" />
                                                <span className="sr-only">Download</span>
                                            </a>
                                        </Button>
                                        {employee.viewer.can_delete_documents && (
                                            <DeleteButton
                                                url={route('admin.employees.documents.destroy', [employee.id, document.id])}
                                                label={document.title}
                                                description="The file is removed permanently."
                                            />
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={documents} />

            <UploadDialog employeeId={employee.id} types={types} open={uploading} onOpenChange={setUploading} />
        </EmployeeProfileLayout>
    );
}
