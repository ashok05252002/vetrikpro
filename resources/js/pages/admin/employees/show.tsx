import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import type { Employee, EmployeeProfileHeader } from '@/types';
import { employmentTypeLabels, genderLabels, statusLabels } from './labels';

function Field({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="space-y-1">
            <dt className="text-muted-foreground text-xs tracking-wide uppercase">{label}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

export default function ShowEmployee({ employee, profile }: { employee: Employee; profile: EmployeeProfileHeader }) {
    const format = useFormat();

    return (
        <EmployeeProfileLayout employee={profile} tab="overview">
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
        </EmployeeProfileLayout>
    );
}
