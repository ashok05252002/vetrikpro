import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Designation, Employee, User } from '@/types';
import { Head } from '@inertiajs/react';
import EmployeeForm, { NONE } from './employee-form';

interface Props {
    employee: Employee;
    users: Pick<User, 'id' | 'name' | 'email'>[];
    departments: Department[];
    designations: Designation[];
}

export default function EditEmployee({ employee, users, departments, designations }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/admin/employees' },
        { title: employee.employee_code, href: `/admin/employees/${employee.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${employee.employee_code}`} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Edit employee" description={employee.employee_code} />

                <EmployeeForm
                    users={users}
                    departments={departments}
                    designations={designations}
                    initial={{
                        user_id: String(employee.user_id),
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
