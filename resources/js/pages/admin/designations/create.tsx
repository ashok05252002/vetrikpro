import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, Department } from '@/types';
import { Head } from '@inertiajs/react';
import DesignationForm, { NONE } from './designation-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Designations', href: '/admin/designations' },
    { title: 'New designation', href: '/admin/designations/create' },
];

export default function CreateDesignation({ departments }: { departments: Department[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New designation" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    icon={SECTIONS.designations.icon}
                    tone={SECTIONS.designations.tone}
                    back={route('admin.designations.index')}
                    title="New designation"
                />

                <DesignationForm
                    departments={departments}
                    initial={{ department_id: NONE, name: '', description: '' }}
                    action={{ url: route('admin.designations.store'), method: 'post' }}
                    submitLabel="Create designation"
                />
            </div>
        </AppLayout>
    );
}
