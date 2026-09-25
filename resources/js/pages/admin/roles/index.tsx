import DeleteButton from '@/components/admin/delete-button';
import PageHeader from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Role } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles & access', href: '/admin/roles' },
];

export default function RolesIndex({ roles, totalPermissions }: { roles: Role[]; totalPermissions: number }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles & access" />

            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title="Roles & access"
                    description="Each role is a set of permissions. Pick one for each user, then fine-tune individuals on their user form."
                    action={
                        <Button asChild>
                            <Link href={route('admin.roles.create')}>
                                <Plus className="size-4" /> New role
                            </Link>
                        </Button>
                    }
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead>People</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-2 font-medium">
                                            {role.name}
                                            {role.is_system && <Badge variant="outline">Built-in</Badge>}
                                        </div>
                                        {role.description && <p className="text-muted-foreground text-xs">{role.description}</p>}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground tabular-nums">
                                        {role.is_super ? 'All' : `${role.permissions_count} of ${totalPermissions}`}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        <Link href={route('admin.users.index', { role: role.slug })} className="hover:underline">
                                            {role.users_count}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={route('admin.roles.edit', role.id)}>
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Link>
                                            </Button>
                                            {!role.is_system && (
                                                <DeleteButton
                                                    url={route('admin.roles.destroy', role.id)}
                                                    label={role.name}
                                                    description="Only a role nobody holds can be deleted."
                                                />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
