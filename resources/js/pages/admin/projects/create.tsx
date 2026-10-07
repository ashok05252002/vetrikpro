import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, Option, User } from '@/types';
import { Head } from '@inertiajs/react';
import ProjectForm, { NO_OWNER } from './project-form';

// Without "see every project" the admin list is closed, so the way back is their own list.
const breadcrumbsFor = (joinsAsMember: boolean): BreadcrumbItem[] => [
    { title: 'Dashboard', href: '/dashboard' },
    joinsAsMember ? { title: 'Projects', href: '/projects' } : { title: 'Manage projects', href: '/admin/projects' },
    { title: 'New project', href: '/admin/projects/create' },
];

interface Props {
    statuses: Option[];
    eligibleLeads: Pick<User, 'id' | 'name' | 'email'>[];
    /** True when the creator can't see every project, so they join this one. */
    joinsAsMember: boolean;
}

export default function CreateProject({ statuses, eligibleLeads, joinsAsMember }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbsFor(joinsAsMember)}>
            <Head title="New project" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    icon={SECTIONS.projects.icon}
                    tone={SECTIONS.projects.tone}
                    back={route(joinsAsMember ? 'projects.index' : 'admin.projects.index')}
                    title="New project"
                    description="Set it up here; you’ll add its members next."
                />

                {joinsAsMember && (
                    <p className="text-muted-foreground max-w-3xl rounded-md border border-dashed px-3 py-2 text-sm">
                        You’ll be added to this project as a member. Pick yourself under Project leads to run it instead.
                    </p>
                )}

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
