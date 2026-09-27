import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import DepartmentForm from './department-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/admin/departments' },
    { title: 'New department', href: '/admin/departments/create' },
];

export default function CreateDepartment() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New department" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    icon={SECTIONS.departments.icon}
                    tone={SECTIONS.departments.tone}
                    back={route('admin.departments.index')}
                    title="New department"
                />

                <DepartmentForm
                    initial={{ name: '', code: '', description: '' }}
                    action={{ url: route('admin.departments.store'), method: 'post' }}
                    submitLabel="Create department"
                />
            </div>
        </AppLayout>
    );
}
