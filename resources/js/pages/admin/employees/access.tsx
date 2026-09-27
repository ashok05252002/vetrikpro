import AccessEditor, { type Overrides } from '@/components/access/access-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import type { EmployeeProfileHeader, PermissionGroup, Role } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    employee: EmployeeProfileHeader;
    access: { role_id: number | null; overrides: Overrides | []; effective: string[] };
    roles: Pick<Role, 'id' | 'name' | 'is_super' | 'permissions'>[];
    permissionGroups: PermissionGroup[];
}

type AccessForm = { role_id: number | null; overrides: Overrides };

export default function EmployeeAccess({ employee, access, roles, permissionGroups }: Props) {
    const { data, setData, put, processing, errors, isDirty } = useForm<AccessForm>({
        role_id: access.role_id,
        // PHP serialises an empty map as [], which is not a record.
        overrides: Array.isArray(access.overrides) ? {} : access.overrides,
    });
    const role = roles.find((r) => r.id === data.role_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.employees.access.update', employee.id), { preserveScroll: true });
    };

    return (
        <EmployeeProfileLayout employee={employee} tab="access">
            <form onSubmit={submit} className="max-w-6xl space-y-6">
                <p className="text-muted-foreground max-w-3xl text-sm">
                    What {employee.name} can do across the app. The role sets the baseline; allow or deny single permissions for this person only. It
                    currently comes to {access.effective.length} permission{access.effective.length === 1 ? '' : 's'}.
                </p>

                <div className="grid max-w-sm gap-2">
                    <Label htmlFor="role_id">Role</Label>
                    <Select value={data.role_id ? String(data.role_id) : undefined} onValueChange={(value) => setData('role_id', Number(value))}>
                        <SelectTrigger id="role_id">
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((r) => (
                                <SelectItem key={r.id} value={String(r.id)}>
                                    {r.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.role_id} />
                </div>

                {role && (
                    <AccessEditor
                        groups={permissionGroups}
                        inherited={role.permissions ?? []}
                        superRole={role.is_super}
                        value={data.overrides}
                        onChange={(next) => setData('overrides', next)}
                    />
                )}
                <InputError message={errors.overrides} />

                <div className="flex items-center gap-3">
                    <Button disabled={processing || !isDirty}>Save access</Button>
                    {isDirty && <span className="text-muted-foreground text-xs">Unsaved changes</span>}
                </div>
            </form>
        </EmployeeProfileLayout>
    );
}
