import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option, ProjectSummary, User } from '@/types';
import { Head } from '@inertiajs/react';
import ProjectForm, { NO_OWNER } from './project-form';

interface Props {
    project: ProjectSummary & { description: string | null; owner_id: number | null; members: number[] };
    users: Pick<User, 'id' | 'name' | 'email'>[];
    statuses: Option[];
}

export default function EditProject({ project, users, statuses }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Manage projects', href: '/admin/projects' },
        { title: project.name, href: `/admin/projects/${project.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${project.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Edit project" description={project.code} />

                <ProjectForm
                    users={users}
                    statuses={statuses}
                    initial={{
                        name: project.name,
                        code: project.code,
                        description: project.description ?? '',
                        status: project.status ?? 'active',
                        owner_id: project.owner_id ? String(project.owner_id) : NO_OWNER,
                        start_date: project.start_date ?? '',
                        due_date: project.due_date ?? '',
                        members: project.members ?? [],
                    }}
                    action={{ url: route('admin.projects.update', project.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
