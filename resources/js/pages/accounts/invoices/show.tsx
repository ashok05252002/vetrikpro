import InvoiceStatusBadge from '@/components/accounts/invoice-status';
import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import type { InvoiceStatus, User } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, MoreHorizontal, Pencil, Send, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    invoice: {
        id: number;
        number: number | null;
        reference: string;
        status: InvoiceStatus;
        bill_name: string;
        bill_email: string | null;
        issue_date: string;
        due_date: string | null;
        total: string;
        issued_at: string | null;
        sent_at: string | null;
        sent_to: string | null;
        paid_at: string | null;
        cancelled_at: string | null;
        is_overdue: boolean;
        creator: Pick<User, 'id' | 'name'> | null;
        customer: { id: number; name: string; email: string | null } | null;
    };
    preview: string;
    companyStateSet: boolean;
}

function SendDialog({ invoice }: { invoice: Props['invoice'] }) {
    const [open, setOpen] = useState(false);
    const format = useFormat();
    const { data, setData, post, processing, errors, transform } = useForm({
        to: invoice.bill_email ?? invoice.customer?.email ?? '',
        cc: '',
        message: '',
    });

    transform((d) => ({ ...d, cc: d.cc.split(/[,;\s]+/).filter(Boolean) }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('accounts.invoices.send', invoice.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    const resend = invoice.status !== 'draft';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant={resend ? 'outline' : 'default'}
                    className={resend ? undefined : 'text-white hover:opacity-90'}
                    style={resend ? undefined : { background: 'var(--mail-bg)' }}
                >
                    <Send className="size-4" style={resend ? { color: 'var(--tone-blue)' } : undefined} /> {resend ? 'Send again' : 'Send invoice'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{resend ? `Send ${invoice.reference} again` : 'Send invoice'}</DialogTitle>
                        <DialogDescription>
                            {resend
                                ? 'The same invoice is emailed again with its PDF.'
                                : `This issues the invoice — it takes the next number and can no longer be edited — and emails it for ${format.money(invoice.total)} with the PDF attached.`}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="email"
                            required
                            value={data.to}
                            onChange={(e) => setData('to', e.target.value)}
                            placeholder="accounts@customer.com"
                        />
                        <InputError message={errors.to} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cc">
                            Cc <span className="text-muted-foreground">(optional, separate with commas)</span>
                        </Label>
                        <Input id="cc" value={data.cc} onChange={(e) => setData('cc', e.target.value)} />
                        <InputError
                            message={(errors as Record<string, string>)['cc'] ?? Object.entries(errors).find(([k]) => k.startsWith('cc.'))?.[1]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="message">
                            Message <span className="text-muted-foreground">(optional)</span>
                        </Label>
                        <Textarea
                            id="message"
                            rows={3}
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            placeholder="Added above the invoice details in the email."
                        />
                        <InputError message={errors.message} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button disabled={processing} className="text-white hover:opacity-90" style={{ background: 'var(--mail-bg)' }}>
                            <Send className="size-4" /> Send
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function MarkPaidDialog({ invoice }: { invoice: Props['invoice'] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ paid_on: new Date().toISOString().slice(0, 10) });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <CheckCircle2 className="size-4" /> Mark paid
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('accounts.invoices.mark-paid', invoice.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>Mark {invoice.reference} paid</DialogTitle>
                        <DialogDescription>When did the payment arrive?</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="paid_on">Paid on</Label>
                        <Input id="paid_on" type="date" required value={data.paid_on} onChange={(e) => setData('paid_on', e.target.value)} />
                        <InputError message={errors.paid_on} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button disabled={processing}>Mark paid</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CancelDialog({ invoice }: { invoice: Props['invoice'] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline" className="text-destructive hover:text-destructive">
                    <XCircle className="size-4" /> Cancel invoice
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel {invoice.reference}?</DialogTitle>
                    <DialogDescription>
                        It stays on record, marked cancelled, and its number is not reused. To bill again, raise a new invoice. The customer is not
                        emailed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Keep it</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.post(route('accounts.invoices.cancel', invoice.id), {}, { preserveScroll: true, onSuccess: () => setOpen(false) })
                        }
                    >
                        Cancel invoice
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function ShowInvoice({ invoice, preview, companyStateSet }: Props) {
    const { can } = usePermission();
    const format = useFormat();
    const draft = invoice.status === 'draft';

    const facts: [string, string][] = [
        ['Amount', format.money(invoice.total)],
        ['Invoice date', format.date(invoice.issue_date)],
        ['Due', invoice.due_date ? format.date(invoice.due_date) : '—'],
        ...(invoice.issued_at ? ([['Issued', format.date(invoice.issued_at)]] as [string, string][]) : []),
        ...(invoice.sent_at
            ? ([['Emailed', `${format.date(invoice.sent_at)}${invoice.sent_to ? ` to ${invoice.sent_to}` : ''}`]] as [string, string][])
            : []),
        ...(invoice.paid_at ? ([['Paid', format.date(invoice.paid_at)]] as [string, string][]) : []),
        ...(invoice.cancelled_at ? ([['Cancelled', format.date(invoice.cancelled_at)]] as [string, string][]) : []),
        ...(invoice.creator ? ([['Raised by', invoice.creator.name]] as [string, string][]) : []),
    ];

    return (
        <AccountsLayout
            tab="invoices"
            title={draft ? `Draft invoice · ${invoice.bill_name}` : `${invoice.reference} · ${invoice.bill_name}`}
            back={route('accounts.invoices.index')}
            trail={[{ title: invoice.reference, href: route('accounts.invoices.show', invoice.id) }]}
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    {draft && can('invoices.edit') && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('accounts.invoices.edit', invoice.id)}>
                                <Pencil className="size-4" /> Edit
                            </Link>
                        </Button>
                    )}
                    <Button asChild size="sm" variant="outline">
                        <a href={route('accounts.invoices.pdf', invoice.id)}>
                            <Download className="size-4" /> PDF
                        </a>
                    </Button>
                    {invoice.status === 'sent' && can('invoices.edit') && (
                        <>
                            <CancelDialog invoice={invoice} />
                            <MarkPaidDialog invoice={invoice} />
                        </>
                    )}
                    {invoice.status !== 'cancelled' && can('invoices.send') && <SendDialog invoice={invoice} />}
                    {draft && (can('invoices.send') || can('invoices.delete')) && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button size="sm" variant="ghost">
                                    <MoreHorizontal className="size-4" />
                                    <span className="sr-only">More</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {can('invoices.send') && (
                                    <DropdownMenuItem
                                        onSelect={() => router.post(route('accounts.invoices.mark-sent', invoice.id), {}, { preserveScroll: true })}
                                    >
                                        Issue without emailing
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                    {draft && can('invoices.delete') && <DeleteButton url={route('accounts.invoices.destroy', invoice.id)} label="this draft" />}
                </div>
            }
        >
            <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <InvoiceStatusBadge status={invoice.status} overdue={invoice.is_overdue} className="text-sm" />
                {facts.map(([label, value]) => (
                    <span key={label}>
                        <span className="text-muted-foreground">{label}</span> <span className="font-medium">{value}</span>
                    </span>
                ))}
            </div>

            {!companyStateSet && (
                <p className="text-muted-foreground text-xs">
                    The company’s GST state isn’t set in Organisation settings, so tax is shown as CGST + SGST.
                </p>
            )}

            {/* The very HTML the PDF is drawn from, on paper. */}
            <div className="bg-muted/40 overflow-x-auto rounded-xl border p-3 sm:p-6">
                <iframe
                    title={`Invoice ${invoice.reference}`}
                    srcDoc={preview}
                    sandbox=""
                    className="mx-auto block h-[1120px] w-[794px] max-w-none rounded-sm bg-white shadow-md"
                />
            </div>
        </AccountsLayout>
    );
}
