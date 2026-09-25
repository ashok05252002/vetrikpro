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
import type { DateFormat, Option } from '@/types';
import { useForm } from '@inertiajs/react';
import { Building2, Upload, X } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

interface SettingsValues {
    company_name: string;
    company_legal_name: string;
    company_tax_id: string;
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
    [key: string]: string | File | boolean | null;
};

interface Props {
    settings: SettingsValues;
    logoUrl: string | null;
    timezones: string[];
    dateFormats: Option[];
}

/** A few common codes up front; anything valid can still be typed. */
const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD'];

export default function SettingsPage({ settings, logoUrl, timezones, dateFormats }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm<SettingsForm>({
        ...settings,
        logo: null,
        remove_logo: false,
    });

    const pickLogo = (file: File | null) => {
        setData((current) => ({ ...current, logo: file, remove_logo: false }));
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    const dropLogo = () => {
        setData((current) => ({ ...current, logo: null, remove_logo: true }));
        setPreview(null);
        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // POST rather than PUT: a multipart body needs a real POST, and method
        // spoofing here buys nothing.
        post(route('admin.settings.update'), { preserveScroll: true, forceFormData: true });
    };

    const canEdit = usePermission().can('settings.edit');
    const shownLogo = preview ?? (data.remove_logo ? null : logoUrl);

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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Logo</CardTitle>
                            <CardDescription>Shown beside the company name. PNG, JPG, SVG or WebP, up to 2 MB.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-4">
                                <div className="bg-muted/40 flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border">
                                    {shownLogo ? (
                                        <img src={shownLogo} alt="Company logo" className="size-full object-contain" />
                                    ) : (
                                        <Building2 className="text-muted-foreground size-6" />
                                    )}
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={() => fileInput.current?.click()}>
                                        <Upload className="size-4" /> Choose file
                                    </Button>
                                    {shownLogo && (
                                        <Button type="button" variant="ghost" size="sm" onClick={dropLogo}>
                                            <X className="size-4" /> Remove
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <input
                                ref={fileInput}
                                type="file"
                                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                className="hidden"
                                onChange={(e) => pickLogo(e.target.files?.[0] ?? null)}
                            />
                            <InputError message={errors.logo} />

                            {data.logo && <p className="text-muted-foreground text-xs">{data.logo.name} — not saved until you press Save.</p>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Contact</CardTitle>
                            <CardDescription>Stored on the organisation record. Nothing renders these yet.</CardDescription>
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
