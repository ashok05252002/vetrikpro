import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import EmployeeForm, { NONE } from '@/pages/admin/employees/employee-form';
import type { BreadcrumbItem, Department, Designation } from '@/types';
import { Head } from '@inertiajs/react';

interface Intern {
    id: number;
    name: string;
    email: string;
    department_id: number | null;
    designation_id: number | null;
    employee_code: string;
    phone: string | null;
    date_of_birth: string | null;
    gender: string | null;
    date_of_joining: string | null;
    has_stipend: boolean;
    stipend: string | null;
    address: string | null;
    status: string;
}

export default function EditIntern({
    intern,
    departments,
    designations,
    canSetPay,
}: {
    intern: Intern;
    departments: Department[];
    designations: Designation[];
    canSetPay: boolean;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Interns', href: '/admin/interns' },
        { title: intern.name, href: route('admin.interns.edit', intern.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${intern.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    icon={SECTIONS.interns.icon}
                    tone={SECTIONS.interns.tone}
                    back={route('admin.interns.index')}
                    title={`Edit ${intern.name}`}
                />

                <EmployeeForm
                    intern
                    canSetPay={canSetPay}
                    cancelHref={route('admin.interns.index')}
                    departments={departments}
                    designations={designations}
                    initial={{
                        name: intern.name,
                        email: intern.email,
                        role_id: '',
                        send_invite: false,
                        offer_letter_mode: 'none',
                        offer_letter: null,
                        employee_code: intern.employee_code,
                        department_id: intern.department_id ? String(intern.department_id) : NONE,
                        designation_id: intern.designation_id ? String(intern.designation_id) : NONE,
                        phone: intern.phone ?? '',
                        date_of_birth: intern.date_of_birth ?? '',
                        gender: intern.gender ?? NONE,
                        date_of_joining: intern.date_of_joining ?? '',
                        employment_type: 'intern',
                        salary: '',
                        has_stipend: intern.has_stipend,
                        stipend: intern.stipend ?? '',
                        address: intern.address ?? '',
                        status: intern.status,
                    }}
                    action={{ url: route('admin.interns.update', intern.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
