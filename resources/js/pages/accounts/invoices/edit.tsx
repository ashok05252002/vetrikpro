import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import CardHeading from '@/components/ui/card-heading';
import DatePicker from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import { cn } from '@/lib/utils';
import type { Customer, InvoiceLine, Option, Product } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, CalendarDays, Contact, ListOrdered, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo } from 'react';

type CustomerOption = Pick<Customer, 'id' | 'name' | 'email' | 'address' | 'state_code' | 'gstin'>;
type ProductOption = Pick<Product, 'id' | 'name' | 'type' | 'hsn_sac' | 'unit' | 'price' | 'gst_rate' | 'description'>;

interface Props {
    invoice: { id: number; reference: string } | null;
    initial: {
        customer_id: number | null;
        issue_date: string;
        due_date: string | null;
        terms?: string | null;
        bill_email?: string | null;
        bill_address?: string | null;
        place_of_supply?: string | null;
        charge_tax?: boolean;
        notes?: string | null;
        items?: InvoiceLine[];
    };
    customers: CustomerOption[];
    products: ProductOption[];
    states: Option[];
    gstRates: number[];
    companyState: string | null;
}

type Form = {
    customer_id: string;
    bill_email: string;
    bill_address: string;
    place_of_supply: string;
    charge_tax: boolean;
    issue_date: string;
    due_date: string;
    notes: string;
    terms: string;
    items: InvoiceLine[];
};

const CUSTOM = '__custom__';

/** Line-grid columns on wide screens, with and without the GST column. */
const COLS_TAX = 'grid-cols-[minmax(0,2.4fr)_70px_70px_minmax(0,1fr)_minmax(0,1fr)_80px_minmax(0,1fr)_36px]';
const COLS_NO_TAX = 'grid-cols-[minmax(0,2.4fr)_70px_70px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_36px]';
const COLS_TAX_LG = 'lg:grid-cols-[minmax(0,2.4fr)_70px_70px_minmax(0,1fr)_minmax(0,1fr)_80px_minmax(0,1fr)_36px]';
const COLS_NO_TAX_LG = 'lg:grid-cols-[minmax(0,2.4fr)_70px_70px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_36px]';
const round2 = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100;
const num = (v: string | number) => (v === '' ? 0 : Number(v)) || 0;

const blankLine = (): InvoiceLine => ({
    product_id: null,
    description: '',
    hsn_sac: '',
    quantity: 1,
    unit: 'nos',
    unit_price: '',
    discounted_price: '',
    gst_rate: 18,
});

/** The same arithmetic as App\Services\Accounts\InvoiceCalculator, so totals don't jump on save. */
function compute(items: InvoiceLine[], interstate: boolean, chargeTax: boolean) {
    let subtotal = 0,
        discount = 0,
        taxable = 0,
        cgst = 0,
        sgst = 0,
        igst = 0;

    const lines = items.map((line) => {
        const gross = round2(num(line.quantity) * num(line.unit_price));
        const lineTaxable = round2(num(line.quantity) * num(line.discounted_price));
        const tax = chargeTax ? round2((lineTaxable * num(line.gst_rate)) / 100) : 0;

        if (interstate) {
            igst += tax;
        } else {
            const half = round2(tax / 2);
            cgst += half;
            sgst += round2(tax - half);
        }
        subtotal += gross;
        discount += round2(gross - lineTaxable);
        taxable += lineTaxable;

        return { taxable: lineTaxable, tax, amount: round2(lineTaxable + tax) };
    });

    return {
        lines,
        subtotal: round2(subtotal),
        discount: round2(discount),
        taxable: round2(taxable),
        cgst: round2(cgst),
        sgst: round2(sgst),
        igst: round2(igst),
        total: round2(taxable + cgst + sgst + igst),
    };
}

export default function InvoiceEditor({ invoice, initial, customers, products, states, gstRates, companyState }: Props) {
    const format = useFormat();
    const { can } = usePermission();

    const { data, setData, post, put, processing, errors } = useForm<Form>({
        customer_id: initial.customer_id ? String(initial.customer_id) : '',
        bill_email: initial.bill_email ?? '',
        bill_address: initial.bill_address ?? '',
        place_of_supply: initial.place_of_supply ?? '',
        charge_tax: initial.charge_tax ?? true,
        issue_date: initial.issue_date,
        due_date: initial.due_date ?? '',
        notes: initial.notes ?? '',
        terms: initial.terms ?? '',
        items: initial.items?.length ? initial.items.map((i) => ({ ...i, hsn_sac: i.hsn_sac ?? '' })) : [blankLine()],
    });

    const errorFor = (key: string) => (errors as Record<string, string | undefined>)[key];
    const customer = customers.find((c) => String(c.id) === data.customer_id);
    const placeOfSupply = data.place_of_supply || customer?.state_code || '';
    const interstate = Boolean(companyState && placeOfSupply && companyState !== placeOfSupply);
    const tax = data.charge_tax;
    const totals = useMemo(() => compute(data.items, interstate, tax), [data.items, interstate, tax]);

    const pickCustomer = (value: string) => {
        const next = customers.find((c) => String(c.id) === value);
        // The billing details follow the customer; they can still be edited for this invoice.
        setData((form) => ({
            ...form,
            customer_id: value,
            bill_email: next?.email ?? '',
            bill_address: next?.address ?? '',
            place_of_supply: next?.state_code ?? '',
        }));
    };

    const setLine = (index: number, patch: Partial<InvoiceLine>) =>
        setData(
            'items',
            data.items.map((line, i) => (i === index ? { ...line, ...patch } : line)),
        );

    const pickProduct = (index: number, value: string) => {
        if (value === CUSTOM) {
            setLine(index, { product_id: null });
            return;
        }
        const product = products.find((p) => String(p.id) === value);
        if (!product) {
            return;
        }
        const price = Number(product.price);
        setLine(index, {
            product_id: product.id,
            description: product.name,
            hsn_sac: product.hsn_sac ?? '',
            unit: product.unit,
            unit_price: price,
            discounted_price: price,
            gst_rate: Number(product.gst_rate),
        });
    };

    // Typing a new cost moves the discounted price with it while there is no discount.
    const setCost = (index: number, value: string) => {
        const line = data.items[index];
        const undiscounted = line.discounted_price === '' || num(line.discounted_price) === num(line.unit_price);
        setLine(index, undiscounted ? { unit_price: value, discounted_price: value } : { unit_price: value });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (invoice) {
            put(route('accounts.invoices.update', invoice.id), { preserveScroll: true });
        } else {
            post(route('accounts.invoices.store'), { preserveScroll: true });
        }
    };

    return (
        <AccountsLayout
            tab="invoices"
            title={invoice ? 'Edit draft invoice' : 'New invoice'}
            description="Saved as a draft. It gets its number when you send it."
            back={invoice ? route('accounts.invoices.show', invoice.id) : route('accounts.invoices.index')}
            trail={[{ title: invoice ? 'Edit draft' : 'New invoice', href: '#' }]}
        >
            <form onSubmit={submit} className="flex max-w-6xl flex-col gap-4">
                {!companyState && tax && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <p>
                            The company’s GST state isn’t set, so every invoice is treated as within the state (CGST + SGST).{' '}
                            {can('settings.edit') && (
                                <Link href={route('admin.settings.edit')} className="font-medium underline">
                                    Set it in Organisation settings
                                </Link>
                            )}
                        </p>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardHeading icon={Contact} tone="green">
                                Bill to
                            </CardHeading>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="customer">Customer</Label>
                                <Select value={data.customer_id || undefined} onValueChange={pickCustomer}>
                                    <SelectTrigger id="customer">
                                        <SelectValue placeholder="Choose a customer" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {customers.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                                {c.gstin && <span className="text-muted-foreground ml-2 font-mono text-xs">{c.gstin}</span>}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {customers.length === 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        No customers yet.{' '}
                                        {can('customers.create') && (
                                            <Link href={route('accounts.customers.index')} className="underline">
                                                Add one first
                                            </Link>
                                        )}
                                    </p>
                                )}
                                <InputError message={errors.customer_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bill_email">Billing email</Label>
                                <Input
                                    id="bill_email"
                                    type="email"
                                    value={data.bill_email}
                                    onChange={(e) => setData('bill_email', e.target.value)}
                                    placeholder="Where the invoice is sent"
                                />
                                <InputError message={errors.bill_email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="place_of_supply">Place of supply</Label>
                                <Select value={placeOfSupply || undefined} onValueChange={(value) => setData('place_of_supply', value)}>
                                    <SelectTrigger id="place_of_supply">
                                        <SelectValue placeholder="State" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {states.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    {!tax ? 'No GST on this invoice.' : interstate ? 'Another state: IGST.' : 'Within the state: CGST + SGST.'}
                                </p>
                                <InputError message={errors.place_of_supply} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="bill_address">Billing address</Label>
                                <Textarea
                                    id="bill_address"
                                    rows={2}
                                    value={data.bill_address}
                                    onChange={(e) => setData('bill_address', e.target.value)}
                                />
                                <InputError message={errors.bill_address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardHeading icon={CalendarDays} tone="sky">
                                Dates
                            </CardHeading>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="issue_date">Invoice date</Label>
                                <DatePicker id="issue_date" value={data.issue_date ?? ''} onChange={(v) => setData('issue_date', v)} required />
                                <InputError message={errors.issue_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="due_date">Due date</Label>
                                <DatePicker id="due_date" value={data.due_date ?? ''} onChange={(v) => setData('due_date', v)} />
                                <InputError message={errors.due_date} />
                            </div>
                            <div className="flex items-start justify-between gap-3 border-t pt-4">
                                <div className="space-y-1">
                                    <Label htmlFor="charge_tax">Charge GST</Label>
                                    <p className="text-muted-foreground text-xs">
                                        {tax ? 'Tax is added to each line.' : 'No tax on this bill — no GST columns or tax lines are printed.'}
                                    </p>
                                </div>
                                <Switch id="charge_tax" checked={tax} onCheckedChange={(on) => setData('charge_tax', on)} />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardHeading icon={ListOrdered} tone="green">
                            Items
                        </CardHeading>
                        <Button type="button" variant="outline" size="sm" onClick={() => setData('items', [...data.items, blankLine()])}>
                            <Plus className="size-4" /> Add line
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <InputError message={errors.items} />

                        {/* Column labels, wide screens only; narrow screens label each field. */}
                        <div className={cn('text-muted-foreground hidden gap-2 px-1 text-xs font-medium lg:grid', tax ? COLS_TAX : COLS_NO_TAX)}>
                            <span>Item</span>
                            <span>Qty</span>
                            <span>Unit</span>
                            <span>Cost</span>
                            <span>Discounted price</span>
                            {tax && <span>GST</span>}
                            <span className="text-right">Amount</span>
                            <span />
                        </div>

                        {data.items.map((line, index) => {
                            const e = (field: string) => errorFor(`items.${index}.${field}`);
                            const computed = totals.lines[index];
                            const discounted = num(line.discounted_price) < num(line.unit_price);

                            return (
                                <div
                                    key={index}
                                    className={cn(
                                        'grid grid-cols-2 gap-2 rounded-lg border p-3 sm:grid-cols-4 lg:items-start lg:border-0 lg:p-1',
                                        tax ? COLS_TAX_LG : COLS_NO_TAX_LG,
                                    )}
                                >
                                    <div className="col-span-2 space-y-2 sm:col-span-4 lg:col-span-1">
                                        <Label className="lg:sr-only">Item</Label>
                                        <Select
                                            value={line.product_id ? String(line.product_id) : CUSTOM}
                                            onValueChange={(value) => pickProduct(index, value)}
                                        >
                                            <SelectTrigger className="h-9">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={CUSTOM}>Custom line</SelectItem>
                                                {products.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>
                                                        {p.name} <span className="text-muted-foreground ml-1 text-xs">{format.money(p.price)}</span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            className="h-9"
                                            value={line.description}
                                            onChange={(ev) => setLine(index, { description: ev.target.value })}
                                            placeholder="Description"
                                            required
                                        />
                                        <Input
                                            className="h-8 w-40 font-mono text-xs"
                                            value={line.hsn_sac ?? ''}
                                            onChange={(ev) => setLine(index, { hsn_sac: ev.target.value })}
                                            placeholder="HSN/SAC"
                                            aria-label="HSN or SAC code"
                                        />
                                        <InputError message={e('product_id') ?? e('description')} />
                                    </div>

                                    <div className="space-y-1">
                                        <Label className="text-xs lg:sr-only">Qty</Label>
                                        <Input
                                            className="h-9"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={line.quantity}
                                            onChange={(ev) => setLine(index, { quantity: ev.target.value })}
                                            required
                                        />
                                        <InputError message={e('quantity')} />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs lg:sr-only">Unit</Label>
                                        <Input
                                            className="h-9"
                                            value={line.unit}
                                            onChange={(ev) => setLine(index, { unit: ev.target.value })}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs lg:sr-only">Cost</Label>
                                        <Input
                                            className="h-9"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={line.unit_price}
                                            onChange={(ev) => setCost(index, ev.target.value)}
                                            required
                                        />
                                        <InputError message={e('unit_price')} />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs lg:sr-only">Discounted price</Label>
                                        <Input
                                            className={cn('h-9', discounted && 'border-emerald-600/50')}
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={line.discounted_price}
                                            onChange={(ev) => setLine(index, { discounted_price: ev.target.value })}
                                            required
                                        />
                                        {discounted && (
                                            <p className="text-[11px] text-emerald-700 dark:text-emerald-400">
                                                {Math.round((1 - num(line.discounted_price) / num(line.unit_price)) * 100)}% off
                                            </p>
                                        )}
                                        <InputError message={e('discounted_price')} />
                                    </div>
                                    {tax && (
                                        <div className="space-y-1">
                                            <Label className="text-xs lg:sr-only">GST</Label>
                                            <Select
                                                value={String(Number(line.gst_rate))}
                                                onValueChange={(value) => setLine(index, { gst_rate: Number(value) })}
                                            >
                                                <SelectTrigger className="h-9">
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
                                        </div>
                                    )}
                                    <div className="col-span-1 space-y-1 text-right sm:col-span-1">
                                        <Label className="text-xs lg:sr-only">Amount</Label>
                                        <p className="pt-2 text-sm font-medium tabular-nums">{format.money(computed?.amount ?? 0)}</p>
                                        {tax && (
                                            <p className="text-muted-foreground text-[11px] tabular-nums">
                                                incl. {format.money(computed?.tax ?? 0)} GST
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-start justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground hover:text-destructive"
                                            disabled={data.items.length === 1}
                                            onClick={() =>
                                                setData(
                                                    'items',
                                                    data.items.filter((_, i) => i !== index),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                            <span className="sr-only">Remove line {index + 1}</span>
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardContent className="grid gap-4 pt-6">
                            <div className="grid gap-2">
                                <Label htmlFor="notes">
                                    Notes <span className="text-muted-foreground">(printed on the invoice)</span>
                                </Label>
                                <Textarea
                                    id="notes"
                                    rows={2}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="e.g. PO number, delivery reference"
                                />
                                <InputError message={errors.notes} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="terms">Terms</Label>
                                <Textarea id="terms" rows={3} value={data.terms} onChange={(e) => setData('terms', e.target.value)} />
                                <InputError message={errors.terms} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-2 pt-6 text-sm">
                            <Row label="Subtotal" value={format.money(totals.subtotal)} />
                            {totals.discount > 0 && <Row label="Discount" value={`− ${format.money(totals.discount)}`} tone="good" />}
                            {tax && <Row label="Taxable value" value={format.money(totals.taxable)} />}
                            {!tax ? null : interstate ? (
                                <Row label="IGST" value={format.money(totals.igst)} />
                            ) : (
                                <>
                                    <Row label="CGST" value={format.money(totals.cgst)} />
                                    <Row label="SGST" value={format.money(totals.sgst)} />
                                </>
                            )}
                            <div className="flex items-center justify-between border-t pt-3 text-base font-semibold">
                                <span>Total</span>
                                <span className="tabular-nums">{format.money(totals.total)}</span>
                            </div>
                            <Button className="mt-3 w-full" disabled={processing}>
                                {invoice ? 'Save draft' : 'Save as draft'}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AccountsLayout>
    );
}

function Row({ label, value, tone }: { label: string; value: string; tone?: 'good' }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className={cn('tabular-nums', tone === 'good' && 'text-emerald-700 dark:text-emerald-400')}>{value}</span>
        </div>
    );
}
