import DeleteButton from '@/components/admin/delete-button';
import FilterBar from '@/components/admin/filter-bar';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option, Paginated, User } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
];

const statusOptions: Option[] = [
    { value: 'active', label: 'Active' },
    { value: 'disabled', label: 'Disabled' },
];

interface Props {
    users: Paginated<User>;
    roles: Option[];
    filters: { search?: string; role?: string; status?: string };
}

export default function UsersIndex({ users, roles, filters }: Props) {
    const { can } = usePermission();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Users"
                    description="Login accounts and their roles. Create an account here before giving someone an employee profile."
                    action={
                        can('users.create') && (
                            <Button asChild>
                                <Link href={route('admin.users.create')}>
                                    <Plus className="size-4" /> New user
                                </Link>
                            </Button>
                        )
                    }
                />

                <FilterBar
                    url={route('admin.users.index')}
                    filters={filters}
                    searchPlaceholder="Search name or email…"
                    selects={[
                        { name: 'role', placeholder: 'All roles', options: roles },
                        { name: 'status', placeholder: 'Any status', options: statusOptions },
                    ]}
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Employee</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            )}

                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">
                                        {user.employee ? (
                                            <Link href={route('admin.employees.show', user.employee.id)} className="hover:underline">
                                                {user.name}
                                            </Link>
                                        ) : (
                                            user.name
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{user.email}</TableCell>
                                    <TableCell>
                                        {user.role ? <Badge variant={user.role.is_super ? 'default' : 'outline'}>{user.role.name}</Badge> : '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{user.employee?.employee_code ?? '—'}</TableCell>
                                    <TableCell>
                                        <span className={user.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'}>
                                            {user.is_active ? 'Active' : 'Disabled'}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            {can('users.edit') && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={route('admin.users.edit', user.id)}>
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">Edit</span>
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('users.delete') && (
                                                <DeleteButton
                                                    url={route('admin.users.destroy', user.id)}
                                                    label={user.name}
                                                    description="The account and any linked employee profile will be removed permanently."
                                                />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={users} />
            </div>
        </AppLayout>
    );
}
