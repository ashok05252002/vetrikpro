import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department } from '@/types';
import { Head } from '@inertiajs/react';
import DepartmentForm from './department-form';

export default function EditDepartment({ department }: { department: Department }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Departments', href: '/admin/departments' },
        { title: department.name, href: `/admin/departments/${department.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${department.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Edit department" />

                <DepartmentForm
                    initial={{ name: department.name, code: department.code ?? '', description: department.description ?? '' }}
                    action={{ url: route('admin.departments.update', department.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
