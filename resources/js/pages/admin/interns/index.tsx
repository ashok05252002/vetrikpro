import ArchiveButton from '@/components/admin/archive-button';
import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import Pill from '@/components/ui/pill';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { HandCoins, Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Interns', href: '/admin/interns' },
];

interface Row {
    id: number;
    employee_code: string;
    date_of_joining: string | null;
    has_stipend: boolean;
    stipend: string | null;
    status: string;
    onboarding_status: string | null;
    user: { id: number; name: string; email: string; is_active: boolean };
    department: string | null;
    designation: string | null;
    can_manage: boolean;
}

interface Props {
    interns: Paginated<Row>;
    filters: { search?: string; stipend?: string; archived: boolean };
    counts: { current: number; archived: number; paid: number };
    canSeeProfiles: boolean;
    /** Without the stipend permission the list says who is paid, not how much. */
    canSeePay: boolean;
}

export default function Interns({ interns, filters, counts, canSeeProfiles, canSeePay }: Props) {
    const { can } = usePermission();
    const format = useFormat();
    const { archived, ...narrowing } = filters;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Interns" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    icon={SECTIONS.interns.icon}
                    tone={SECTIONS.interns.tone}
                    title="Interns"
                    description={`${counts.current} current, ${counts.paid} with a stipend.`}
                    action={
                        can('interns.create') && (
                            <Button asChild>
                                <Link href={route('admin.interns.create')}>
                                    <Plus className="size-4" /> New intern
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex gap-1 border-b">
                    {[
                        { label: `Current (${counts.current})`, on: !archived, href: route('admin.interns.index') },
                        { label: `Archived (${counts.archived})`, on: archived, href: route('admin.interns.index', { archived: 1 }) },
                    ].map((view) => (
                        <Link
                            key={view.label}
                            href={view.href}
                            className={
                                view.on
                                    ? 'border-primary text-primary -mb-px border-b-2 px-3 py-2 text-sm font-semibold'
                                    : 'text-muted-foreground hover:text-foreground px-3 py-2 text-sm'
                            }
                        >
                            {view.label}
                        </Link>
                    ))}
                </div>

                <FilterBar
                    url={route('admin.interns.index')}
                    filters={narrowing}
                    keep={archived ? { archived: '1' } : undefined}
                    searchPlaceholder="Search name, email or code…"
                    selects={[
                        {
                            name: 'stipend',
                            placeholder: 'Paid and unpaid',
                            options: [
                                { value: 'paid', label: 'With stipend' },
                                { value: 'unpaid', label: 'No stipend' },
                            ],
                        },
                    ]}
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Intern</TableHead>
                                <TableHead className="hidden md:table-cell">Department</TableHead>
                                <TableHead className="hidden sm:table-cell">Started</TableHead>
                                <TableHead>Stipend</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {interns.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                        {Object.values(narrowing).some(Boolean)
                                            ? 'Nobody matches.'
                                            : archived
                                              ? 'No archived interns.'
                                              : 'No interns yet.'}
                                    </TableCell>
                                </TableRow>
                            )}
                            {interns.data.map((intern) => (
                                <TableRow key={intern.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <UserAvatar name={intern.user.name} className="size-8" />
                                            <div className="min-w-0">
                                                {canSeeProfiles ? (
                                                    <Link href={route('admin.employees.show', intern.id)} className="font-medium hover:underline">
                                                        {intern.user.name}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">{intern.user.name}</span>
                                                )}
                                                <span className="text-muted-foreground block truncate text-xs">
                                                    {intern.user.email} · <span className="font-mono">{intern.employee_code}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground hidden text-sm md:table-cell">
                                        {intern.department ?? '—'}
                                        {intern.designation && <span className="block text-xs">{intern.designation}</span>}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground hidden text-sm sm:table-cell">
                                        {intern.date_of_joining ? format.date(intern.date_of_joining) : '—'}
                                    </TableCell>
                                    <TableCell>
                                        {intern.has_stipend ? (
                                            <Pill color="var(--status-good)" icon={HandCoins}>
                                                {canSeePay ? `${format.money(intern.stipend)} / month` : 'Paid'}
                                            </Pill>
                                        ) : (
                                            <Pill color="var(--muted-foreground)">Unpaid</Pill>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            {!archived && can('interns.edit') && intern.can_manage && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={route('admin.interns.edit', intern.id)}>
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('interns.edit') && intern.can_manage && (
                                                <ArchiveButton
                                                    employeeId={intern.id}
                                                    name={intern.user.name}
                                                    archived={archived}
                                                    compact
                                                    routes="admin.interns"
                                                />
                                            )}
                                            {archived && can('interns.delete') && intern.can_manage && (
                                                <DeleteButton
                                                    url={route('admin.interns.destroy', intern.id)}
                                                    label={intern.user.name}
                                                    description="Only for someone added by mistake. Anyone with tasks, bugs or project history cannot be deleted — they stay archived."
                                                />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={interns} />
            </div>
        </AppLayout>
    );
}
