import PageHeader from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Option, ProjectSummary, User } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import ProjectForm, { NO_OWNER } from './project-form';

interface Props {
    project: ProjectSummary & {
        description: string | null;
        owner_id: number | null;
        owner: Pick<User, 'id' | 'name' | 'email'> | null;
        repository_url: string | null;
        default_branch: string;
        members_count: number;
    };
    statuses: Option[];
}

export default function EditProject({ project, statuses }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Manage projects', href: '/admin/projects' },
        { title: project.name, href: `/admin/projects/${project.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${project.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    back={route('admin.projects.index')}
                    title="Edit project"
                    description={project.code}
                    action={
                        <Button asChild variant="outline">
                            <Link href={route('projects.members.index', project.id)}>
                                <Users className="size-4" /> Members ({project.members_count})
                            </Link>
                        </Button>
                    }
                />

                <ProjectForm
                    owner={project.owner}
                    statuses={statuses}
                    initial={{
                        name: project.name,
                        code: project.code,
                        description: project.description ?? '',
                        status: project.status ?? 'active',
                        owner_id: project.owner_id ? String(project.owner_id) : NO_OWNER,
                        start_date: project.start_date ?? '',
                        due_date: project.due_date ?? '',
                        repository_url: project.repository_url ?? '',
                        default_branch: project.default_branch,
                    }}
                    action={{ url: route('admin.projects.update', project.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
