import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import ConfigLayout from '@/layouts/config/config-layout';
import { formatDate, formatMoney } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { DateFormat, Option } from '@/types';
import { useForm } from '@inertiajs/react';
import { Building2, Upload, X } from 'lucide-react';
import { FormEventHandler, useMemo, useRef } from 'react';

interface SettingsValues {
    company_name: string;
    company_legal_name: string;
    company_tax_id: string;
    company_state: string;
    invoice_due_days: number;
    invoice_terms: string;
    invoice_bank_details: string;
    company_email: string;
    company_phone: string;
    company_website: string;
    company_address: string;
    display_timezone: string;
    display_date_format: DateFormat;
    display_currency: string;
}

/** useForm's generic needs an index signature; the fields above stay typed. */
type SettingsForm = SettingsValues & {
    logo: File | null;
    remove_logo: boolean;
    logo_dark: File | null;
    remove_logo_dark: boolean;
    favicon: File | null;
    remove_favicon: boolean;
    [key: string]: string | number | File | boolean | null;
};

type ImageField = 'logo' | 'logo_dark' | 'favicon';

interface Props {
    settings: SettingsValues;
    images: Record<ImageField, string | null>;
    timezones: string[];
    dateFormats: Option[];
    states: Option[];
}

interface Slot {
    field: ImageField;
    label: string;
    hint: string;
    accept: string;
    /** The background the image is previewed on, as it will be used. */
    surface: 'light' | 'dark';
}

const IMAGE_SLOTS: Slot[] = [
    {
        field: 'logo',
        label: 'Logo — light background',
        hint: 'Also used on emails, offer letters and invoices.',
        accept: 'image/png,image/jpeg,image/svg+xml,image/webp',
        surface: 'light',
    },
    {
        field: 'logo_dark',
        label: 'Logo — dark background',
        hint: 'Usually a white or light version of the logo.',
        accept: 'image/png,image/jpeg,image/svg+xml,image/webp',
        surface: 'dark',
    },
    {
        field: 'favicon',
        label: 'Favicon',
        hint: 'The browser-tab icon. ICO, PNG or SVG, square, up to 512 KB.',
        accept: '.ico,image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml',
        surface: 'light',
    },
];

/** One upload: preview on the surface it will sit on, choose, remove. Saved with the form. */
function ImageSlot({
    slot,
    current,
    file,
    error,
    disabled,
    onPick,
    onRemove,
}: {
    slot: Slot;
    current: string | null;
    file: File | null;
    error?: string;
    disabled: boolean;
    onPick: (file: File | null) => void;
    onRemove: () => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const preview = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);
    const shown = preview ?? current;

    return (
        <div className="space-y-2 py-4 first:pt-0 last:pb-0">
            <div className="flex items-center gap-4">
                <div
                    className={cn(
                        'flex shrink-0 items-center justify-center overflow-hidden rounded-lg border p-1.5',
                        slot.field === 'favicon' ? 'size-12' : 'h-14 w-24',
                        slot.surface === 'dark' ? 'border-sidebar-border bg-sidebar' : 'bg-white',
                    )}
                >
                    {shown ? (
                        <img src={shown} alt={slot.label} className="size-full object-contain" />
                    ) : (
                        <Building2 className={cn('size-5', slot.surface === 'dark' ? 'text-sidebar-foreground/60' : 'text-neutral-400')} />
                    )}
                </div>

                <div className="min-w-0 flex-1 space-y-1">
                    <p className="text-sm font-medium">{slot.label}</p>
                    <p className="text-muted-foreground text-xs">{file ? `${file.name} — not saved until you press Save.` : slot.hint}</p>
                </div>

                {!disabled && (
                    <div className="flex shrink-0 gap-1">
                        <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()}>
                            <Upload className="size-4" />
                            <span className="sr-only sm:not-sr-only">{shown ? 'Replace' : 'Upload'}</span>
                        </Button>
                        {shown && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    onRemove();
                                    if (input.current) {
                                        input.current.value = '';
                                    }
                                }}
                            >
                                <X className="size-4" />
                                <span className="sr-only">Remove {slot.label}</span>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <input ref={input} type="file" accept={slot.accept} className="hidden" onChange={(e) => onPick(e.target.files?.[0] ?? null)} />
            <InputError message={error} />
        </div>
    );
}

/** A few common codes up front; anything valid can still be typed. */
const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'];

export default function SettingsPage({ settings, images, timezones, dateFormats, states }: Props) {
    const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm<SettingsForm>({
        ...settings,
        logo: null,
        remove_logo: false,
        logo_dark: null,
        remove_logo_dark: false,
        favicon: null,
        remove_favicon: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // POST rather than PUT: a multipart body needs a real POST, and method
        // spoofing here buys nothing.
        post(route('admin.settings.update'), {
            preserveScroll: true,
            forceFormData: true,
            // A new favicon only shows on a full load; the tab icon is outside Inertia's reach.
            onSuccess: () => {
                if (data.favicon || data.remove_favicon) {
                    window.location.reload();
                }
                // The saved images now come back as URLs; the picked files are done with.
                reset('logo', 'remove_logo', 'logo_dark', 'remove_logo_dark', 'favicon', 'remove_favicon');
            },
        });
    };

    const canEdit = usePermission().can('settings.edit');

    // Live preview of the regional choices, using today's date and a sample amount.
    const sample = { timezone: data.display_timezone, dateFormat: data.display_date_format, currency: data.display_currency };

    return (
        <ConfigLayout tab="organisation">
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">Your organisation's name, branding and regional defaults.</p>
                    {canEdit ? (
                        <div className="flex items-center gap-3">
                            {recentlySuccessful && <span className="text-muted-foreground text-sm">Saved</span>}
                            <Button disabled={processing}>Save settings</Button>
                        </div>
                    ) : (
                        <span className="text-muted-foreground text-xs">View only — you can’t change these.</span>
                    )}
                </div>

                <div className="grid max-w-5xl gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Company</CardTitle>
                            <CardDescription>The name here appears in the sidebar, the sign-in screen and the browser tab.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="company_name">Company name</Label>
                                <Input
                                    id="company_name"
                                    value={data.company_name}
                                    onChange={(e) => setData('company_name', e.target.value)}
                                    required
                                    autoFocus
                                    placeholder="Vetrik Private Limited"
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_legal_name">
                                    Registered name <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Input
                                    id="company_legal_name"
                                    value={data.company_legal_name}
                                    onChange={(e) => setData('company_legal_name', e.target.value)}
                                    placeholder="The full name on official documents"
                                />
                                <InputError message={errors.company_legal_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_tax_id">
                                    Tax / registration number <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Input id="company_tax_id" value={data.company_tax_id} onChange={(e) => setData('company_tax_id', e.target.value)} />
                                <InputError message={errors.company_tax_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_state">GST state</Label>
                                <Select value={data.company_state || undefined} onValueChange={(value) => setData('company_state', value)}>
                                    <SelectTrigger id="company_state">
                                        <SelectValue placeholder="Where the company is registered" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {states.map((state) => (
                                            <SelectItem key={state.value} value={state.value}>
                                                {state.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">Invoices to this state carry CGST + SGST; to any other, IGST.</p>
                                <InputError message={errors.company_state} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Branding</CardTitle>
                            <CardDescription>
                                PNG, JPG, SVG or WebP up to 2 MB. The dark version is used on the navy sidebar, the sign-in panel and in dark mode;
                                without one, the light logo is shown on a white tile.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {IMAGE_SLOTS.map((slot) => (
                                <ImageSlot
                                    key={slot.field}
                                    slot={slot}
                                    current={data[`remove_${slot.field}`] ? null : images[slot.field]}
                                    file={data[slot.field] as File | null}
                                    error={errors[slot.field]}
                                    disabled={!canEdit}
                                    onPick={(file) => setData((current) => ({ ...current, [slot.field]: file, [`remove_${slot.field}`]: false }))}
                                    onRemove={() => setData((current) => ({ ...current, [slot.field]: null, [`remove_${slot.field}`]: true }))}
                                />
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Contact</CardTitle>
                            <CardDescription>Printed on offer letters, promotion letters and invoices.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="company_email">Email</Label>
                                    <Input
                                        id="company_email"
                                        type="email"
                                        value={data.company_email}
                                        onChange={(e) => setData('company_email', e.target.value)}
                                        placeholder="hr@company.com"
                                    />
                                    <InputError message={errors.company_email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="company_phone">Phone</Label>
                                    <Input
                                        id="company_phone"
                                        value={data.company_phone}
                                        onChange={(e) => setData('company_phone', e.target.value)}
                                        placeholder="+91 98765 43210"
                                    />
                                    <InputError message={errors.company_phone} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_website">Website</Label>
                                <Input
                                    id="company_website"
                                    type="url"
                                    value={data.company_website}
                                    onChange={(e) => setData('company_website', e.target.value)}
                                    placeholder="https://company.com"
                                />
                                <InputError message={errors.company_website} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_address">Address</Label>
                                <Textarea
                                    id="company_address"
                                    value={data.company_address}
                                    onChange={(e) => setData('company_address', e.target.value)}
                                />
                                <InputError message={errors.company_address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Invoices</CardTitle>
                            <CardDescription>Defaults for each new invoice; every one can still be changed on the invoice.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid max-w-40 gap-2">
                                <Label htmlFor="invoice_due_days">Payment due after (days)</Label>
                                <Input
                                    id="invoice_due_days"
                                    type="number"
                                    min={0}
                                    max={365}
                                    value={data.invoice_due_days}
                                    onChange={(e) => setData('invoice_due_days', Number(e.target.value))}
                                />
                                <InputError message={errors.invoice_due_days} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="invoice_terms">Terms</Label>
                                <Textarea
                                    id="invoice_terms"
                                    rows={3}
                                    value={data.invoice_terms}
                                    onChange={(e) => setData('invoice_terms', e.target.value)}
                                />
                                <InputError message={errors.invoice_terms} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="invoice_bank_details">
                                    Bank details for payment <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Textarea
                                    id="invoice_bank_details"
                                    rows={3}
                                    value={data.invoice_bank_details}
                                    onChange={(e) => setData('invoice_bank_details', e.target.value)}
                                    placeholder={'Account name, number, IFSC, bank'}
                                />
                                <p className="text-muted-foreground text-xs">Printed on every invoice.</p>
                                <InputError message={errors.invoice_bank_details} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Regional</CardTitle>
                            <CardDescription>How dates and money are shown. Stored values are never changed.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="display_timezone">Timezone</Label>
                                <Select value={data.display_timezone} onValueChange={(value) => setData('display_timezone', value)}>
                                    <SelectTrigger id="display_timezone">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {timezones.map((zone) => (
                                            <SelectItem key={zone} value={zone}>
                                                {zone}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.display_timezone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="display_date_format">Date format</Label>
                                <Select
                                    value={data.display_date_format}
                                    onValueChange={(value) => setData('display_date_format', value as DateFormat)}
                                >
                                    <SelectTrigger id="display_date_format">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {dateFormats.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.display_date_format} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="display_currency">Currency</Label>
                                <Input
                                    id="display_currency"
                                    value={data.display_currency}
                                    onChange={(e) => setData('display_currency', e.target.value.toUpperCase())}
                                    maxLength={3}
                                    list="currency-codes"
                                    placeholder="INR"
                                />
                                <datalist id="currency-codes">
                                    {CURRENCIES.map((code) => (
                                        <option key={code} value={code} />
                                    ))}
                                </datalist>
                                <InputError message={errors.display_currency} />
                                <p className="text-muted-foreground text-xs">Three-letter ISO code. Used for employee salaries.</p>
                            </div>

                            <div className="bg-muted/40 rounded-lg border p-3 text-sm">
                                <p className="text-muted-foreground mb-1 text-xs">Preview</p>
                                <p>
                                    {formatDate(new Date().toISOString().slice(0, 10), sample)} · {formatMoney(55000, sample)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </ConfigLayout>
    );
}
