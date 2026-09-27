import ActiveToggle, { InactiveBadge } from '@/components/admin/active-toggle';
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
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import type { Option, Paginated, Product, ProductType } from '@/types';
import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface Props {
    products: Paginated<Product>;
    types: Option[];
    gstRates: number[];
    filters: { search?: string; type?: string; state?: string };
}

type Form = { name: string; type: ProductType; code: string; hsn_sac: string; unit: string; price: string; gst_rate: string; description: string };

const blank: Form = { name: '', type: 'service', code: '', hsn_sac: '', unit: 'nos', price: '', gst_rate: '18', description: '' };

/** Common units; anything else can be typed. */
const UNITS = ['nos', 'hrs', 'days', 'months', 'kg', 'pcs', 'set'];

function ProductDialog({
    product,
    types,
    gstRates,
    open,
    onOpenChange,
}: {
    product: Product | null;
    types: Option[];
    gstRates: number[];
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
            product
                ? {
                      name: product.name,
                      type: product.type,
                      code: product.code ?? '',
                      hsn_sac: product.hsn_sac ?? '',
                      unit: product.unit,
                      price: String(Number(product.price)),
                      gst_rate: String(Number(product.gst_rate)),
                      description: product.description ?? '',
                  }
                : blank,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, product?.id]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (product) {
            put(route('accounts.products.update', product.id), done);
        } else {
            post(route('accounts.products.store'), done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{product ? `Edit ${product.name}` : 'New product or service'}</DialogTitle>
                        <DialogDescription>The price and GST rate fill in an invoice line; each line can still be changed.</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="p-name">Name</Label>
                            <Input id="p-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-type">Type</Label>
                            <Select value={data.type} onValueChange={(value) => setData('type', value as ProductType)}>
                                <SelectTrigger id="p-type">
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
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-code">
                                Code <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Input id="p-code" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="SKU or item code" />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-price">Price</Label>
                            <Input
                                id="p-price"
                                type="number"
                                min={0}
                                step="0.01"
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                required
                            />
                            <InputError message={errors.price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-unit">Unit</Label>
                            <Input id="p-unit" list="p-units" value={data.unit} onChange={(e) => setData('unit', e.target.value)} required />
                            <datalist id="p-units">
                                {UNITS.map((u) => (
                                    <option key={u} value={u} />
                                ))}
                            </datalist>
                            <InputError message={errors.unit} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-gst">GST rate</Label>
                            <Select value={data.gst_rate} onValueChange={(value) => setData('gst_rate', value)}>
                                <SelectTrigger id="p-gst">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {gstRates.map((r) => (
                                        <SelectItem key={r} value={String(r)}>
                                            {r}%
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gst_rate} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-hsn">{data.type === 'service' ? 'SAC code' : 'HSN code'}</Label>
                            <Input
                                id="p-hsn"
                                value={data.hsn_sac}
                                onChange={(e) => setData('hsn_sac', e.target.value)}
                                className="font-mono"
                                placeholder={data.type === 'service' ? '998314' : '8471'}
                            />
                            <InputError message={errors.hsn_sac} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="p-desc">
                                Description <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Textarea id="p-desc" rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            <InputError message={errors.description} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{product ? 'Save' : 'Add'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Products({ products, types, gstRates, filters }: Props) {
    const { can } = usePermission();
    const format = useFormat();
    const [editing, setEditing] = useState<Product | null>(null);
    const [open, setOpen] = useState(false);

    const openDialog = (product: Product | null) => {
        setEditing(product);
        setOpen(true);
    };

    return (
        <AccountsLayout
            tab="products"
            title="Products & services"
            description="What you sell, with the price and GST an invoice line starts from. Once invoiced, one can be marked inactive but not deleted."
            actions={
                can('products.create') && (
                    <Button onClick={() => openDialog(null)}>
                        <Plus className="size-4" /> New item
                    </Button>
                )
            }
        >
            <FilterBar
                url={route('accounts.products.index')}
                filters={filters}
                searchPlaceholder="Name, code or HSN/SAC…"
                selects={[
                    { name: 'type', placeholder: 'Products and services', options: types },
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
                            <TableHead>Item</TableHead>
                            <TableHead className="hidden sm:table-cell">Type</TableHead>
                            <TableHead className="hidden md:table-cell">HSN/SAC</TableHead>
                            <TableHead className="text-right">Price</TableHead>
                            <TableHead className="hidden text-right sm:table-cell">GST</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {products.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                    {filters.search || filters.type || filters.state ? 'Nothing matches.' : 'Nothing added yet.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {products.data.map((product) => (
                            <TableRow key={product.id}>
                                <TableCell className={product.is_active ? undefined : 'text-muted-foreground'}>
                                    <span className="font-medium">{product.name}</span>
                                    <InactiveBadge active={product.is_active} />
                                    {product.code && <span className="text-muted-foreground block font-mono text-xs">{product.code}</span>}
                                </TableCell>
                                <TableCell className="hidden sm:table-cell">
                                    <Badge variant="outline">{product.type === 'service' ? 'Service' : 'Product'}</Badge>
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden font-mono text-xs md:table-cell">
                                    {product.hsn_sac ?? '—'}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {format.money(product.price)}
                                    <span className="text-muted-foreground block text-xs">per {product.unit}</span>
                                </TableCell>
                                <TableCell className="hidden text-right tabular-nums sm:table-cell">{Number(product.gst_rate)}%</TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {can('products.edit') && (
                                            <>
                                                <Button variant="ghost" size="sm" onClick={() => openDialog(product)}>
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Button>
                                                <ActiveToggle url={route('accounts.products.active', product.id)} active={product.is_active} />
                                            </>
                                        )}
                                        {can('products.delete') && !product.invoice_items_count && (
                                            <DeleteButton url={route('accounts.products.destroy', product.id)} label={product.name} />
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={products} />

            <ProductDialog product={editing} types={types} gstRates={gstRates} open={open} onOpenChange={setOpen} />
        </AccountsLayout>
    );
}
