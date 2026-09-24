import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Designation } from '@/types';
import { Head } from '@inertiajs/react';
import DesignationForm, { NONE } from './designation-form';

interface Props {
    designation: Designation;
    departments: Department[];
}

export default function EditDesignation({ designation, departments }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Designations', href: '/admin/designations' },
        { title: designation.name, href: `/admin/designations/${designation.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${designation.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Edit designation" />

                <DesignationForm
                    departments={departments}
                    initial={{
                        department_id: designation.department_id ? String(designation.department_id) : NONE,
                        name: designation.name,
                        description: designation.description ?? '',
                    }}
                    action={{ url: route('admin.designations.update', designation.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
