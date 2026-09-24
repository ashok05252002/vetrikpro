import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option, User } from '@/types';
import { Head } from '@inertiajs/react';
import UserForm from './user-form';

interface Props {
    user: Pick<User, 'id' | 'name' | 'email' | 'role' | 'is_active'>;
    roles: Option[];
}

export default function EditUser({ user, roles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/admin/users' },
        { title: user.name, href: `/admin/users/${user.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Edit user" description={user.email} />

                <UserForm
                    roles={roles}
                    initial={{
                        name: user.name,
                        email: user.email,
                        role: user.role,
                        is_active: user.is_active,
                        password: '',
                        password_confirmation: '',
                    }}
                    action={{ url: route('admin.users.update', user.id), method: 'put' }}
                    submitLabel="Save changes"
                    passwordOptional
                />
            </div>
        </AppLayout>
    );
}
