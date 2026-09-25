import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import UserCombobox from '@/components/work/user-combobox';
import type { Option, User } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const NO_OWNER = 'none';

export interface ProjectFormData {
    name: string;
    code: string;
    description: string;
    status: string;
    owner_id: string;
    start_date: string;
    due_date: string;
    repository_url: string;
    default_branch: string;
    [key: string]: string;
}

interface Props {
    statuses: Option[];
    /** The current owner, for the picker's label; the picker searches for anyone else. */
    owner?: Pick<User, 'id' | 'name' | 'email'> | null;
    initial: ProjectFormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
}

export default function ProjectForm({ statuses, owner: initialOwner = null, initial, action, submitLabel }: Props) {
    const { data, setData, post, put, processing, errors, transform } = useForm<ProjectFormData>(initial);

    transform((payload) => ({
        ...payload,
        owner_id: payload.owner_id === NO_OWNER ? '' : payload.owner_id,
    }));

    const [owner, setOwner] = useState<Pick<User, 'id' | 'name'> | null>(initialOwner);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-6 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        placeholder="Attendance Module"
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="code">Code</Label>
                    <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} required placeholder="ATT" />
                    <InputError message={errors.code} />
                    <p className="text-muted-foreground text-xs">A short unique tag shown on task cards.</p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="owner_id">Owner</Label>
                    <UserCombobox
                        id="owner_id"
                        url={route('admin.lookups.users')}
                        value={owner}
                        noneLabel="No owner"
                        onChange={(user) => {
                            setOwner(user);
                            setData('owner_id', user ? String(user.id) : NO_OWNER);
                        }}
                    />
                    <InputError message={errors.owner_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger id="status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {statuses.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="start_date">Start date</Label>
                    <Input id="start_date" type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                    <InputError message={errors.start_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="due_date">Due date</Label>
                    <Input id="due_date" type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                    <InputError message={errors.due_date} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">
                    Description <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-6 border-t pt-6 sm:grid-cols-[2fr_1fr]">
                <div className="grid gap-2">
                    <Label htmlFor="repository_url">
                        Repository URL <span className="text-muted-foreground">(optional)</span>
                    </Label>
                    <Input
                        id="repository_url"
                        type="url"
                        value={data.repository_url}
                        onChange={(e) => setData('repository_url', e.target.value)}
                        placeholder="https://github.com/company/project"
                    />
                    <InputError message={errors.repository_url} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="default_branch">Default branch</Label>
                    <Input
                        id="default_branch"
                        value={data.default_branch}
                        onChange={(e) => setData('default_branch', e.target.value)}
                        required
                        placeholder="main"
                        className="font-mono"
                    />
                    <InputError message={errors.default_branch} />
                </div>
                <p className="text-muted-foreground -mt-3 text-xs sm:col-span-2">
                    Used by the Git tab: branches are merged into the default branch unless the request says otherwise. Members are managed on the
                    project’s Members tab.
                </p>
            </div>

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={route('admin.projects.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}

export { NO_OWNER };
