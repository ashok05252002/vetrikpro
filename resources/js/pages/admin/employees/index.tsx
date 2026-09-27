import AccessToggle from '@/components/admin/access-toggle';
import ArchiveButton from '@/components/admin/archive-button';
import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import OnboardingBadge from '@/components/onboarding/onboarding-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserAvatar from '@/components/work/user-avatar';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Employee, OnboardingStatus, Option, Paginated, User } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { employmentTypeLabels, statusLabels } from './labels';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
];

interface Props {
    employees: Paginated<
        Employee & {
            user: Pick<User, 'id' | 'name' | 'email' | 'is_active' | 'role'>;
            onboarding: { status: OnboardingStatus; done: number; total: number } | null;
            can_toggle_access: boolean;
        }
    >;
    departments: Department[];
    roles: Option[];
    onboardingStatuses: Option[];
    filters: { search?: string; department?: string; role?: string; account?: string; onboarding?: string; archived: boolean };
    archivedCount: number;
}

const accountOptions: Option[] = [
    { value: 'active', label: 'Can sign in' },
    { value: 'deactivated', label: 'Deactivated' },
];

export default function EmployeesIndex({ employees, departments, roles, onboardingStatuses, filters, archivedCount }: Props) {
    const { archived, ...narrowing } = filters;
    const { can } = usePermission();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employees" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Employees"
                    description="Everyone in the organisation: their login, role and HR record, in one place."
                    action={
                        can('employees.create') && (
                            <Button asChild>
                                <Link href={route('admin.employees.create')}>
                                    <Plus className="size-4" /> New employee
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex gap-1 border-b">
                    {[
                        { label: 'Current staff', on: !archived, href: route('admin.employees.index') },
                        {
                            label: `Archived${archivedCount ? ` (${archivedCount})` : ''}`,
                            on: archived,
                            href: route('admin.employees.index', { archived: 1 }),
                        },
                    ].map((view) => (
                        <Link
                            key={view.label}
                            href={view.href}
                            className={
                                view.on
                                    ? 'border-primary text-foreground -mb-px border-b-2 px-3 py-2 text-sm font-medium'
                                    : 'text-muted-foreground hover:text-foreground px-3 py-2 text-sm'
                            }
                        >
                            {view.label}
                        </Link>
                    ))}
                </div>

                <FilterBar
                    url={route('admin.employees.index')}
                    filters={narrowing}
                    keep={archived ? { archived: '1' } : undefined}
                    searchPlaceholder="Search name, email or code…"
                    selects={[
                        { name: 'role', placeholder: 'All roles', options: roles },
                        { name: 'account', placeholder: 'Any login status', options: accountOptions },
                        { name: 'onboarding', placeholder: 'Any onboarding', options: onboardingStatuses },
                        {
                            name: 'department',
                            placeholder: 'All departments',
                            options: departments.map((d) => ({ value: String(d.id), label: d.name })),
                        },
                    ]}
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead className="hidden lg:table-cell">Department</TableHead>
                                <TableHead className="hidden md:table-cell">Status</TableHead>
                                <TableHead>Login</TableHead>
                                <TableHead className="hidden sm:table-cell">Onboarding</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-10 text-center">
                                        {Object.values(narrowing).some(Boolean)
                                            ? 'Nobody matches these filters.'
                                            : archived
                                              ? 'Nobody is archived.'
                                              : 'No employees yet.'}
                                    </TableCell>
                                </TableRow>
                            )}

                            {employees.data.map((employee) => (
                                <TableRow key={employee.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <UserAvatar name={employee.user.name} className="size-8" />
                                            <div className="min-w-0">
                                                <Link href={route('admin.employees.show', employee.id)} className="font-medium hover:underline">
                                                    {employee.user.name}
                                                </Link>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {employee.user.email} · <span className="font-mono">{employee.employee_code}</span>
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {employee.user.role ? (
                                            <Badge variant={employee.user.role.is_super ? 'default' : 'outline'}>{employee.user.role.name}</Badge>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground hidden lg:table-cell">
                                        {employee.department?.name ?? '—'}
                                        {employee.designation && <span className="block text-xs">{employee.designation.name}</span>}
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        <Badge variant={employee.status === 'active' ? 'secondary' : 'outline'}>
                                            {statusLabels[employee.status] ?? employee.status}
                                        </Badge>
                                        <span className="text-muted-foreground block text-xs">
                                            {employmentTypeLabels[employee.employment_type] ?? employee.employment_type}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={
                                                employee.user.is_active
                                                    ? 'text-xs font-medium text-emerald-700 dark:text-emerald-400'
                                                    : 'text-destructive text-xs font-medium'
                                            }
                                        >
                                            {employee.user.is_active ? 'Can sign in' : 'Deactivated'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="hidden sm:table-cell">
                                        {employee.onboarding ? (
                                            <Link
                                                href={route('admin.employees.onboarding', employee.id)}
                                                className="block min-w-28 space-y-1 hover:underline"
                                            >
                                                <OnboardingBadge status={employee.onboarding.status} />
                                                {employee.onboarding.status !== 'completed' && (
                                                    <span className="text-muted-foreground block text-[11px] tabular-nums">
                                                        {employee.onboarding.done}/{employee.onboarding.total} items
                                                    </span>
                                                )}
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            {archived ? (
                                                <>
                                                    {employee.can_toggle_access && (
                                                        <ArchiveButton employeeId={employee.id} name={employee.user.name} archived />
                                                    )}
                                                    {/* Refused by the server for anyone with work history — only a mistaken entry goes for good. */}
                                                    {can('employees.delete') && employee.can_toggle_access && (
                                                        <DeleteButton
                                                            url={route('admin.employees.destroy', employee.id)}
                                                            label={employee.user.name}
                                                            description="Only for someone added by mistake: their login, HR record and documents are removed permanently. Anyone with tasks, bugs or project history cannot be deleted — they stay archived."
                                                        />
                                                    )}
                                                </>
                                            ) : (
                                                <>
                                                    {employee.can_toggle_access && (
                                                        <AccessToggle
                                                            employeeId={employee.id}
                                                            name={employee.user.name}
                                                            active={employee.user.is_active}
                                                        />
                                                    )}
                                                    {can('employees.edit') && (
                                                        <Button asChild variant="ghost" size="sm">
                                                            <Link href={route('admin.employees.edit', employee.id)}>
                                                                <Pencil className="size-4" />
                                                                <span className="sr-only">Edit</span>
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {employee.can_toggle_access && (
                                                        <ArchiveButton employeeId={employee.id} name={employee.user.name} archived={false} compact />
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={employees} />
            </div>
        </AppLayout>
    );
}
