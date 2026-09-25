import PageHeader from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Employee } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { employmentTypeLabels, genderLabels, statusLabels } from './labels';

function Field({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="space-y-1">
            <dt className="text-muted-foreground text-xs tracking-wide uppercase">{label}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

export default function ShowEmployee({ employee }: { employee: Employee }) {
    const format = useFormat();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/admin/employees' },
        { title: employee.employee_code, href: `/admin/employees/${employee.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={employee.user?.name ?? employee.employee_code} />

            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    back={route('admin.employees.index')}
                    title={employee.user?.name ?? employee.employee_code}
                    description={employee.employee_code}
                    action={
                        <Button asChild variant="outline">
                            <Link href={route('admin.employees.edit', employee.id)}>
                                <Pencil className="size-4" /> Edit
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Employment</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4">
                                <Field label="Department" value={employee.department?.name} />
                                <Field label="Designation" value={employee.designation?.name} />
                                <Field label="Employment type" value={employmentTypeLabels[employee.employment_type]} />
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground text-xs tracking-wide uppercase">Status</dt>
                                    <dd>
                                        <Badge variant={employee.status === 'active' ? 'default' : 'secondary'}>
                                            {statusLabels[employee.status] ?? employee.status}
                                        </Badge>
                                    </dd>
                                </div>
                                <Field label="Date of joining" value={format.date(employee.date_of_joining)} />
                                <Field label="Monthly salary" value={format.money(employee.salary)} />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Personal</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4">
                                <Field label="Email" value={employee.user?.email} />
                                <Field label="Phone" value={employee.phone} />
                                <Field label="Date of birth" value={format.date(employee.date_of_birth)} />
                                <Field label="Gender" value={employee.gender ? genderLabels[employee.gender] : null} />
                                <div className="col-span-2">
                                    <Field label="Address" value={employee.address} />
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
