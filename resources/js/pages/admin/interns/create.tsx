import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import EmployeeForm, { NONE } from '@/pages/admin/employees/employee-form';
import type { BreadcrumbItem, Department, Designation } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Interns', href: '/admin/interns' },
    { title: 'New intern', href: '/admin/interns/create' },
];

export default function CreateIntern({
    departments,
    designations,
    nextCode,
}: {
    departments: Department[];
    designations: Designation[];
    nextCode: string;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New intern" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    icon={SECTIONS.interns.icon}
                    tone={SECTIONS.interns.tone}
                    back={route('admin.interns.index')}
                    title="New intern"
                    description="Creates their login and record, with or without a stipend, and invites them to complete onboarding."
                />

                <EmployeeForm
                    creating
                    intern
                    cancelHref={route('admin.interns.index')}
                    departments={departments}
                    designations={designations}
                    initial={{
                        name: '',
                        email: '',
                        role_id: '',
                        send_invite: true,
                        offer_letter_mode: 'none',
                        offer_letter: null,
                        employee_code: nextCode,
                        department_id: NONE,
                        designation_id: NONE,
                        phone: '',
                        date_of_birth: '',
                        gender: NONE,
                        date_of_joining: '',
                        employment_type: 'intern',
                        salary: '',
                        has_stipend: false,
                        stipend: '',
                        address: '',
                        status: 'active',
                    }}
                    action={{ url: route('admin.interns.store'), method: 'post' }}
                    submitLabel="Add intern"
                />
            </div>
        </AppLayout>
    );
}
