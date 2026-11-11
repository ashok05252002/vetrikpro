import RenewalDialog, { type RenewalFields, type RenewalOptions } from '@/components/accounts/renewal-dialog';
import RenewalStatusBadge from '@/components/accounts/renewal-status';
import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pill from '@/components/ui/pill';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDashed,
    ExternalLink,
    IndianRupee,
    MoreHorizontal,
    PauseCircle,
    Pencil,
    PlayCircle,
    RotateCcw,
    Scissors,
    Send,
    XCircle,
} from 'lucide-react';
import { FormEventHandler, useState, type ReactNode } from 'react';

interface OpenPeriod {
    id: number;
    due_date: string;
    amount: number;
    paid: number;
    balance: number;
    days_left: number;
    remind_from: string;
}

interface Renewal extends RenewalFields {
    id: number;
    type_label: string;
    cycle_label: string;
    status: string;
    snoozed_until: string | null;
    is_snoozed: boolean;
    customer: { id: number; name: string; email: string | null; is_active: boolean } | null;
    owners: { id: number; name: string; email: string }[];
    start_mail_sent_at: string | null;
    start_blocker: string | null;
    creator: { id: number; name: string } | null;
    created_at: string | null;
    open: OpenPeriod | null;
    team_recipients: string[];
    customer_email: string | null;
    has_payments: boolean;
}

interface Cycle {
    id: number;
    due_date: string;
    amount: number;
    paid: number;
    balance: number;
    status: 'pending' | 'paid' | 'written_off' | 'skipped';
    status_label: string;
    closed_on: string | null;
    closer: string | null;
    payments: { id: number; paid_on: string; amount: number; mode: string; reference: string | null; note: string | null; recorder: string | null }[];
}

interface Reminder {
    id: number;
    sent_on: string;
    audience: 'team' | 'customer' | 'start';
    recipients: string[];
    days_left: number;
}

interface Props extends RenewalOptions {
    renewal: Renewal;
    periods: Cycle[];
    reminders: Reminder[];
    modes: Option[];
    customerRemindersOn: boolean;
}

const cycleColor: Record<Cycle['status'], string> = {
    pending: 'var(--status-warning)',
    paid: 'var(--status-good)',
    written_off: 'var(--muted-foreground)',
    skipped: 'var(--muted-foreground)',
};

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[9rem_1fr] gap-3 py-1.5 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{children}</dd>
        </div>
    );
}

function today(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function PaymentDialog({
    renewal,
    modes,
    open,
    onOpenChange,
}: {
    renewal: Renewal;
    modes: Option[];
    open: boolean;
    onOpenChange: (o: boolean) => void;
}) {
    const format = useFormat();
    const balance = renewal.open?.balance ?? 0;
    const { data, setData, post, processing, errors, reset } = useForm({
        paid_on: today(),
        amount: String(balance),
        mode: modes[0]?.value ?? 'upi',
        reference: '',
        note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('accounts.renewals.pay', renewal.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    const amount = Number(data.amount) || 0;
    const remaining = Math.max(0, balance - amount);

    return (
        <Dialog
            open={open}
            onOpenChange={(o) => {
                if (o) setData((d) => ({ ...d, amount: String(balance), paid_on: today() }));
                onOpenChange(o);
            }}
        >
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Record payment</DialogTitle>
                        <DialogDescription>
                            Balance for the period due {renewal.open && format.date(renewal.open.due_date)}: <strong>{format.money(balance)}</strong>.
                            A part payment keeps the reminders going for the rest.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="p-amount">Amount received</Label>
                            <Input
                                id="p-amount"
                                type="number"
                                step="0.01"
                                min={0.01}
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                required
                                autoFocus
                            />
                            <InputError message={errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-date">Received on</Label>
                            <DatePicker id="p-date" value={data.paid_on} onChange={(v) => setData('paid_on', v)} notAfterToday required />
                            <InputError message={errors.paid_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-mode">Mode</Label>
                            <Select value={data.mode} onValueChange={(v) => setData('mode', v)}>
                                <SelectTrigger id="p-mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {modes.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>
                                            {m.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.mode} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="p-ref">
                                Reference <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Input
                                id="p-ref"
                                value={data.reference}
                                onChange={(e) => setData('reference', e.target.value)}
                                placeholder="UTR / cheque no."
                            />
                            <InputError message={errors.reference} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="p-note">
                                Note <span className="text-muted-foreground">(optional)</span>
                            </Label>
                            <Input id="p-note" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                            <InputError message={errors.note} />
                        </div>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {amount >= balance && balance > 0
                            ? 'This clears the period: reminders stop and the next period opens.'
                            : `Leaves ${format.money(remaining)} due; reminders continue.`}
                    </p>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>Record payment</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** A yes/no step for an action that posts, with an optional date or amount. */
function ActionDialog({
    open,
    onOpenChange,
    title,
    description,
    url,
    confirm,
    destructive,
    fields,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    title: string;
    description: ReactNode;
    url: string;
    confirm: string;
    destructive?: boolean;
    fields?: 'snooze' | 'reactivate';
}) {
    const { data, setData, post, processing, errors } = useForm({ until: '', due_date: '', amount: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(url, { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {fields === 'snooze' && (
                        <div className="grid gap-2">
                            <Label htmlFor="a-until">Pause until</Label>
                            <DatePicker id="a-until" value={data.until} onChange={(v) => setData('until', v)} min={today()} required />
                            <InputError message={errors.until} />
                        </div>
                    )}
                    {fields === 'reactivate' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="a-due">Next due date</Label>
                                <DatePicker id="a-due" value={data.due_date} onChange={(v) => setData('due_date', v)} required />
                                <InputError message={errors.due_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="a-amount">
                                    Amount <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Input
                                    id="a-amount"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                />
                                <InputError message={errors.amount} />
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Back
                        </Button>
                        <Button disabled={processing} variant={destructive ? 'destructive' : 'default'}>
                            {confirm}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RenewalShow({ renewal, periods, reminders, modes, customerRemindersOn, types, cycles, customers, people }: Props) {
    const { can } = usePermission();
    const format = useFormat();
    const [editing, setEditing] = useState(false);
    const [paying, setPaying] = useState(false);
    const [action, setAction] = useState<null | 'snooze' | 'cancel' | 'reactivate' | 'writeoff' | 'start'>(null);

    const active = renewal.status === 'active';
    const open = renewal.open;
    const customerReminded = customerRemindersOn && renewal.notify_customer && !!renewal.customer_email;

    return (
        <AccountsLayout
            tab="renewals"
            title={renewal.title}
            description={[renewal.type_label, renewal.identifier, renewal.customer?.name ?? 'Our own'].filter(Boolean).join(' · ')}
            back={route('accounts.renewals.index')}
            trail={[{ title: renewal.title, href: route('accounts.renewals.show', renewal.id) }]}
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    {can('renewals.pay') && open && (
                        <Button size="sm" onClick={() => setPaying(true)}>
                            <IndianRupee className="size-4" /> Record payment
                        </Button>
                    )}
                    {can('renewals.edit') && (
                        <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                            <Pencil className="size-4" /> Edit
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="sm" variant="outline" aria-label="More actions">
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {open && (
                                <DropdownMenuItem asChild>
                                    <a href={route('accounts.renewals.preview', [renewal.id, 'team'])} target="_blank" rel="noreferrer">
                                        <ExternalLink className="size-4" /> Preview team reminder
                                    </a>
                                </DropdownMenuItem>
                            )}
                            {open && renewal.customer && (
                                <DropdownMenuItem asChild>
                                    <a href={route('accounts.renewals.preview', [renewal.id, 'customer'])} target="_blank" rel="noreferrer">
                                        <ExternalLink className="size-4" /> Preview customer reminder
                                    </a>
                                </DropdownMenuItem>
                            )}
                            {open && renewal.customer && (
                                <DropdownMenuItem asChild>
                                    <a href={route('accounts.renewals.preview', [renewal.id, 'start'])} target="_blank" rel="noreferrer">
                                        <ExternalLink className="size-4" /> Preview start email
                                    </a>
                                </DropdownMenuItem>
                            )}
                            {can('renewals.edit') && open && renewal.customer && (
                                <DropdownMenuItem onSelect={() => setAction('start')} disabled={!!renewal.start_blocker}>
                                    <Send className="size-4" /> {renewal.start_mail_sent_at ? 'Send start email again…' : 'Send start email…'}
                                </DropdownMenuItem>
                            )}
                            {can('renewals.edit') && active && (
                                <>
                                    <DropdownMenuSeparator />
                                    {renewal.is_snoozed ? (
                                        <ResumeItem id={renewal.id} />
                                    ) : (
                                        <DropdownMenuItem onSelect={() => setAction('snooze')}>
                                            <PauseCircle className="size-4" /> Pause reminders…
                                        </DropdownMenuItem>
                                    )}
                                </>
                            )}
                            {can('renewals.pay') && open && open.paid > 0 && (
                                <DropdownMenuItem onSelect={() => setAction('writeoff')}>
                                    <Scissors className="size-4" /> Write off balance…
                                </DropdownMenuItem>
                            )}
                            {can('renewals.edit') && active && (
                                <DropdownMenuItem onSelect={() => setAction('cancel')}>
                                    <XCircle className="size-4" /> Cancel renewal…
                                </DropdownMenuItem>
                            )}
                            {can('renewals.edit') && !active && (
                                <DropdownMenuItem onSelect={() => setAction('reactivate')}>
                                    <RotateCcw className="size-4" /> Restart renewal…
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                    {can('renewals.delete') && !renewal.has_payments && (
                        <DeleteButton
                            url={route('accounts.renewals.destroy', renewal.id)}
                            label={renewal.title}
                            description="It is removed with its periods and reminder log. Renewals with payments can only be cancelled."
                        />
                    )}
                </div>
            }
        >
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex-row items-start justify-between gap-3 space-y-0">
                        <div className="space-y-1">
                            <CardTitle className="text-base">Current period</CardTitle>
                            <CardDescription>
                                {open
                                    ? `Reminders from ${format.date(open.remind_from)} until fully paid${renewal.repeat_daily ? ', every day' : ' (first day, due day, then weekly)'}.`
                                    : 'No open period: this renewal is cancelled.'}
                            </CardDescription>
                        </div>
                        <RenewalStatusBadge renewal={{ ...renewal, open: open ? { ...open } : null }} />
                    </CardHeader>
                    <CardContent>
                        {open ? (
                            <div className="grid gap-3 sm:grid-cols-4">
                                <Stat label="Due on" value={format.date(open.due_date)} />
                                <Stat label="Amount" value={format.money(open.amount)} />
                                <Stat label="Received" value={format.money(open.paid)} />
                                <Stat label="Balance" value={format.money(open.balance)} strong />
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">Restart it from the ⋯ menu to track it again from a new due date.</p>
                        )}
                        {open && (
                            <div className="text-muted-foreground mt-4 space-y-1 border-t pt-3 text-sm">
                                {renewal.is_snoozed && (
                                    <p>
                                        <PauseCircle className="mr-1 inline size-4" />
                                        Reminders paused until <strong className="text-foreground">{format.date(renewal.snoozed_until)}</strong>.
                                    </p>
                                )}
                                <p>
                                    Team reminder to:{' '}
                                    <span className="text-foreground">
                                        {renewal.team_recipients.length
                                            ? renewal.team_recipients.join(', ')
                                            : 'nobody: add a responsible person or reminder addresses'}
                                    </span>
                                </p>
                                <p>
                                    Customer reminder:{' '}
                                    <span className="text-foreground">
                                        {customerReminded
                                            ? renewal.customer_email
                                            : !renewal.customer
                                              ? 'no customer'
                                              : !renewal.notify_customer
                                                ? 'off for this renewal'
                                                : !customerRemindersOn
                                                  ? 'switched off in Configuration hub'
                                                  : 'the customer has no active billing email'}
                                    </span>
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y">
                            <Row label="Type">{renewal.type_label}</Row>
                            {renewal.identifier && <Row label="Domain / server">{renewal.identifier}</Row>}
                            {renewal.provider && <Row label="Provider">{renewal.provider}</Row>}
                            <Row label="Renews">{renewal.cycle_label}</Row>
                            <Row label="Customer">{renewal.customer?.name ?? 'Our own'}</Row>
                            <Row label="Responsible">{renewal.owners.length ? renewal.owners.map((o) => o.name).join(', ') : '—'}</Row>
                            {renewal.start_date && <Row label="Plan started">{format.date(renewal.start_date)}</Row>}
                            {renewal.customer && (
                                <Row label="Start email">
                                    {renewal.start_mail_sent_at
                                        ? `Sent ${format.dateTime(renewal.start_mail_sent_at)}`
                                        : renewal.send_start_mail && renewal.start_blocker
                                          ? `Not sent: ${renewal.start_blocker}`
                                          : 'Not sent'}
                                </Row>
                            )}
                            <Row label="Charge">{renewal.sell_price !== null ? format.money(renewal.sell_price) : '—'}</Row>
                            {can('renewals.cost') && (
                                <Row label="Our cost">{renewal.cost_price !== null ? format.money(renewal.cost_price) : '—'}</Row>
                            )}
                            <Row label="Auto-renews">{renewal.auto_renew ? 'Yes, at the provider' : 'No'}</Row>
                            {renewal.notes && (
                                <Row label="Notes">
                                    <span className="whitespace-pre-line">{renewal.notes}</span>
                                </Row>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Periods and payments</CardTitle>
                    <CardDescription>A period closes when its payments cover it; the next one opens a cycle after its due date.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {periods.map((c) => (
                        <div key={c.id} className="rounded-lg border">
                            <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                <div className="flex items-center gap-3">
                                    <span className="font-medium">Due {format.date(c.due_date)}</span>
                                    <Pill
                                        color={cycleColor[c.status]}
                                        icon={c.status === 'paid' ? CheckCircle2 : c.status === 'pending' ? CircleDashed : XCircle}
                                    >
                                        {c.status === 'pending' && c.paid > 0 ? 'Partly paid' : c.status_label}
                                    </Pill>
                                </div>
                                <div className="text-muted-foreground text-sm tabular-nums">
                                    {format.money(c.paid)} of {format.money(c.amount)}
                                    {c.status === 'pending' && c.balance > 0 && (
                                        <span className="text-foreground ml-2 font-medium">· {format.money(c.balance)} due</span>
                                    )}
                                    {c.closed_on && (
                                        <span className="ml-2">
                                            · closed {format.date(c.closed_on)}
                                            {c.closer ? ` by ${c.closer}` : ''}
                                        </span>
                                    )}
                                </div>
                            </div>
                            {c.payments.length > 0 && (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Received</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                            <TableHead className="hidden sm:table-cell">Mode</TableHead>
                                            <TableHead className="hidden md:table-cell">Reference / note</TableHead>
                                            <TableHead className="hidden md:table-cell">By</TableHead>
                                            <TableHead className="w-10" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {c.payments.map((p) => (
                                            <TableRow key={p.id}>
                                                <TableCell className="text-sm">{format.date(p.paid_on)}</TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">{format.money(p.amount)}</TableCell>
                                                <TableCell className="hidden text-sm sm:table-cell">{p.mode}</TableCell>
                                                <TableCell className="text-muted-foreground hidden text-sm md:table-cell">
                                                    {[p.reference, p.note].filter(Boolean).join(' · ') || '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground hidden text-sm md:table-cell">
                                                    {p.recorder ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {can('renewals.pay') && (
                                                        <DeleteButton
                                                            url={route('accounts.renewals.payments.destroy', [renewal.id, p.id])}
                                                            label={`the payment of ${format.money(p.amount)}`}
                                                            description={
                                                                c.status === 'pending'
                                                                    ? 'The balance goes back up and reminders continue.'
                                                                    : 'This period reopens and the next one, opened by this payment, is removed.'
                                                            }
                                                        />
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Reminders sent</CardTitle>
                    <CardDescription>The last 60, newest first.</CardDescription>
                </CardHeader>
                <CardContent>
                    {reminders.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            None yet.{open ? ` The first goes out on ${format.date(open.remind_from)}.` : ''}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead className="text-right">Days left then</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reminders.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell className="text-sm">{format.date(r.sent_on)}</TableCell>
                                        <TableCell className="text-sm">
                                            <span className="text-muted-foreground mr-1">
                                                {r.audience === 'customer' ? 'Customer:' : r.audience === 'start' ? 'Start email:' : 'Team:'}
                                            </span>
                                            {r.recipients.join(', ')}
                                        </TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">
                                            {r.days_left < 0 ? `${-r.days_left} overdue` : r.days_left}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <RenewalDialog
                renewal={renewal}
                options={{ types, cycles, customers, people }}
                open={editing}
                onOpenChange={setEditing}
                hasOpenPeriod={!!open}
            />
            {open && <PaymentDialog renewal={renewal} modes={modes} open={paying} onOpenChange={setPaying} />}
            <ActionDialog
                open={action === 'snooze'}
                onOpenChange={(o) => !o && setAction(null)}
                title="Pause reminders"
                description="No reminders, to the team or the customer, until this date. Use it when the customer has promised to pay. Recording a full payment ends the pause."
                url={route('accounts.renewals.snooze', renewal.id)}
                confirm="Pause"
                fields="snooze"
            />
            <ActionDialog
                open={action === 'start'}
                onOpenChange={(o) => !o && setAction(null)}
                title="Send the start email?"
                description={`Emails ${renewal.customer_email ?? 'the customer'}: the plan${renewal.start_date ? ` started on ${format.date(renewal.start_date)}` : ''}, renews ${renewal.cycle_label.toLowerCase()}, next renewal on ${open ? format.date(open.due_date) : '—'}.`}
                url={route('accounts.renewals.send-start', renewal.id)}
                confirm="Send email"
            />
            <ActionDialog
                open={action === 'writeoff'}
                onOpenChange={(o) => !o && setAction(null)}
                title="Write off the balance?"
                description={`The ${open ? format.money(open.balance) : ''} still due is dropped, this period closes and the next one opens.`}
                url={route('accounts.renewals.write-off', renewal.id)}
                confirm="Write off"
                destructive
            />
            <ActionDialog
                open={action === 'cancel'}
                onOpenChange={(o) => !o && setAction(null)}
                title={`Cancel ${renewal.title}?`}
                description="For when it is not being renewed. Reminders stop and the open period is skipped. The history stays, and it can be restarted later."
                url={route('accounts.renewals.cancel', renewal.id)}
                confirm="Cancel renewal"
                destructive
            />
            <ActionDialog
                open={action === 'reactivate'}
                onOpenChange={(o) => !o && setAction(null)}
                title={`Restart ${renewal.title}`}
                description="It is tracked again from this due date. Leave the amount empty to use the usual price."
                url={route('accounts.renewals.reactivate', renewal.id)}
                confirm="Restart"
                fields="reactivate"
            />
        </AccountsLayout>
    );
}

function Stat({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
    return (
        <div>
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className={strong ? 'text-lg font-semibold tabular-nums' : 'text-lg tabular-nums'}>{value}</p>
        </div>
    );
}

function ResumeItem({ id }: { id: number }) {
    const { post } = useForm({ until: null as string | null });
    return (
        <DropdownMenuItem onSelect={() => post(route('accounts.renewals.snooze', id), { preserveScroll: true })}>
            <PlayCircle className="size-4" /> Resume reminders
        </DropdownMenuItem>
    );
}
