import FlashMessages from '@/components/admin/flash-messages';
import AppLogo from '@/components/app-logo';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import Toaster from '@/components/ui/toaster';
import { formatBytes } from '@/lib/files';
import { cn } from '@/lib/utils';
import type { OnboardingSlot, OnboardingState } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, LogOut, Trash2, Undo2, Upload } from 'lucide-react';
import { FormEventHandler, useRef, type ReactNode } from 'react';

const NONE = 'none';
const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.md';

interface Props {
    onboarding: OnboardingState;
    person: { name: string; email: string; employee_code: string };
}

function Step({
    number,
    title,
    description,
    done,
    children,
}: {
    number: number;
    title: string;
    description: string;
    done: boolean;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start gap-4 space-y-0">
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                        done ? 'text-white' : 'bg-primary/10 text-primary',
                    )}
                    style={done ? { background: 'var(--status-good)' } : undefined}
                    aria-label={done ? 'Done' : `Step ${number}`}
                >
                    {done ? <CheckCircle2 className="size-5" /> : number}
                </span>
                <div className="space-y-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function DetailsForm({ state }: { state: OnboardingState }) {
    const { data, setData, put, processing, errors, isDirty, transform } = useForm({
        phone: state.details.phone ?? '',
        date_of_birth: state.details.date_of_birth ?? '',
        gender: state.details.gender ?? NONE,
        address: state.details.address ?? '',
    });

    // Radix Select cannot hold an empty value; "prefer not to say" travels blank.
    transform((payload) => ({ ...payload, gender: payload.gender === NONE ? '' : payload.gender }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('onboarding.details'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                    id="phone"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    required
                    disabled={!state.editable}
                    placeholder="+91 98765 43210"
                />
                <InputError message={errors.phone} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="dob">Date of birth</Label>
                <DatePicker
                    id="dob"
                    value={data.date_of_birth ?? ''}
                    onChange={(v) => setData('date_of_birth', v)}
                    notAfterToday
                    required
                    disabled={!state.editable}
                />
                <InputError message={errors.date_of_birth} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="gender">
                    Gender <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Select value={data.gender} onValueChange={(value) => setData('gender', value)} disabled={!state.editable}>
                    <SelectTrigger id="gender">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Prefer not to say</SelectItem>
                        <SelectItem value="female">Female</SelectItem>
                        <SelectItem value="male">Male</SelectItem>
                        <SelectItem value="other">Other</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="address">Current address</Label>
                <Textarea
                    id="address"
                    rows={3}
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    required
                    disabled={!state.editable}
                />
                <InputError message={errors.address} />
            </div>
            {state.editable && (
                <div className="sm:col-span-2">
                    <Button disabled={processing || !isDirty}>Save details</Button>
                </div>
            )}
        </form>
    );
}

function BankForm({ state }: { state: OnboardingState }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        account_name: state.bank.account_name ?? '',
        account_number: state.bank.account_number ?? '',
        account_number_confirmation: state.bank.account_number ?? '',
        ifsc: state.bank.ifsc ?? '',
        bank_name: state.bank.bank_name ?? '',
        branch: state.bank.branch ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('onboarding.bank'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="account_name">Account holder name</Label>
                <Input
                    id="account_name"
                    value={data.account_name}
                    onChange={(e) => setData('account_name', e.target.value)}
                    required
                    disabled={!state.editable}
                />
                <InputError message={errors.account_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="account_number">Account number</Label>
                <Input
                    id="account_number"
                    inputMode="numeric"
                    autoComplete="off"
                    value={data.account_number}
                    onChange={(e) => setData('account_number', e.target.value.replace(/\D/g, ''))}
                    required
                    disabled={!state.editable}
                />
                <InputError message={errors.account_number} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="account_number_confirmation">Account number again</Label>
                <Input
                    id="account_number_confirmation"
                    inputMode="numeric"
                    autoComplete="off"
                    value={data.account_number_confirmation}
                    onChange={(e) => setData('account_number_confirmation', e.target.value.replace(/\D/g, ''))}
                    onPaste={(e) => e.preventDefault()}
                    required
                    disabled={!state.editable}
                />
                <p className="text-muted-foreground text-xs">Typed again, not pasted, so a slip in the first one is caught.</p>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="ifsc">IFSC code</Label>
                <Input
                    id="ifsc"
                    className="font-mono uppercase"
                    maxLength={11}
                    value={data.ifsc}
                    onChange={(e) => setData('ifsc', e.target.value.toUpperCase())}
                    required
                    disabled={!state.editable}
                    placeholder="SBIN0001234"
                />
                <InputError message={errors.ifsc} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="bank_name">Bank name</Label>
                <Input
                    id="bank_name"
                    value={data.bank_name}
                    onChange={(e) => setData('bank_name', e.target.value)}
                    required
                    disabled={!state.editable}
                />
                <InputError message={errors.bank_name} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="branch">
                    Branch <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Input id="branch" value={data.branch} onChange={(e) => setData('branch', e.target.value)} disabled={!state.editable} />
            </div>
            {state.editable && (
                <div className="sm:col-span-2">
                    <Button disabled={processing || !isDirty}>Save bank details</Button>
                </div>
            )}
        </form>
    );
}

function Slot({ slot, editable }: { slot: OnboardingSlot; editable: boolean }) {
    const input = useRef<HTMLInputElement>(null);
    const picked = useRef<File | null>(null);
    const { post, processing, errors, progress, transform } = useForm<{ document_type_id: number; file: File | null }>({
        document_type_id: slot.type_id,
        file: null,
    });

    // setData is applied on the next render, so the chosen file is handed to
    // the request through transform instead; errors still land on this form.
    transform((data) => ({ ...data, file: picked.current }));

    const upload = (file: File) => {
        picked.current = file;
        post(route('onboarding.documents.store'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <li className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0 space-y-0.5">
                <p className="flex items-center gap-2 text-sm font-medium">
                    {slot.document ? (
                        <CheckCircle2 className="size-4 shrink-0" style={{ color: 'var(--status-good)' }} aria-label="Uploaded" />
                    ) : (
                        <span className="border-input size-4 shrink-0 rounded-full border" aria-hidden />
                    )}
                    {slot.name}
                    {slot.required ? (
                        <span className="text-destructive text-xs">Required</span>
                    ) : (
                        <span className="text-muted-foreground text-xs">Optional</span>
                    )}
                </p>
                {slot.description && <p className="text-muted-foreground pl-6 text-xs">{slot.description}</p>}
                {slot.document && (
                    <p className="text-muted-foreground truncate pl-6 text-xs">
                        {slot.document.original_name} · {formatBytes(slot.document.size)}
                    </p>
                )}
                <InputError message={errors.file} className="pl-6" />
                {progress && <p className="text-muted-foreground pl-6 text-xs">Uploading… {progress.percentage}%</p>}
            </div>

            <div className="flex shrink-0 items-center gap-1 pl-6 sm:pl-0">
                {slot.document && (
                    <Button asChild variant="ghost" size="sm">
                        <a href={route('onboarding.documents.download', slot.document.id)}>
                            <Download className="size-4" />
                            <span className="sr-only">Download {slot.name}</span>
                        </a>
                    </Button>
                )}
                {editable && slot.document && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={() => router.delete(route('onboarding.documents.destroy', slot.document!.id), { preserveScroll: true })}
                    >
                        <Trash2 className="size-4" />
                        <span className="sr-only">Remove {slot.name}</span>
                    </Button>
                )}
                {editable && (
                    <>
                        <input
                            ref={input}
                            type="file"
                            accept={ACCEPT}
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    upload(file);
                                }
                                e.target.value = '';
                            }}
                        />
                        <Button
                            variant={slot.document ? 'outline' : 'default'}
                            size="sm"
                            disabled={processing}
                            onClick={() => input.current?.click()}
                        >
                            <Upload className="size-4" /> {slot.document ? 'Replace' : 'Upload'}
                        </Button>
                    </>
                )}
            </div>
        </li>
    );
}

export default function Onboarding({ onboarding, person }: Props) {
    const done = (section: string) => onboarding.checklist.filter((i) => i.section === section && i.required).every((i) => i.done);
    const documents = onboarding.slots.filter((s) => !s.is_offer_letter);
    const offerSlot = onboarding.slots.find((s) => s.is_offer_letter);

    return (
        <div className="bg-muted/40 min-h-svh">
            <Head title="Complete your profile" />

            <header className="bg-sidebar text-sidebar-foreground">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3">
                    <div className="flex items-center gap-2">
                        <AppLogo />
                    </div>
                    <Link href={route('logout')} method="post" as="button" className="inline-flex items-center gap-1.5 text-sm hover:text-white">
                        <LogOut className="size-4" /> Sign out
                    </Link>
                </div>
            </header>

            <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-8">
                <section
                    className="relative overflow-hidden rounded-2xl p-6 text-white"
                    style={{ background: 'linear-gradient(120deg, var(--hero-from), var(--hero-to))' }}
                >
                    <span aria-hidden className="absolute -top-16 -right-10 size-48 rounded-full border-[24px] border-white/10" />
                    <div className="relative space-y-3">
                        <h1 className="text-2xl font-semibold tracking-tight">Welcome, {person.name.split(' ')[0]}</h1>
                        <p className="text-sm text-white/85">
                            Complete these steps so HR can finish setting you up. Everything saves as you go; submit when every required item is done.
                        </p>
                        <div className="max-w-sm space-y-1.5">
                            <div className="flex justify-between text-xs text-white/80">
                                <span>Required items</span>
                                <span className="tabular-nums">
                                    {onboarding.progress.done} of {onboarding.progress.total}
                                </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-white/25">
                                <div
                                    className="h-full rounded-full bg-white transition-[width]"
                                    style={{ width: `${onboarding.progress.percent}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </section>

                {onboarding.status === 'returned' && onboarding.note && (
                    <Alert variant="destructive">
                        <Undo2 className="size-4" />
                        <AlertDescription>
                            <span className="font-medium">HR asked for changes:</span> <span className="whitespace-pre-wrap">{onboarding.note}</span>
                        </AlertDescription>
                    </Alert>
                )}

                {!onboarding.editable && (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>Your profile has been submitted and is with HR for review.</AlertDescription>
                    </Alert>
                )}

                <Step number={1} title="Your details" description="How HR can reach you." done={done('details')}>
                    <DetailsForm state={onboarding} />
                </Step>

                <Step number={2} title="Bank details" description="Where your salary is paid. Stored encrypted." done={done('bank')}>
                    <BankForm state={onboarding} />
                </Step>

                <Step
                    number={3}
                    title="Documents"
                    description="One file per item: PDF, image or Office document, up to 10 MB."
                    done={done('documents')}
                >
                    <ul className="divide-y">
                        {documents.map((slot) => (
                            <Slot key={slot.type_id} slot={slot} editable={onboarding.editable} />
                        ))}
                    </ul>
                </Step>

                {offerSlot && (
                    <Step number={4} title="Offer letter" description="Download it, sign it, and upload the signed copy." done={done('offer')}>
                        <div className="space-y-2">
                            {onboarding.offer_letter && (
                                <Button asChild variant="outline" size="sm">
                                    <a href={route('onboarding.offer-letter')}>
                                        <Download className="size-4" /> Download {onboarding.offer_letter.name}
                                    </a>
                                </Button>
                            )}
                            <ul>
                                <Slot slot={offerSlot} editable={onboarding.editable} />
                            </ul>
                        </div>
                    </Step>
                )}

                {onboarding.editable && (
                    <div className="bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                        <p className="text-muted-foreground text-sm">
                            {onboarding.progress.complete
                                ? 'Everything required is in. Submit to send it to HR.'
                                : `${onboarding.progress.total - onboarding.progress.done} required item${onboarding.progress.total - onboarding.progress.done === 1 ? '' : 's'} still to go.`}
                        </p>
                        <Button disabled={!onboarding.progress.complete} onClick={() => router.post(route('onboarding.submit'))}>
                            Submit to HR
                        </Button>
                    </div>
                )}

                <p className="text-muted-foreground text-center text-xs">
                    Signed in as {person.email} · {person.employee_code}
                </p>
            </main>

            <FlashMessages />
            <Toaster />
        </div>
    );
}
