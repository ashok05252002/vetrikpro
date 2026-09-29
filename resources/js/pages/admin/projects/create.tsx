import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, Option, User } from '@/types';
import { Head } from '@inertiajs/react';
import ProjectForm, { NO_OWNER } from './project-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manage projects', href: '/admin/projects' },
    { title: 'New project', href: '/admin/projects/create' },
];

export default function CreateProject({ statuses, eligibleLeads }: { statuses: Option[]; eligibleLeads: Pick<User, 'id' | 'name' | 'email'>[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New project" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    icon={SECTIONS.projects.icon}
                    tone={SECTIONS.projects.tone}
                    back={route('admin.projects.index')}
                    title="New project"
                    description="Set it up here; you’ll add its members next."
                />

                <ProjectForm
                    statuses={statuses}
                    eligibleLeads={eligibleLeads}
                    initial={{
                        name: '',
                        code: '',
                        description: '',
                        status: 'active',
                        owner_id: NO_OWNER,
                        start_date: '',
                        due_date: '',
                        repository_url: '',
                        default_branch: 'main',
                        lead_ids: [],
                    }}
                    action={{ url: route('admin.projects.store'), method: 'post' }}
                    submitLabel="Create project"
                />
            </div>
        </AppLayout>
    );
}
