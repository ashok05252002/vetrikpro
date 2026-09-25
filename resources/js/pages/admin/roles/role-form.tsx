import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PermissionGroup } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type RoleFormData = {
    name: string;
    description: string;
    permissions: string[];
};

interface Props {
    groups: PermissionGroup[];
    initial: RoleFormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    /** The administrator role holds everything; its matrix is informational. */
    superRole?: boolean;
}

export default function RoleForm({ groups, initial, action, submitLabel, superRole = false }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<RoleFormData>(initial);

    const toggle = (key: string, on: boolean) =>
        setData('permissions', on ? [...data.permissions, key] : data.permissions.filter((permission) => permission !== key));

    const toggleGroup = (keys: string[], on: boolean) =>
        setData('permissions', on ? Array.from(new Set([...data.permissions, ...keys])) : data.permissions.filter((key) => !keys.includes(key)));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-8">
            <section className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus placeholder="Team Lead" />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={2}
                        placeholder="Who gets this role, in one line."
                    />
                    <InputError message={errors.description} />
                </div>
            </section>

            <section className="space-y-4 border-t pt-6">
                <div className="space-y-1">
                    <h2 className="text-base font-semibold">Permissions</h2>
                    <p className="text-muted-foreground text-sm">
                        {superRole
                            ? 'This role holds every permission, including ones added later, so there is nothing to tick.'
                            : 'Everyone with this role gets these. Individual people can still be adjusted on their user form.'}
                    </p>
                </div>

                <InputError message={errors.permissions} />

                {groups.map((group) => {
                    const keys = group.permissions.map((p) => p.key);
                    const held = superRole ? keys.length : keys.filter((key) => data.permissions.includes(key)).length;

                    return (
                        <div key={group.group} className="overflow-hidden rounded-lg border">
                            <label className="bg-muted/50 flex cursor-pointer items-center gap-3 px-4 py-2">
                                <Checkbox
                                    disabled={superRole}
                                    checked={held === keys.length ? true : held > 0 ? 'indeterminate' : false}
                                    onCheckedChange={(checked) => toggleGroup(keys, checked === true)}
                                />
                                <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{group.group}</span>
                                <span className="text-muted-foreground ml-auto text-xs tabular-nums">
                                    {held}/{keys.length}
                                </span>
                            </label>
                            <ul className="divide-y">
                                {group.permissions.map((permission) => (
                                    <li key={permission.key}>
                                        <label className="flex cursor-pointer items-start gap-3 px-4 py-3">
                                            <Checkbox
                                                className="mt-0.5"
                                                disabled={superRole}
                                                checked={superRole || data.permissions.includes(permission.key)}
                                                onCheckedChange={(checked) => toggle(permission.key, checked === true)}
                                            />
                                            <span className="space-y-0.5">
                                                <span className="block text-sm">{permission.label}</span>
                                                <code className="text-muted-foreground block text-xs">{permission.key}</code>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    );
                })}
            </section>

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={route('admin.roles.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
