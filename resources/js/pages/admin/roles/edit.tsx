import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PermissionGroup, Role } from '@/types';
import { Head } from '@inertiajs/react';
import RoleForm from './role-form';

interface Props {
    role: Role & { permissions: string[]; users_count: number };
    permissionGroups: PermissionGroup[];
}

export default function EditRole({ role, permissionGroups }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Roles & access', href: '/admin/roles' },
        { title: role.name, href: `/admin/roles/${role.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${role.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    back={route('admin.roles.index')}
                    title={`Edit ${role.name}`}
                    description={`${role.users_count} ${role.users_count === 1 ? 'person has' : 'people have'} this role. Changes apply to them at once.`}
                />

                <RoleForm
                    groups={permissionGroups}
                    superRole={role.is_super}
                    initial={{ name: role.name, description: role.description ?? '', permissions: role.permissions }}
                    action={{ url: route('admin.roles.update', role.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
