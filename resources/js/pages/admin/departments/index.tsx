import DeleteButton from '@/components/admin/delete-button';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import SearchFilter from '@/components/admin/search-filter';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/admin/departments' },
];

export default function DepartmentsIndex({ departments, filters }: { departments: Paginated<Department>; filters: { search?: string } }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departments" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Departments"
                    description="The organisational units employees belong to."
                    action={
                        <Button asChild>
                            <Link href={route('admin.departments.create')}>
                                <Plus className="size-4" /> New department
                            </Link>
                        </Button>
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
                                    <TableCell className="font-medium">{department.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{department.code ?? '—'}</TableCell>
                                    <TableCell>{department.designations_count ?? 0}</TableCell>
                                    <TableCell>{department.employees_count ?? 0}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={route('admin.departments.edit', department.id)}>
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Link>
                                            </Button>
                                            <DeleteButton
                                                url={route('admin.departments.destroy', department.id)}
                                                label={department.name}
                                                description="Employees and designations in this department will keep their records but lose the department link."
                                            />
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
