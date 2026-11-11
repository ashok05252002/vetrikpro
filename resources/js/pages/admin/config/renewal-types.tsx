import { InactiveBadge } from '@/components/admin/active-toggle';
import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ConfigLayout from '@/layouts/config/config-layout';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface TypeRow {
    id: number;
    name: string;
    cycle_months: number;
    remind_days: number;
    is_active: boolean;
    renewals_count: number;
}

interface Props {
    types: TypeRow[];
    cycles: Option[];
    can: { create: boolean; edit: boolean; delete: boolean };
}

type Form = { name: string; cycle_months: string; remind_days: string; is_active: boolean };

const blank: Form = { name: '', cycle_months: '12', remind_days: '15', is_active: true };

function TypeDialog({
    type,
    cycles,
    open,
    onOpenChange,
}: {
    type: TypeRow | null;
    cycles: Option[];
    open: boolean;
    onOpenChange: (o: boolean) => void;
}) {
    const { data, setData, post, put, processing, errors, clearErrors } = useForm<Form>(blank);

    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData(
            type
                ? { name: type.name, cycle_months: String(type.cycle_months), remind_days: String(type.remind_days), is_active: type.is_active }
                : blank,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, type?.id]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (type) {
            put(route('admin.config.renewal-types.update', type.id), done);
        } else {
            post(route('admin.config.renewal-types.store'), done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{type ? `Edit ${type.name}` : 'New renewal type'}</DialogTitle>
                        <DialogDescription>
                            The cycle and notice are filled in when someone adds a renewal of this type; they can still change them there.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="t-name">Name</Label>
                        <Input
                            id="t-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                            placeholder="e.g. Website maintenance"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="t-cycle">Usually renews</Label>
                            <Select value={data.cycle_months} onValueChange={(v) => setData('cycle_months', v)}>
                                <SelectTrigger id="t-cycle">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {cycles.map((c) => (
                                        <SelectItem key={c.value} value={c.value}>
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.cycle_months} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="t-remind">Remind before</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="t-remind"
                                    type="number"
                                    min={0}
                                    max={365}
                                    className="w-24"
                                    value={data.remind_days}
                                    onChange={(e) => setData('remind_days', e.target.value)}
                                    required
                                />
                                <span className="text-muted-foreground text-sm">days</span>
                            </div>
                            <InputError message={errors.remind_days} />
                        </div>
                    </div>
                    <div className="flex items-start justify-between gap-4">
                        <div className="space-y-0.5">
                            <Label htmlFor="t-active">Active</Label>
                            <p className="text-muted-foreground text-xs">
                                Switched off, it is no longer offered for new renewals. Existing ones keep it.
                            </p>
                        </div>
                        <Switch id="t-active" checked={data.is_active} onCheckedChange={(on) => setData('is_active', on)} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{type ? 'Save' : 'Add type'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RenewalTypes({ types, cycles, can }: Props) {
    const [editing, setEditing] = useState<TypeRow | null>(null);
    const [open, setOpen] = useState(false);
    const cycleLabel = (m: number) => cycles.find((c) => c.value === String(m))?.label ?? `Every ${m} months`;

    const openDialog = (type: TypeRow | null) => {
        setEditing(type);
        setOpen(true);
    };

    return (
        <ConfigLayout
            tab="renewal-types"
            actions={
                can.create && (
                    <Button onClick={() => openDialog(null)}>
                        <Plus className="size-4" /> New type
                    </Button>
                )
            }
        >
            <div className="max-w-4xl rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Type</TableHead>
                            <TableHead>Usually renews</TableHead>
                            <TableHead className="hidden sm:table-cell">Remind before</TableHead>
                            <TableHead className="hidden sm:table-cell">Renewals</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {types.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                    No renewal types yet.
                                </TableCell>
                            </TableRow>
                        )}
                        {types.map((t) => (
                            <TableRow key={t.id}>
                                <TableCell className={t.is_active ? 'font-medium' : 'text-muted-foreground font-medium'}>
                                    {t.name}
                                    <InactiveBadge active={t.is_active} />
                                </TableCell>
                                <TableCell className="text-sm">{cycleLabel(t.cycle_months)}</TableCell>
                                <TableCell className="hidden text-sm tabular-nums sm:table-cell">{t.remind_days} days</TableCell>
                                <TableCell className="hidden text-sm tabular-nums sm:table-cell">{t.renewals_count}</TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {can.edit && (
                                            <Button variant="ghost" size="sm" onClick={() => openDialog(t)}>
                                                <Pencil className="size-4" />
                                                <span className="sr-only">Edit {t.name}</span>
                                            </Button>
                                        )}
                                        {can.delete && t.renewals_count === 0 && (
                                            <DeleteButton url={route('admin.config.renewal-types.destroy', t.id)} label={t.name} />
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <TypeDialog type={editing} cycles={cycles} open={open} onOpenChange={setOpen} />
        </ConfigLayout>
    );
}
