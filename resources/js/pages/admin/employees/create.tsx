import PageHeader from '@/components/admin/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Department, Designation, User } from '@/types';
import { Head } from '@inertiajs/react';
import EmployeeForm, { NONE } from './employee-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
    { title: 'New employee', href: '/admin/employees/create' },
];

interface Props {
    users: Pick<User, 'id' | 'name' | 'email'>[];
    departments: Department[];
    designations: Designation[];
    nextCode: string;
}

export default function CreateEmployee({ users, departments, designations, nextCode }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New employee" />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="New employee" description="Attach an HR record to an existing user account." />

                {users.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Every user already has an employee profile. Create a user first under Users → New user.
                    </p>
                ) : (
                    <EmployeeForm
                        users={users}
                        departments={departments}
                        designations={designations}
                        initial={{
                            user_id: '',
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
                )}
            </div>
        </AppLayout>
    );
}
