import RenewalDialog, { type RenewalOptions } from '@/components/accounts/renewal-dialog';
import RenewalStatusBadge from '@/components/accounts/renewal-status';
import FilterBar from '@/components/admin/filter-bar';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AccountsLayout from '@/layouts/accounts/accounts-layout';
import { cn } from '@/lib/utils';
import type { Option, Paginated, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, BellRing, CalendarRange, IndianRupee, Mail, Plus, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

interface RenewalRow {
    id: number;
    title: string;
    identifier: string | null;
    provider: string | null;
    renewal_type_id: number;
    type_label: string;
    cycle_label: string;
    customer: { id: number; name: string } | null;
    owners: string[];
    status: string;
    is_snoozed: boolean;
    snoozed_until: string | null;
    notify_customer: boolean;
    cost_price: number | null;
    open: { id: number; due_date: string; amount: number; paid: number; balance: number; days_left: number; in_window: boolean } | null;
}

interface Props extends RenewalOptions {
    renewals: Paginated<RenewalRow>;
    summary: { overdue_count: number; overdue_balance: number; week: number; month: number; collected_this_month: number };
    filters: { search?: string; category?: string; customer?: string; status?: string; due?: string };
    modes: Option[];
}

function Tile({
    label,
    value,
    hint,
    critical,
    href,
    icon,
    chip,
}: {
    label: string;
    value: string;
    hint?: string;
    critical?: boolean;
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
            <p className={cn('mt-1 text-xl font-semibold tabular-nums', critical && 'text-[var(--status-critical)]')}>{value}</p>
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

export default function Renewals({ renewals, summary, filters, types, cycles, customers, people }: Props) {
    const { can } = usePermission();
    const format = useFormat();
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const canCost = can('renewals.cost');

    return (
        <AccountsLayout
            tab="renewals"
            title="Renewals"
            description="Domains, servers, SSL, AMCs and anything else paid again every cycle. Reminders run daily from the notice period until each one is fully paid."
            actions={
                can('renewals.create') && (
                    <Button onClick={() => setOpen(true)}>
                        <Plus className="size-4" /> New renewal
                    </Button>
                )
            }
        >
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Tile
                    icon={AlertTriangle}
                    chip="red"
                    label="Overdue"
                    value={String(summary.overdue_count)}
                    hint={summary.overdue_count ? `${format.money(summary.overdue_balance)} unpaid` : 'Nothing late'}
                    critical={summary.overdue_count > 0}
                    href={route('accounts.renewals.index', { due: 'overdue' })}
                />
                <Tile
                    icon={BellRing}
                    chip="amber"
                    label="Due in 7 days"
                    value={String(summary.week)}
                    href={route('accounts.renewals.index', { due: 'week' })}
                />
                <Tile
                    icon={CalendarRange}
                    chip="blue"
                    label="Due in 30 days"
                    value={String(summary.month)}
                    href={route('accounts.renewals.index', { due: 'month' })}
                />
                <Tile icon={IndianRupee} chip="green" label="Collected this month" value={format.money(summary.collected_this_month)} />
            </div>

            <FilterBar
                url={route('accounts.renewals.index')}
                filters={filters}
                searchPlaceholder="Name, domain, provider or customer…"
                selects={[
                    {
                        name: 'due',
                        placeholder: 'Any due date',
                        options: [
                            { value: 'reminding', label: 'Being reminded now' },
                            { value: 'overdue', label: 'Overdue' },
                            { value: 'week', label: 'Due in 7 days' },
                            { value: 'month', label: 'Due in 30 days' },
                            { value: 'partly', label: 'Partly paid' },
                        ],
                    },
                    { name: 'type', placeholder: 'All types', options: types.map(({ value, label }) => ({ value, label })) },
                    { name: 'customer', placeholder: 'All customers', options: [{ value: 'own', label: 'Our own' }, ...customers] },
                    {
                        name: 'status',
                        placeholder: 'Active',
                        options: [
                            { value: 'cancelled', label: 'Cancelled' },
                            { value: 'all', label: 'Active and cancelled' },
                        ],
                    },
                ]}
            />

            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Renewal</TableHead>
                            <TableHead className="hidden md:table-cell">Customer</TableHead>
                            <TableHead className="hidden sm:table-cell">Due</TableHead>
                            <TableHead className="text-right">Balance</TableHead>
                            {canCost && <TableHead className="hidden text-right xl:table-cell">Our cost</TableHead>}
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {renewals.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={canCost ? 6 : 5} className="text-muted-foreground py-10 text-center">
                                    {Object.values(filters).some(Boolean)
                                        ? 'No renewals match.'
                                        : 'No renewals yet. Add your first domain or server.'}
                                </TableCell>
                            </TableRow>
                        )}
                        {renewals.data.map((r) => (
                            <TableRow key={r.id} className="cursor-pointer" onClick={() => router.visit(route('accounts.renewals.show', r.id))}>
                                <TableCell className={r.status === 'cancelled' ? 'text-muted-foreground' : undefined}>
                                    <Link
                                        href={route('accounts.renewals.show', r.id)}
                                        className="font-medium hover:underline"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        {r.title}
                                    </Link>
                                    {r.notify_customer && (
                                        <Mail className="text-muted-foreground ml-1.5 inline size-3.5" aria-label="Customer is reminded too" />
                                    )}
                                    <span className="text-muted-foreground block text-xs">
                                        {[r.type_label, r.identifier, r.provider, r.cycle_label].filter(Boolean).join(' · ')}
                                    </span>
                                </TableCell>
                                <TableCell className="hidden text-sm md:table-cell">
                                    {r.customer?.name ?? <span className="text-muted-foreground">Our own</span>}
                                </TableCell>
                                <TableCell className="text-muted-foreground hidden text-sm sm:table-cell">
                                    {r.open ? format.date(r.open.due_date) : '—'}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {r.open ? (
                                        <>
                                            <span className="font-medium">{format.money(r.open.balance)}</span>
                                            {r.open.paid > 0 && (
                                                <span className="text-muted-foreground block text-xs">of {format.money(r.open.amount)}</span>
                                            )}
                                        </>
                                    ) : (
                                        '—'
                                    )}
                                </TableCell>
                                {canCost && (
                                    <TableCell className="text-muted-foreground hidden text-right tabular-nums xl:table-cell">
                                        {r.cost_price !== null ? format.money(r.cost_price) : '—'}
                                    </TableCell>
                                )}
                                <TableCell>
                                    <RenewalStatusBadge renewal={r} />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Pagination meta={renewals} />

            <RenewalDialog
                renewal={null}
                options={{ types, cycles, customers, people }}
                open={open}
                onOpenChange={setOpen}
                currentUserId={auth.user?.id}
            />
        </AccountsLayout>
    );
}
