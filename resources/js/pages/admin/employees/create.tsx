import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Designation, Option } from '@/types';
import { Head } from '@inertiajs/react';
import EmployeeForm, { NONE } from './employee-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
    { title: 'New employee', href: '/admin/employees/create' },
];

interface Props {
    departments: Department[];
    designations: Designation[];
    roles: Option[];
    defaultRoleId: string;
    nextCode: string;
}

export default function CreateEmployee({ departments, designations, roles, defaultRoleId, nextCode }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New employee" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    back={route('admin.employees.index')}
                    title="New employee"
                    description="Creates their login and HR record together, and invites them to complete their profile."
                />

                <EmployeeForm
                    creating
                    roles={roles}
                    departments={departments}
                    designations={designations}
                    initial={{
                        name: '',
                        email: '',
                        role_id: roles.some((r) => r.value === defaultRoleId) ? defaultRoleId : (roles[0]?.value ?? ''),
                        send_invite: true,
                        offer_letter_mode: 'generate',
                        offer_letter: null,
                        employee_code: nextCode,
                        department_id: NONE,
                        designation_id: NONE,
                        phone: '',
                        date_of_birth: '',
                        gender: NONE,
                        date_of_joining: '',
                        employment_type: 'full_time',
                        salary: '',
                        address: '',
                        status: 'active',
                    }}
                    action={{ url: route('admin.employees.store'), method: 'post' }}
                    submitLabel="Create employee"
                />
            </div>
        </AppLayout>
    );
}
