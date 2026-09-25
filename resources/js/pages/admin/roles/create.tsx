import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PermissionGroup } from '@/types';
import { Head } from '@inertiajs/react';
import RoleForm from './role-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles & access', href: '/admin/roles' },
    { title: 'New role', href: '/admin/roles/create' },
];

export default function CreateRole({ permissionGroups }: { permissionGroups: PermissionGroup[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New role" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader back={route('admin.roles.index')} title="New role" description="A named set of permissions you can give to people." />

                <RoleForm
                    groups={permissionGroups}
                    initial={{ name: '', description: '', permissions: [] }}
                    action={{ url: route('admin.roles.store'), method: 'post' }}
                    submitLabel="Create role"
                />
            </div>
        </AppLayout>
    );
}
