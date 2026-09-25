import DeleteButton from '@/components/admin/delete-button';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import SearchFilter from '@/components/admin/search-filter';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Designation, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Designations', href: '/admin/designations' },
];

export default function DesignationsIndex({ designations, filters }: { designations: Paginated<Designation>; filters: { search?: string } }) {
    const { can } = usePermission();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Designations" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Designations"
                    description="Job titles, optionally scoped to a department."
                    action={
                        can('designations.create') && (
                            <Button asChild>
                                <Link href={route('admin.designations.create')}>
                                    <Plus className="size-4" /> New designation
                                </Link>
                            </Button>
                        )
                    }
                />

                <SearchFilter url={route('admin.designations.index')} initial={filters.search ?? ''} placeholder="Search designations…" />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Employees</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {designations.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground py-10 text-center">
                                        No designations yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {designations.data.map((designation) => (
                                <TableRow key={designation.id}>
                                    <TableCell className="font-medium">{designation.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{designation.department?.name ?? '—'}</TableCell>
                                    <TableCell>{designation.employees_count ?? 0}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            {can('designations.edit') && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={route('admin.designations.edit', designation.id)}>
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('designations.delete') && (
                                                <DeleteButton url={route('admin.designations.destroy', designation.id)} label={designation.name} />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={designations} />
            </div>
        </AppLayout>
    );
}
