import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option } from '@/types';
import { Head } from '@inertiajs/react';
import UserForm from './user-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
    { title: 'New user', href: '/admin/users/create' },
];

export default function CreateUser({ roles }: { roles: Option[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New user" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    back={route('admin.users.index')}
                    title="New user"
                    description="Create a login account. You can attach an employee profile afterwards."
                />

                <UserForm
                    roles={roles}
                    initial={{ name: '', email: '', role: 'employee', is_active: true, password: '', password_confirmation: '' }}
                    action={{ url: route('admin.users.store'), method: 'post' }}
                    submitLabel="Create user"
                />
            </div>
        </AppLayout>
    );
}
