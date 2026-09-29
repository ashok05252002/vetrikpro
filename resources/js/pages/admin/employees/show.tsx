import PromoteDialog from '@/components/admin/promote-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import CardHeading from '@/components/ui/card-heading';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import type { Department, Designation, Employee, EmployeeProfileHeader, User } from '@/types';
import { Briefcase, Download, Mail, TrendingUp, UserRound } from 'lucide-react';
import { employmentTypeLabels, genderLabels, statusLabels } from './labels';

function Field({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="space-y-1">
            <dt className="text-muted-foreground text-xs tracking-wide uppercase">{label}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

interface PromotionRow {
    id: number;
    is_promotion: boolean;
    from_designation_name: string | null;
    to_designation_name: string;
    from_salary: string | null;
    to_salary: string;
    increment_percent: number | null;
    effective_date: string;
    note: string | null;
    emailed_at: string | null;
    created_at: string;
    creator: Pick<User, 'id' | 'name'> | null;
    letter_url: string | null;
}

/** Promotions and revisions, newest first, as a short timeline. */
function CareerHistory({ promotions }: { promotions: PromotionRow[] }) {
    const format = useFormat();

    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardHeading icon={TrendingUp} tone="green">
                    Promotions & salary revisions
                </CardHeading>
            </CardHeader>
            <CardContent>
                {promotions.length === 0 ? (
                    <p className="text-muted-foreground text-sm">None yet.</p>
                ) : (
                    <ol className="space-y-4">
                        {promotions.map((p) => (
                            <li key={p.id} className="flex gap-3">
                                <span className="bg-primary/10 text-primary mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full">
                                    <TrendingUp className="size-4" />
                                </span>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <p className="text-sm">
                                        <span className="font-medium">
                                            {p.is_promotion ? `Promoted to ${p.to_designation_name}` : 'Salary revised'}
                                        </span>
                                        {p.is_promotion && p.from_designation_name && (
                                            <span className="text-muted-foreground"> from {p.from_designation_name}</span>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {p.from_salary !== null ? `${format.money(p.from_salary)} → ` : ''}
                                        <span className="text-foreground font-medium">{format.money(p.to_salary)}</span> a month
                                        {p.increment_percent !== null &&
                                            p.increment_percent !== 0 &&
                                            ` (${p.increment_percent > 0 ? '+' : ''}${p.increment_percent}%)`}
                                        {' · '}effective {format.date(p.effective_date)}
                                        {p.creator && ` · by ${p.creator.name}`}
                                    </p>
                                    {p.note && <p className="text-muted-foreground text-xs italic">{p.note}</p>}
                                    <div className="flex flex-wrap items-center gap-3 pt-1">
                                        {p.letter_url && (
                                            <Button asChild variant="outline" size="sm" className="h-7 text-xs">
                                                <a href={p.letter_url}>
                                                    <Download className="size-3.5" /> Letter
                                                </a>
                                            </Button>
                                        )}
                                        <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                            <Mail className="size-3.5" style={{ color: p.emailed_at ? 'var(--tone-blue)' : undefined }} />
                                            {p.emailed_at ? `Emailed ${format.date(p.emailed_at)}` : 'Not emailed'}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

interface Props {
    employee: Employee;
    profile: EmployeeProfileHeader;
    promotions: PromotionRow[];
    promoteOptions: { departments: Pick<Department, 'id' | 'name'>[]; designations: Pick<Designation, 'id' | 'name' | 'department_id'>[] } | null;
}

export default function ShowEmployee({ employee, profile, promotions, promoteOptions }: Props) {
    const format = useFormat();

    return (
        <EmployeeProfileLayout
            employee={profile}
            tab="overview"
            actions={
                promoteOptions && (
                    <PromoteDialog
                        employee={{
                            id: employee.id,
                            name: profile.name,
                            email: profile.email,
                            designation_id: employee.designation_id,
                            department_id: employee.department_id,
                            salary: employee.salary,
                        }}
                        departments={promoteOptions.departments}
                        designations={promoteOptions.designations}
                    />
                )
            }
        >
            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardHeading icon={Briefcase} tone="teal">
                            Employment
                        </CardHeading>
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
                            {employee.employment_type === 'intern' ? (
                                <Field label="Monthly stipend" value={employee.has_stipend ? format.money(employee.stipend) : 'Unpaid internship'} />
                            ) : (
                                <Field label="Monthly salary" value={format.money(employee.salary)} />
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardHeading icon={UserRound} tone="teal">
                            Personal
                        </CardHeading>
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

                <CareerHistory promotions={promotions} />
            </div>
        </EmployeeProfileLayout>
    );
}
