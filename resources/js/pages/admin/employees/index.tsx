import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import OnboardingBadge from '@/components/onboarding/onboarding-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Employee, OnboardingStatus, Option, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';
import { employmentTypeLabels, statusLabels } from './labels';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
];

interface Props {
    employees: Paginated<Employee & { onboarding: { status: OnboardingStatus; done: number; total: number } | null }>;
    departments: Department[];
    onboardingStatuses: Option[];
    filters: { search?: string; department?: string; onboarding?: string };
}

export default function EmployeesIndex({ employees, departments, onboardingStatuses, filters }: Props) {
    const { can } = usePermission();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employees" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Employees"
                    description="HR records attached to user accounts."
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

                <FilterBar
                    url={route('admin.employees.index')}
                    filters={filters}
                    searchPlaceholder="Search name, email or code…"
                    selects={[
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
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Designation</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Onboarding</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-muted-foreground py-10 text-center">
                                        No employee profiles yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {employees.data.map((employee) => (
                                <TableRow key={employee.id}>
                                    <TableCell className="font-mono text-xs">{employee.employee_code}</TableCell>
                                    <TableCell>
                                        <div className="font-medium">{employee.user?.name}</div>
                                        <div className="text-muted-foreground text-xs">{employee.user?.email}</div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{employee.department?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">{employee.designation?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {employmentTypeLabels[employee.employment_type] ?? employee.employment_type}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={employee.status === 'active' ? 'default' : 'secondary'}>
                                            {statusLabels[employee.status] ?? employee.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
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
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={route('admin.employees.show', employee.id)}>
                                                    <Eye className="size-4" />
                                                    <span className="sr-only">View</span>
                                                </Link>
                                            </Button>
                                            {can('employees.edit') && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={route('admin.employees.edit', employee.id)}>
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('employees.delete') && (
                                                <DeleteButton
                                                    url={route('admin.employees.destroy', employee.id)}
                                                    label={employee.user?.name ?? employee.employee_code}
                                                    description="The HR record is removed. The login account is kept."
                                                />
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
