import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, Department, Designation, Employee } from '@/types';
import { Head } from '@inertiajs/react';
import EmployeeForm, { NONE } from './employee-form';

interface Props {
    employee: Employee & { name: string; email: string; role_id: string };
    departments: Department[];
    designations: Designation[];
    /** Empty when the viewer may not change access; the role is then shown, not edited. */
    roles: { value: string; label: string }[];
    roleName: string | null;
}

export default function EditEmployee({ employee, departments, designations, roles, roleName }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/admin/employees' },
        { title: employee.name, href: route('admin.employees.show', employee.id) },
        { title: 'Edit', href: `/admin/employees/${employee.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${employee.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    icon={SECTIONS.employees.icon}
                    tone={SECTIONS.employees.tone}
                    back={route('admin.employees.show', employee.id)}
                    title={`Edit ${employee.name}`}
                    description={employee.employee_code}
                />

                <EmployeeForm
                    departments={departments}
                    designations={designations}
                    roles={roles}
                    roleName={roleName}
                    initial={{
                        name: employee.name,
                        email: employee.email,
                        role_id: employee.role_id,
                        send_invite: false,
                        offer_letter_mode: 'none',
                        offer_letter: null,
                        employee_code: employee.employee_code,
                        department_id: employee.department_id ? String(employee.department_id) : NONE,
                        designation_id: employee.designation_id ? String(employee.designation_id) : NONE,
                        phone: employee.phone ?? '',
                        date_of_birth: employee.date_of_birth ?? '',
                        gender: employee.gender ?? NONE,
                        date_of_joining: employee.date_of_joining ?? '',
                        employment_type: employee.employment_type,
                        salary: employee.salary ?? '',
                        address: employee.address ?? '',
                        status: employee.status,
                    }}
                    action={{ url: route('admin.employees.update', employee.id), method: 'put' }}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
