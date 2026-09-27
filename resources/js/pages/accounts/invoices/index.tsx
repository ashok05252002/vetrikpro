import InvoiceStatusBadge from '@/components/accounts/invoice-status';
import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import { cn } from '@/lib/utils';
import type { InvoiceSummary, Option, Paginated } from '@/types';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, CircleDashed, Plus, Send, type LucideIcon } from 'lucide-react';

interface Props {
    invoices: Paginated<InvoiceSummary>;
    summary: { outstanding: number; overdue: number; overdue_count: number; paid_this_month: number; drafts: number };
    statuses: Option[];
    customers: Option[];
    filters: { search?: string; status?: string; customer?: string };
}

function Tile({
    label,
    value,
    hint,
    tone,
    href,
    icon,
    chip,
}: {
    label: string;
    value: string;
    hint?: string;
    tone?: 'critical' | 'good';
    href?: string;
    icon: LucideIcon;
    chip: Tone;
}) {
    const body = (
        <>
            <div className="flex items-center justify-between gap-2">
                <p className="text-muted-foreground text-xs font-medium">{label}</p>
                <IconChip icon={icon} tone={chip} size="xs" />
            </div>
            <p
                className={cn(
                    'mt-1 text-xl font-semibold tabular-nums',
                    tone === 'critical' && 'text-[var(--status-critical)]',
                    tone === 'good' && 'text-emerald-700 dark:text-emerald-400',
                )}
            >
                {value}
            </p>
            {hint && <p className="text-muted-foreground mt-0.5 text-xs">{hint}</p>}
        </>
    );

    return href ? (
        <Link href={href} className="bg-card hover:border-foreground/20 rounded-xl border p-4 transition-colors">
            {body}
        </Link>
    ) : (
        <div className="bg-card rounded-xl border p-4">{body}</div>
    );
}

export default function Invoices({ invoices, summary, statuses, customers, filters }: Props) {
    const { can } = usePermission();
    const format = useFormat();

    return (
        <AccountsLayout
            tab="invoices"
            title="Invoices"
            description="Draft, send and track GST invoices. A draft gets its number when it is sent."
            actions={
                can('invoices.create') && (
                    <Button asChild>
                        <Link href={route('accounts.invoices.create')}>
                            <Plus className="size-4" /> New invoice
                        </Link>
                    </Button>
                )
            }
        >
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Tile
                    icon={Send}
                    chip="blue"
                    label="Outstanding"
                    value={format.money(summary.outstanding)}
                    hint="Sent, not yet paid"
                    href={route('accounts.invoices.index', { status: 'sent' })}
                />
                <Tile
                    icon={AlertTriangle}
                    chip="red"
                    label="Overdue"
                    value={format.money(summary.overdue)}
                    hint={
                        summary.overdue_count === 0
                            ? 'Nothing late'
                            : `${summary.overdue_count} invoice${summary.overdue_count === 1 ? '' : 's'} past due`
                    }
                    tone={summary.overdue_count > 0 ? 'critical' : undefined}
                    href={route('accounts.invoices.index', { status: 'overdue' })}
                />
                <Tile
                    icon={CheckCircle2}
                    chip="green"
                    label="Paid this month"
                    value={format.money(summary.paid_this_month)}
                    tone="good"
                    href={route('accounts.invoices.index', { status: 'paid' })}
                />
                <Tile
                    icon={CircleDashed}
                    chip="slate"
                    label="Drafts"
                    value={String(summary.drafts)}
                    hint="Not sent yet"
                    href={route('accounts.invoices.index', { status: 'draft' })}
                />
            </div>

            <FilterBar
                url={route('accounts.invoices.index')}
                filters={filters}
                searchPlaceholder="Customer or number, e.g. INV-0007…"
                selects={[
                    { name: 'status', placeholder: 'Any status', options: statuses },
                    { name: 'customer', placeholder: 'All customers', options: customers },
                ]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-28">Invoice</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead className="hidden md:table-cell">Date</TableHead>
                            <TableHead className="hidden md:table-cell">Due</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {invoices.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                    {Object.values(filters).some(Boolean) ? 'No invoices match.' : 'No invoices yet.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {invoices.data.map((invoice) => (
                            <TableRow
                                key={invoice.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(route('accounts.invoices.show', invoice.id))}
                            >
                                <TableCell>
                                    <Link
                                        href={route('accounts.invoices.show', invoice.id)}
                                        className={cn('font-mono text-xs hover:underline', invoice.number === null && 'text-muted-foreground italic')}
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        {invoice.reference}
                                    </Link>
                                </TableCell>
                                <TableCell className="font-medium">{invoice.bill_name}</TableCell>
                                <TableCell className="text-muted-foreground hidden text-sm md:table-cell">
                                    {format.date(invoice.issue_date)}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'hidden text-sm md:table-cell',
                                        invoice.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground',
                                    )}
                                >
                                    {invoice.due_date ? format.date(invoice.due_date) : '—'}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'text-right font-medium tabular-nums',
                                        invoice.status === 'cancelled' && 'text-muted-foreground line-through',
                                    )}
                                >
                                    {format.money(invoice.total)}
                                </TableCell>
                                <TableCell>
                                    <InvoiceStatusBadge status={invoice.status} overdue={invoice.is_overdue} />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={invoices} />
        </AccountsLayout>
    );
}
