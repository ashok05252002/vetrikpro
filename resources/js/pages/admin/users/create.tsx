import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import UserForm, { type UserFormOptions } from './user-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
    { title: 'New user', href: '/admin/users/create' },
];

export default function CreateUser(options: UserFormOptions) {
    // Default to the least-privileged role on offer, never to none.
    const fallback = options.roles.find((r) => !r.is_super && (r.permissions?.length ?? 0) === 0) ?? options.roles[0];

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
                    {...options}
                    initial={{
                        name: '',
                        email: '',
                        role_id: fallback?.id ?? null,
                        is_active: true,
                        password: '',
                        password_confirmation: '',
                        overrides: {},
                    }}
                    action={{ url: route('admin.users.store'), method: 'post' }}
                    submitLabel="Create user"
                />
            </div>
        </AppLayout>
    );
}
