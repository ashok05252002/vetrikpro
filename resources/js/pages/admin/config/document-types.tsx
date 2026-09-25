import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ConfigLayout from '@/layouts/config/config-layout';
import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FilePlus2, Lock, Pencil } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface DocumentTypeRow {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_required: boolean;
    is_active: boolean;
    is_system: boolean;
    sort_order: number;
    documents_count: number;
}

interface Props {
    types: DocumentTypeRow[];
    can: { create: boolean; edit: boolean; delete: boolean };
}

type Form = { name: string; description: string; is_required: boolean; is_active: boolean };

function TypeDialog({ type, open, onOpenChange }: { type: DocumentTypeRow | null; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<Form>({
        name: '',
        description: '',
        is_required: false,
        is_active: true,
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        setData({
            name: type?.name ?? '',
            description: type?.description ?? '',
            is_required: type?.is_required ?? false,
            is_active: type?.is_active ?? true,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, type?.id]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => (reset(), onOpenChange(false)) };
        if (type) {
            put(route('admin.config.document-types.update', type.id), done);
        } else {
            post(route('admin.config.document-types.store'), done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{type ? `Edit ${type.name}` : 'New document type'}</DialogTitle>
                        <DialogDescription>Each type is its own upload slot on the employee’s onboarding checklist.</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="dt-name">Name</Label>
                        <Input
                            id="dt-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                            placeholder="Voter ID"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="dt-description">
                            Instructions <span className="text-muted-foreground">(optional)</span>
                        </Label>
                        <Input
                            id="dt-description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Front and back in one PDF."
                        />
                        <p className="text-muted-foreground text-xs">Shown to the employee beside the upload.</p>
                        <InputError message={errors.description} />
                    </div>

                    <label className="flex items-start gap-3">
                        <Checkbox
                            className="mt-0.5"
                            checked={data.is_required}
                            disabled={type?.is_system}
                            onCheckedChange={(c) => setData('is_required', c === true)}
                        />
                        <span className="text-sm">
                            Required for onboarding
                            <span className="text-muted-foreground block text-xs">The employee can’t submit their profile without it.</span>
                        </span>
                    </label>

                    <label className="flex items-start gap-3">
                        <Checkbox
                            className="mt-0.5"
                            checked={data.is_active}
                            disabled={type?.is_system}
                            onCheckedChange={(c) => setData('is_active', c === true)}
                        />
                        <span className="text-sm">
                            Active
                            <span className="text-muted-foreground block text-xs">
                                Switched off, it is no longer offered. Documents already uploaded keep it.
                            </span>
                        </span>
                    </label>

                    {type?.is_system && (
                        <p className="text-muted-foreground text-xs">
                            This type is part of onboarding, so it is always required and always on. You can still rename it.
                        </p>
                    )}

                    <DialogFooter>
                        <Button disabled={processing}>{type ? 'Save' : 'Add type'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function DocumentTypes({ types, can }: Props) {
    const [editing, setEditing] = useState<DocumentTypeRow | null>(null);
    const [open, setOpen] = useState(false);

    const move = (index: number, by: -1 | 1) => {
        const ids = types.map((t) => t.id);
        [ids[index], ids[index + by]] = [ids[index + by], ids[index]];
        router.post(route('admin.config.document-types.reorder'), { ids }, { preserveScroll: true });
    };

    const toggle = (type: DocumentTypeRow, field: 'is_required' | 'is_active', value: boolean) =>
        router.put(
            route('admin.config.document-types.update', type.id),
            { name: type.name, description: type.description ?? '', is_required: type.is_required, is_active: type.is_active, [field]: value },
            { preserveScroll: true },
        );

    const required = types.filter((t) => t.is_active && t.is_required).length;

    return (
        <ConfigLayout tab="document-types">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-muted-foreground max-w-2xl text-sm">
                    What every new employee is asked to upload when they complete their profile, in this order.{' '}
                    <span className="text-foreground font-medium">{required} required</span> right now.
                </p>
                {can.create && (
                    <Button
                        size="sm"
                        onClick={() => {
                            setEditing(null);
                            setOpen(true);
                        }}
                    >
                        <FilePlus2 className="size-4" /> New document type
                    </Button>
                )}
            </div>

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {can.edit && <TableHead className="w-20">Order</TableHead>}
                            <TableHead>Document</TableHead>
                            <TableHead className="w-28 text-center">Required</TableHead>
                            <TableHead className="w-24 text-center">Active</TableHead>
                            <TableHead className="hidden w-28 md:table-cell">Uploaded</TableHead>
                            <TableHead className="w-24 text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {types.map((type, index) => (
                            <TableRow key={type.id} className={type.is_active ? undefined : 'opacity-60'}>
                                {can.edit && (
                                    <TableCell>
                                        <div className="flex gap-0.5">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="size-7 p-0"
                                                disabled={index === 0}
                                                onClick={() => move(index, -1)}
                                                aria-label={`Move ${type.name} up`}
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="size-7 p-0"
                                                disabled={index === types.length - 1}
                                                onClick={() => move(index, 1)}
                                                aria-label={`Move ${type.name} down`}
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                )}
                                <TableCell>
                                    <div className="flex flex-wrap items-center gap-2 font-medium">
                                        {type.name}
                                        {type.is_system && (
                                            <Badge variant="outline" className="gap-1">
                                                <Lock className="size-3" /> Onboarding
                                            </Badge>
                                        )}
                                    </div>
                                    {type.description && <p className="text-muted-foreground text-xs">{type.description}</p>}
                                </TableCell>
                                <TableCell className="text-center">
                                    <Checkbox
                                        aria-label={`${type.name} is required`}
                                        checked={type.is_required}
                                        disabled={!can.edit || type.is_system}
                                        onCheckedChange={(c) => toggle(type, 'is_required', c === true)}
                                    />
                                </TableCell>
                                <TableCell className="text-center">
                                    <Checkbox
                                        aria-label={`${type.name} is active`}
                                        checked={type.is_active}
                                        disabled={!can.edit || type.is_system}
                                        onCheckedChange={(c) => toggle(type, 'is_active', c === true)}
                                    />
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-xs tabular-nums md:table-cell">
                                    {type.documents_count}
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {can.edit && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setEditing(type);
                                                    setOpen(true);
                                                }}
                                            >
                                                <Pencil className="size-4" />
                                                <span className="sr-only">Edit {type.name}</span>
                                            </Button>
                                        )}
                                        {can.delete && !type.is_system && (
                                            <DeleteButton
                                                url={route('admin.config.document-types.destroy', type.id)}
                                                label={type.name}
                                                description={
                                                    type.documents_count > 0
                                                        ? 'Documents use this type, so it will be refused — switch it off instead.'
                                                        : undefined
                                                }
                                            />
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <TypeDialog type={editing} open={open} onOpenChange={setOpen} />
        </ConfigLayout>
    );
}
