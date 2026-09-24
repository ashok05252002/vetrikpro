import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Department, Designation, User } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';
import { employmentTypeLabels, statusLabels } from './labels';

const NONE = 'none';

interface FormData {
    user_id: string;
    employee_code: string;
    department_id: string;
    designation_id: string;
    phone: string;
    date_of_birth: string;
    gender: string;
    date_of_joining: string;
    employment_type: string;
    salary: string;
    address: string;
    status: string;
    [key: string]: string;
}

interface Props {
    users: Pick<User, 'id' | 'name' | 'email'>[];
    departments: Department[];
    designations: Designation[];
    initial: FormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    lockUser?: boolean;
}

export default function EmployeeForm({ users, departments, designations, initial, action, submitLabel, lockUser = false }: Props) {
    const { data, setData, post, put, processing, errors, transform } = useForm<FormData>(initial);

    // Radix Select has no empty-string value, so optional relations ride as a
    // sentinel and are blanked on the way to the server.
    transform((payload) => ({
        ...payload,
        department_id: payload.department_id === NONE ? '' : payload.department_id,
        designation_id: payload.designation_id === NONE ? '' : payload.designation_id,
        gender: payload.gender === NONE ? '' : payload.gender,
    }));

    // Only show designations belonging to the chosen department (plus unscoped ones).
    const visibleDesignations = useMemo(() => {
        if (data.department_id === NONE) {
            return designations;
        }
        return designations.filter((d) => d.department_id === null || String(d.department_id) === data.department_id);
    }, [designations, data.department_id]);

    const onDepartmentChange = (value: string) => {
        setData((current) => ({
            ...current,
            department_id: value,
            // Drop a designation that no longer belongs to the selected department.
            designation_id: designations.some(
                (d) => String(d.id) === current.designation_id && (d.department_id === null || String(d.department_id) === value),
            )
                ? current.designation_id
                : NONE,
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-6 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="user_id">User account</Label>
                    <Select value={data.user_id} onValueChange={(value) => setData('user_id', value)} disabled={lockUser}>
                        <SelectTrigger id="user_id">
                            <SelectValue placeholder="Select a user" />
                        </SelectTrigger>
                        <SelectContent>
                            {users.map((user) => (
                                <SelectItem key={user.id} value={String(user.id)}>
                                    {user.name} · {user.email}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.user_id} />
                    {!lockUser && <p className="text-muted-foreground text-xs">Only users without a profile are listed.</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="employee_code">Employee code</Label>
                    <Input id="employee_code" value={data.employee_code} onChange={(e) => setData('employee_code', e.target.value)} required />
                    <InputError message={errors.employee_code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="department_id">Department</Label>
                    <Select value={data.department_id} onValueChange={onDepartmentChange}>
                        <SelectTrigger id="department_id">
                            <SelectValue placeholder="Select a department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>No department</SelectItem>
                            {departments.map((department) => (
                                <SelectItem key={department.id} value={String(department.id)}>
                                    {department.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.department_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="designation_id">Designation</Label>
                    <Select value={data.designation_id} onValueChange={(value) => setData('designation_id', value)}>
                        <SelectTrigger id="designation_id">
                            <SelectValue placeholder="Select a designation" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>No designation</SelectItem>
                            {visibleDesignations.map((designation) => (
                                <SelectItem key={designation.id} value={String(designation.id)}>
                                    {designation.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.designation_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+91 98765 43210" />
                    <InputError message={errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="gender">Gender</Label>
                    <Select value={data.gender} onValueChange={(value) => setData('gender', value)}>
                        <SelectTrigger id="gender">
                            <SelectValue placeholder="Not specified" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>Not specified</SelectItem>
                            <SelectItem value="male">Male</SelectItem>
                            <SelectItem value="female">Female</SelectItem>
                            <SelectItem value="other">Other</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="date_of_birth">Date of birth</Label>
                    <Input id="date_of_birth" type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} />
                    <InputError message={errors.date_of_birth} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="date_of_joining">Date of joining</Label>
                    <Input
                        id="date_of_joining"
                        type="date"
                        value={data.date_of_joining}
                        onChange={(e) => setData('date_of_joining', e.target.value)}
                    />
                    <InputError message={errors.date_of_joining} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="employment_type">Employment type</Label>
                    <Select value={data.employment_type} onValueChange={(value) => setData('employment_type', value)}>
                        <SelectTrigger id="employment_type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(employmentTypeLabels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.employment_type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger id="status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(statusLabels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="salary">Monthly salary</Label>
                    <Input id="salary" type="number" step="0.01" min="0" value={data.salary} onChange={(e) => setData('salary', e.target.value)} />
                    <InputError message={errors.salary} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="address">Address</Label>
                <Textarea id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                <InputError message={errors.address} />
            </div>

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={route('admin.employees.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}

export { NONE };
