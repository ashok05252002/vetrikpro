import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEventHandler, useEffect, type ReactNode } from 'react';

export interface CategoryOption extends Option {
    cycle: number;
    remind: number;
}

export interface RenewalOptions {
    types: CategoryOption[];
    cycles: Option[];
    customers: Option[];
    people: Option[];
}

/** What the edit form holds; the server sends the same shape for an existing renewal. */
export interface RenewalFields {
    customer_id: number | null;
    renewal_type_id: number;
    title: string;
    identifier: string | null;
    provider: string | null;
    cycle_months: number;
    cost_price: number | null;
    sell_price: number | null;
    remind_days_before: number;
    repeat_daily: boolean;
    notify_customer: boolean;
    auto_renew: boolean;
    owner_ids: number[];
    start_date: string | null;
    send_start_mail: boolean;
    notes: string | null;
    due_date: string | null;
    amount: number | null;
}

type Form = {
    customer_id: string;
    renewal_type_id: string;
    title: string;
    identifier: string;
    provider: string;
    cycle_months: string;
    cost_price: string;
    sell_price: string;
    remind_days_before: string;
    repeat_daily: boolean;
    notify_customer: boolean;
    auto_renew: boolean;
    owner_ids: string[];
    start_date: string;
    send_start_mail: boolean;
    notes: string;
    due_date: string;
    amount: string;
};

const NONE = '__none';

/** YYYY-MM-DD plus whole months, clamped to the month's last day (31 Jan + 1 = 28 Feb). */
function addMonths(date: string, months: number): string {
    const [y, m, d] = date.split('-').map(Number);
    const target = new Date(Date.UTC(y, m - 1 + months, 1));
    const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(d, last));
    return target.toISOString().slice(0, 10);
}
const str = (v: number | string | null | undefined) => (v === null || v === undefined ? '' : String(v));

function blank(options: RenewalOptions, ownerId?: number): Form {
    const first = options.types[0];
    return {
        customer_id: '',
        renewal_type_id: first?.value ?? '',
        title: '',
        identifier: '',
        provider: '',
        cycle_months: String(first?.cycle ?? 12),
        cost_price: '',
        sell_price: '',
        remind_days_before: String(first?.remind ?? 15),
        repeat_daily: true,
        notify_customer: false,
        auto_renew: false,
        owner_ids: ownerId ? [String(ownerId)] : [],
        start_date: '',
        send_start_mail: false,
        notes: '',
        due_date: '',
        amount: '',
    };
}

function Field({
    id,
    label,
    hint,
    error,
    className,
    children,
}: {
    id?: string;
    label: ReactNode;
    hint?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid content-start gap-2 ${className ?? ''}`}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Toggle({
    id,
    label,
    hint,
    checked,
    onChange,
    disabled,
}: {
    id: string;
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (on: boolean) => void;
    disabled?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-4">
            <div className="space-y-0.5">
                <Label htmlFor={id} className="text-sm font-medium">
                    {label}
                </Label>
                {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            </div>
            <Switch id={id} checked={checked} onCheckedChange={onChange} disabled={disabled} />
        </div>
    );
}

/**
 * Add or edit a renewal. The due date and amount are those of the open
 * period; changing the category on a new renewal suggests its usual cycle
 * and notice period.
 */
export default function RenewalDialog({
    renewal,
    options,
    open,
    onOpenChange,
    currentUserId,
    hasOpenPeriod = true,
}: {
    renewal: (RenewalFields & { id: number }) | null;
    options: RenewalOptions;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    currentUserId?: number;
    hasOpenPeriod?: boolean;
}) {
    const { can } = usePermission();
    const canCost = can('renewals.cost');
    const { data, setData, post, put, processing, errors, clearErrors, transform } = useForm<Form>(blank(options, currentUserId));

    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData(
            renewal
                ? {
                      customer_id: str(renewal.customer_id),
                      renewal_type_id: String(renewal.renewal_type_id),
                      title: renewal.title,
                      identifier: renewal.identifier ?? '',
                      provider: renewal.provider ?? '',
                      cycle_months: String(renewal.cycle_months),
                      cost_price: str(renewal.cost_price),
                      sell_price: str(renewal.sell_price),
                      remind_days_before: String(renewal.remind_days_before),
                      repeat_daily: renewal.repeat_daily,
                      notify_customer: renewal.notify_customer,
                      auto_renew: renewal.auto_renew,
                      owner_ids: renewal.owner_ids.map(String),
                      start_date: renewal.start_date ?? '',
                      send_start_mail: renewal.send_start_mail,
                      notes: renewal.notes ?? '',
                      due_date: renewal.due_date ?? '',
                      amount: str(renewal.amount),
                  }
                : blank(options, currentUserId),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, renewal?.id]);

    const pickCategory = (value: string) => {
        const category = options.types.find((c) => c.value === value);
        setData((d) => ({
            ...d,
            renewal_type_id: value,
            // Suggest the usual cycle and notice only while adding.
            ...(renewal || !category ? {} : { cycle_months: String(category.cycle), remind_days_before: String(category.remind) }),
        }));
    };

    transform((d) => ({
        ...d,
        notify_customer: d.customer_id ? d.notify_customer : false,
        send_start_mail: d.customer_id && !renewal ? d.send_start_mail : false,
    }));

    // A new plan's first renewal is one cycle after it starts; fill it in if not set yet.
    const pickStart = (value: string) =>
        setData((d) => ({
            ...d,
            start_date: value,
            ...(!renewal && value && !d.due_date ? { due_date: addMonths(value, Number(d.cycle_months) || 12) } : {}),
        }));

    const peopleLeft = options.people.filter((p) => !data.owner_ids.includes(p.value));
    const nameOf = (id: string) => options.people.find((p) => p.value === id)?.label ?? 'Unknown';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const done = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (renewal) {
            put(route('accounts.renewals.update', renewal.id), done);
        } else {
            post(route('accounts.renewals.store'), done);
        }
    };

    const select = (id: string, value: string, onChange: (v: string) => void, items: Option[], placeholder: string, noneLabel?: string) => (
        <Select value={value || (noneLabel ? NONE : undefined)} onValueChange={(v) => onChange(v === NONE ? '' : v)}>
            <SelectTrigger id={id}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {noneLabel && <SelectItem value={NONE}>{noneLabel}</SelectItem>}
                {items.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>{renewal ? `Edit ${renewal.title}` : 'New renewal'}</DialogTitle>
                        <DialogDescription>
                            A domain, server, AMC or anything else paid again every cycle. Reminders start the set number of days before the due date
                            and continue until it is fully paid.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id="r-category" label="Type" error={errors.renewal_type_id}>
                            {select('r-category', data.renewal_type_id, pickCategory, options.types, 'Type')}
                        </Field>
                        <Field id="r-customer" label="Customer" error={errors.customer_id}>
                            {select(
                                'r-customer',
                                data.customer_id,
                                (v) => setData('customer_id', v),
                                options.customers,
                                'Customer',
                                'Our own (no customer)',
                            )}
                        </Field>
                        <Field id="r-title" label="Name" error={errors.title} className="sm:col-span-2">
                            <Input
                                id="r-title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                required
                                autoFocus
                                placeholder="e.g. Client X website domain"
                            />
                        </Field>
                        <Field id="r-identifier" label="Domain / server / plan" error={errors.identifier}>
                            <Input
                                id="r-identifier"
                                value={data.identifier}
                                onChange={(e) => setData('identifier', e.target.value)}
                                placeholder="clientx.com, 203.0.113.10, Business plan"
                            />
                        </Field>
                        <Field id="r-provider" label="Provider" error={errors.provider}>
                            <Input
                                id="r-provider"
                                value={data.provider}
                                onChange={(e) => setData('provider', e.target.value)}
                                placeholder="GoDaddy, Hostinger, AWS…"
                            />
                        </Field>
                    </div>

                    <div className="bg-muted/30 grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                        <Field id="r-start" label="Plan started on" hint="Optional. Shown in the start email." error={errors.start_date}>
                            <DatePicker id="r-start" value={data.start_date} onChange={pickStart} />
                        </Field>
                        <Field
                            id="r-due"
                            label={renewal ? 'Current period due on' : 'Next due (expiry) date'}
                            error={errors.due_date}
                            hint={!hasOpenPeriod ? 'Cancelled: restart it to set a due date.' : undefined}
                        >
                            <DatePicker
                                id="r-due"
                                value={data.due_date}
                                onChange={(v) => setData('due_date', v)}
                                required={hasOpenPeriod}
                                disabled={!hasOpenPeriod}
                            />
                        </Field>
                        <Field id="r-cycle" label="Renews" error={errors.cycle_months}>
                            {select('r-cycle', data.cycle_months, (v) => setData('cycle_months', v), options.cycles, 'Cycle')}
                        </Field>
                        <Field id="r-remind" label="Remind before" error={errors.remind_days_before}>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="r-remind"
                                    type="number"
                                    min={0}
                                    max={365}
                                    className="w-24"
                                    value={data.remind_days_before}
                                    onChange={(e) => setData('remind_days_before', e.target.value)}
                                    required
                                />
                                <span className="text-muted-foreground text-sm">days</span>
                            </div>
                        </Field>
                        {canCost && (
                            <Field
                                id="r-cost"
                                label="Our cost"
                                hint="What we pay the provider. Never shown to the customer."
                                error={errors.cost_price}
                            >
                                <Input
                                    id="r-cost"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.cost_price}
                                    onChange={(e) => setData('cost_price', e.target.value)}
                                />
                            </Field>
                        )}
                        <Field id="r-sell" label="Charge to customer" hint="Each new period is raised for this." error={errors.sell_price}>
                            <Input
                                id="r-sell"
                                type="number"
                                step="0.01"
                                min={0}
                                value={data.sell_price}
                                onChange={(e) => setData('sell_price', e.target.value)}
                            />
                        </Field>
                        <Field
                            id="r-amount"
                            label="This period’s amount"
                            hint={renewal ? 'Can differ from the usual price.' : 'Leave empty to use the price.'}
                            error={errors.amount}
                        >
                            <Input
                                id="r-amount"
                                type="number"
                                step="0.01"
                                min={0}
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                disabled={!hasOpenPeriod}
                                placeholder={data.sell_price || data.cost_price || '0'}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="r-owner"
                            label="Responsible people"
                            hint="Each one gets the daily reminder."
                            error={errors.owner_ids ?? Object.entries(errors).find(([k]) => k.startsWith('owner_ids.'))?.[1]}
                        >
                            <div className="flex flex-wrap gap-1.5">
                                {data.owner_ids.length === 0 && <p className="text-muted-foreground text-xs">Nobody yet.</p>}
                                {data.owner_ids.map((id) => (
                                    <span key={id} className="bg-muted inline-flex items-center gap-1 rounded-md border py-0.5 pr-1 pl-2 text-xs">
                                        {nameOf(id)}
                                        <button
                                            type="button"
                                            aria-label={`Remove ${nameOf(id)}`}
                                            className="hover:bg-background rounded p-0.5"
                                            onClick={() =>
                                                setData(
                                                    'owner_ids',
                                                    data.owner_ids.filter((o) => o !== id),
                                                )
                                            }
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </span>
                                ))}
                            </div>
                            {peopleLeft.length > 0 && (
                                <Select value="" onValueChange={(v) => setData('owner_ids', [...data.owner_ids, v])}>
                                    <SelectTrigger id="r-owner">
                                        <SelectValue placeholder="Add a person…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {peopleLeft.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <div className="grid content-start gap-4 pt-1">
                            <Toggle
                                id="r-daily"
                                label="Remind every day"
                                hint={data.repeat_daily ? 'Daily until paid.' : 'First day, due day, then weekly until paid.'}
                                checked={data.repeat_daily}
                                onChange={(on) => setData('repeat_daily', on)}
                            />
                            <Toggle
                                id="r-customer-notify"
                                label="Remind the customer too"
                                hint={
                                    data.customer_id ? 'Emails the customer’s billing address. Our cost is never included.' : 'Pick a customer first.'
                                }
                                checked={!!data.customer_id && data.notify_customer}
                                onChange={(on) => setData('notify_customer', on)}
                                disabled={!data.customer_id}
                            />
                            <InputError message={errors.notify_customer} />
                            {!renewal && (
                                <>
                                    <Toggle
                                        id="r-start-mail"
                                        label="Send the customer a start email"
                                        hint={
                                            data.customer_id
                                                ? 'Once, when saved: “your plan started on …, next renewal on …”.'
                                                : 'Pick a customer first.'
                                        }
                                        checked={!!data.customer_id && data.send_start_mail}
                                        onChange={(on) => setData('send_start_mail', on)}
                                        disabled={!data.customer_id}
                                    />
                                    <InputError message={errors.send_start_mail} />
                                </>
                            )}
                            <Toggle
                                id="r-auto"
                                label="Auto-renews at the provider"
                                hint="For your information. Reminders still run until it is marked paid."
                                checked={data.auto_renew}
                                onChange={(on) => setData('auto_renew', on)}
                            />
                        </div>
                        <Field id="r-notes" label="Notes" error={errors.notes} className="sm:col-span-2">
                            <Textarea
                                id="r-notes"
                                rows={2}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="Login hint, account ID, anything useful. Internal only."
                            />
                        </Field>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{renewal ? 'Save' : 'Add renewal'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
