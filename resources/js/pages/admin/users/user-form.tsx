import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Option, UserRole } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface UserFormData {
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    password: string;
    password_confirmation: string;
    [key: string]: string | boolean;
}

interface Props {
    roles: Option[];
    initial: UserFormData;
    /** Edit forms PUT to an existing record; create forms POST. */
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    passwordOptional?: boolean;
}

export default function UserForm({ roles, initial, action, submitLabel, passwordOptional = false }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<UserFormData>(initial);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const submitter = action.method === 'put' ? put : post;
        submitter(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
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

            <div className="grid gap-2">
                <Label htmlFor="role">Role</Label>
                <Select value={data.role} onValueChange={(value) => setData('role', value as UserRole)}>
                    <SelectTrigger id="role">
                        <SelectValue placeholder="Select a role" />
                    </SelectTrigger>
                    <SelectContent>
                        {roles.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                {role.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.role} />
                <p className="text-muted-foreground text-xs">Administrators and HR managers can reach the admin area.</p>
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

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={route('admin.users.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
