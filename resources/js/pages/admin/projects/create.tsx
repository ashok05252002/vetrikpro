import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option, User } from '@/types';
import { Head } from '@inertiajs/react';
import ProjectForm, { NO_OWNER } from './project-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manage projects', href: '/admin/projects' },
    { title: 'New project', href: '/admin/projects/create' },
];

interface Props {
    users: Pick<User, 'id' | 'name' | 'email'>[];
    statuses: Option[];
}

export default function CreateProject({ users, statuses }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New project" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader back={route('admin.projects.index')} title="New project" description="Set it up, then add tasks on the board." />

                <ProjectForm
                    users={users}
                    statuses={statuses}
                    initial={{
                        name: '',
                        code: '',
                        description: '',
                        status: 'active',
                        owner_id: NO_OWNER,
                        start_date: '',
                        due_date: '',
                        members: [],
                    }}
                    action={{ url: route('admin.projects.store'), method: 'post' }}
                    submitLabel="Create project"
                />
            </div>
        </AppLayout>
    );
}
