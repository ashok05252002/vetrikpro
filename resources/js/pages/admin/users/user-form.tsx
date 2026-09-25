import AccessEditor, { type Overrides } from '@/components/access/access-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PermissionGroup, Role } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

// A type alias, not an interface: useForm needs an implicit index signature.
export type UserFormData = {
    name: string;
    email: string;
    role_id: number | null;
    is_active: boolean;
    password: string;
    password_confirmation: string;
    overrides: Overrides;
};

export interface UserFormOptions {
    /** Only the roles the signed-in user may assign. */
    roles: Pick<Role, 'id' | 'name' | 'is_super' | 'permissions'>[];
    permissionGroups: PermissionGroup[];
    canManageAccess: boolean;
}

interface Props extends UserFormOptions {
    initial: UserFormData;
    /** Edit forms PUT to an existing record; create forms POST. */
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    passwordOptional?: boolean;
}

export default function UserForm({ roles, permissionGroups, canManageAccess, initial, action, submitLabel, passwordOptional = false }: Props) {
    const { data, setData, transform, post, put, processing, errors } = useForm<UserFormData>(initial);
    const role = roles.find((r) => r.id === data.role_id);

    // Without roles.manage the Access section is hidden, and the field is left
    // out entirely so the server keeps whatever overrides already exist.
    transform((form) => {
        if (canManageAccess) {
            return form;
        }

        const { overrides: _omit, ...rest } = form;
        void _omit;

        return rest as UserFormData;
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const submitter = action.method === 'put' ? put : post;
        submitter(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-8">
            <section className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="name">Full name</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus placeholder="Jane Doe" />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="off"
                        placeholder="jane@company.com"
                    />
                    <InputError message={errors.email} />
                    <p className="text-muted-foreground text-xs">This is the address they sign in with. It must be unique.</p>
                </div>

                <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password {passwordOptional && <span className="text-muted-foreground">(optional)</span>}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            required={!passwordOptional}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                            required={!passwordOptional}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>

                {passwordOptional && <p className="text-muted-foreground -mt-3 text-xs">Leave both fields blank to keep the current password.</p>}

                <div className="flex items-center gap-2">
                    <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                    <Label htmlFor="is_active" className="font-normal">
                        Account is active
                    </Label>
                </div>
            </section>

            <section className="space-y-4 border-t pt-6">
                <div className="space-y-1">
                    <h2 className="text-base font-semibold">Role & access</h2>
                    <p className="text-muted-foreground text-sm">
                        The role sets what this person can do.
                        {canManageAccess && ' Below it you can allow or deny individual permissions for this person only.'}
                    </p>
                </div>

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

                {canManageAccess && role && (
                    <>
                        <AccessEditor
                            groups={permissionGroups}
                            inherited={role.permissions ?? []}
                            superRole={role.is_super}
                            value={data.overrides}
                            onChange={(next) => setData('overrides', next)}
                        />
                        <InputError message={errors.overrides} />
                    </>
                )}
            </section>

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={route('admin.users.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
