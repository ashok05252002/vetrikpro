import PermissionMatrix, { viewKey } from '@/components/access/permission-matrix';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PermissionGroup, PermissionModule } from '@/types';
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

    const allKeys = (module: PermissionModule) => module.actions.map((a) => a.key);

    // Ticking any action brings its module's View along; unticking View clears
    // the row — nobody edits what they cannot see.
    const toggle = (key: string, on: boolean, module: PermissionModule) => {
        const view = viewKey(module);
        const next = new Set(data.permissions);

        if (on) {
            next.add(key);
            if (view) {
                next.add(view);
            }
        } else if (key === view) {
            allKeys(module).forEach((k) => next.delete(k));
        } else {
            next.delete(key);
        }

        setData('permissions', Array.from(next));
    };

    const toggleRow = (module: PermissionModule, on: boolean) => {
        const next = new Set(data.permissions);
        allKeys(module).forEach((k) => (on ? next.add(k) : next.delete(k)));
        setData('permissions', Array.from(next));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-6xl space-y-8">
            <section className="max-w-3xl space-y-6">
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

                <PermissionMatrix
                    groups={groups}
                    cell={(key, label, module) => (
                        <Checkbox
                            aria-label={label}
                            disabled={superRole}
                            checked={superRole || data.permissions.includes(key)}
                            onCheckedChange={(checked) => toggle(key, checked === true, module)}
                        />
                    )}
                    rowAction={(module) => {
                        const held = superRole ? module.actions.length : module.actions.filter((a) => data.permissions.includes(a.key)).length;

                        return (
                            <label className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                <Checkbox
                                    aria-label={`All ${module.label} permissions`}
                                    disabled={superRole}
                                    checked={held === module.actions.length ? true : held > 0 ? 'indeterminate' : false}
                                    onCheckedChange={(checked) => toggleRow(module, checked === true)}
                                />
                                All
                            </label>
                        );
                    }}
                />
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
