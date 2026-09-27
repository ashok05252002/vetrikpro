import ActiveToggle, { InactiveBadge } from '@/components/admin/active-toggle';
import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import type { Customer, Option, Paginated } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FilePlus2, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface Props {
    customers: Paginated<Customer>;
    states: Option[];
    filters: { search?: string; state?: string };
}

type Form = { name: string; contact_person: string; email: string; phone: string; address: string; state_code: string; gstin: string };

const blank: Form = { name: '', contact_person: '', email: '', phone: '', address: '', state_code: '', gstin: '' };

function CustomerDialog({
    customer,
    states,
    open,
    onOpenChange,
}: {
    customer: Customer | null;
    states: Option[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, put, processing, errors, clearErrors } = useForm<Form>(blank);

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        setData(
            customer
                ? {
                      name: customer.name,
                      contact_person: customer.contact_person ?? '',
                      email: customer.email ?? '',
                      phone: customer.phone ?? '',
                      address: customer.address ?? '',
                      state_code: customer.state_code ?? '',
                      gstin: customer.gstin ?? '',
                  }
                : blank,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, customer?.id]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (customer) {
            put(route('accounts.customers.update', customer.id), done);
        } else {
            post(route('accounts.customers.store'), done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{customer ? `Edit ${customer.name}` : 'New customer'}</DialogTitle>
                        <DialogDescription>
                            Invoices copy these details when they are made; editing here never changes an invoice already sent.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="c-name">Name</Label>
                            <Input
                                id="c-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoFocus
                                placeholder="Company or person billed"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c-email">Billing email</Label>
                            <Input
                                id="c-email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="accounts@customer.com"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c-phone">Phone</Label>
                            <Input id="c-phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            <InputError message={errors.phone} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="c-contact">Contact person</Label>
                            <Input id="c-contact" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} />
                            <InputError message={errors.contact_person} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="c-address">Billing address</Label>
                            <Textarea id="c-address" rows={3} value={data.address} onChange={(e) => setData('address', e.target.value)} />
                            <InputError message={errors.address} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c-gstin">
                                GSTIN <span className="text-muted-foreground">(if registered)</span>
                            </Label>
                            <Input
                                id="c-gstin"
                                value={data.gstin}
                                onChange={(e) => setData('gstin', e.target.value.toUpperCase())}
                                maxLength={15}
                                className="font-mono uppercase"
                                placeholder="33ABCDE1234F1Z5"
                            />
                            <InputError message={errors.gstin} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c-state">State</Label>
                            <Select value={data.state_code || undefined} onValueChange={(value) => setData('state_code', value)}>
                                <SelectTrigger id="c-state">
                                    <SelectValue placeholder={data.gstin.length >= 2 ? 'From the GSTIN' : 'Place of supply'} />
                                </SelectTrigger>
                                <SelectContent>
                                    {states.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.state_code} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{customer ? 'Save' : 'Add customer'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Customers({ customers, states, filters }: Props) {
    const { can } = usePermission();
    const [editing, setEditing] = useState<Customer | null>(null);
    const [open, setOpen] = useState(false);

    const openDialog = (customer: Customer | null) => {
        setEditing(customer);
        setOpen(true);
    };

    return (
        <AccountsLayout
            tab="customers"
            title="Customers"
            description="Who you invoice. Once invoiced, a customer can be marked inactive but not deleted."
            actions={
                can('customers.create') && (
                    <Button onClick={() => openDialog(null)}>
                        <Plus className="size-4" /> New customer
                    </Button>
                )
            }
        >
            <FilterBar
                url={route('accounts.customers.index')}
                filters={filters}
                searchPlaceholder="Name, email or GSTIN…"
                selects={[
                    {
                        name: 'state',
                        placeholder: 'Active and inactive',
                        options: [
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ],
                    },
                ]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Customer</TableHead>
                            <TableHead className="hidden md:table-cell">GSTIN</TableHead>
                            <TableHead className="hidden lg:table-cell">State</TableHead>
                            <TableHead className="hidden sm:table-cell">Invoices</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {customers.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                    {filters.search || filters.state ? 'Nobody matches.' : 'No customers yet.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {customers.data.map((customer) => (
                            <TableRow key={customer.id}>
                                <TableCell className={customer.is_active ? undefined : 'text-muted-foreground'}>
                                    <span className="font-medium">{customer.name}</span>
                                    <InactiveBadge active={customer.is_active} />
                                    <span className="text-muted-foreground block text-xs">
                                        {[customer.contact_person, customer.email].filter(Boolean).join(' · ') || '—'}
                                    </span>
                                </TableCell>
                                <TableCell className="hidden font-mono text-xs md:table-cell">
                                    {customer.gstin ?? <span className="text-muted-foreground">Unregistered</span>}
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-sm lg:table-cell">{customer.state_name ?? '—'}</TableCell>
                                <TableCell className="hidden tabular-nums sm:table-cell">
                                    {customer.invoices_count ? (
                                        <Link href={route('accounts.invoices.index', { customer: customer.id })} className="hover:underline">
                                            {customer.invoices_count}
                                        </Link>
                                    ) : (
                                        0
                                    )}
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {can('invoices.create') && customer.is_active && (
                                            <Button asChild variant="ghost" size="sm" title="New invoice">
                                                <Link href={route('accounts.invoices.create', { customer: customer.id })}>
                                                    <FilePlus2 className="size-4" />
                                                    <span className="sr-only">New invoice for {customer.name}</span>
                                                </Link>
                                            </Button>
                                        )}
                                        {can('customers.edit') && (
                                            <>
                                                <Button variant="ghost" size="sm" onClick={() => openDialog(customer)}>
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Button>
                                                <ActiveToggle url={route('accounts.customers.active', customer.id)} active={customer.is_active} />
                                            </>
                                        )}
                                        {can('customers.delete') && !customer.invoices_count && (
                                            <DeleteButton url={route('accounts.customers.destroy', customer.id)} label={customer.name} />
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={customers} />

            <CustomerDialog customer={editing} states={states} open={open} onOpenChange={setOpen} />
        </AccountsLayout>
    );
}
