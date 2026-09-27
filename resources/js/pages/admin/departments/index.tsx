import ActiveToggle, { InactiveBadge } from '@/components/admin/active-toggle';
import DeleteButton from '@/components/admin/delete-button';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import SearchFilter from '@/components/admin/search-filter';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/admin/departments' },
];

export default function DepartmentsIndex({ departments, filters }: { departments: Paginated<Department>; filters: { search?: string } }) {
    const { can } = usePermission();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departments" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Departments"
                    description="The organisational units employees belong to. One in use can be marked inactive, not deleted."
                    action={
                        can('departments.create') && (
                            <Button asChild>
                                <Link href={route('admin.departments.create')}>
                                    <Plus className="size-4" /> New department
                                </Link>
                            </Button>
                        )
                    }
                />

                <SearchFilter url={route('admin.departments.index')} initial={filters.search ?? ''} placeholder="Search departments…" />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Designations</TableHead>
                                <TableHead>Employees</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {departments.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                        No departments yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {departments.data.map((department) => (
                                <TableRow key={department.id}>
                                    <TableCell className={department.is_active ? 'font-medium' : 'text-muted-foreground font-medium'}>
                                        {department.name}
                                        <InactiveBadge active={department.is_active} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{department.code ?? '—'}</TableCell>
                                    <TableCell>{department.designations_count ?? 0}</TableCell>
                                    <TableCell>{department.employees_count ?? 0}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            {can('departments.edit') && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={route('admin.departments.edit', department.id)}>
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('departments.edit') && (
                                                <ActiveToggle url={route('admin.departments.active', department.id)} active={department.is_active} />
                                            )}
                                            {/* In use, it can only be switched off: deleting would strip it from every record. */}
                                            {can('departments.delete') && !department.in_use && (
                                                <DeleteButton url={route('admin.departments.destroy', department.id)} label={department.name} />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={departments} />
            </div>
        </AppLayout>
    );
}
