import type { Overrides } from '@/components/access/access-editor';
import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, User } from '@/types';
import { Head } from '@inertiajs/react';
import UserForm, { type UserFormOptions } from './user-form';

interface Props extends UserFormOptions {
    user: Pick<User, 'id' | 'name' | 'email' | 'role_id' | 'is_active'> & { overrides: Overrides };
}

export default function EditUser({ user, ...options }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/admin/users' },
        { title: user.name, href: `/admin/users/${user.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader back={route('admin.users.index')} title="Edit user" description={user.email} />

                <UserForm
                    {...options}
                    initial={{
                        name: user.name,
                        email: user.email,
                        role_id: user.role_id,
                        is_active: user.is_active,
                        password: '',
                        password_confirmation: '',
                        // PHP serialises an empty map as [], which is not a record.
                        overrides: Array.isArray(user.overrides) ? {} : user.overrides,
                    }}
                    action={{ url: route('admin.users.update', user.id), method: 'put' }}
                    submitLabel="Save changes"
                    passwordOptional
                />
            </div>
        </AppLayout>
    );
}
