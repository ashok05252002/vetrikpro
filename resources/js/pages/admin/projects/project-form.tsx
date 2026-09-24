import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import UserAvatar from '@/components/work/user-avatar';
import type { Option, User } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const NO_OWNER = 'none';

export interface ProjectFormData {
    name: string;
    code: string;
    description: string;
    status: string;
    owner_id: string;
    start_date: string;
    due_date: string;
    members: number[];
    [key: string]: string | number[] | number;
}

interface Props {
    users: Pick<User, 'id' | 'name' | 'email'>[];
    statuses: Option[];
    initial: ProjectFormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
}

export default function ProjectForm({ users, statuses, initial, action, submitLabel }: Props) {
    const { data, setData, post, put, processing, errors, transform } = useForm<ProjectFormData>(initial);

    transform((payload) => ({
        ...payload,
        owner_id: payload.owner_id === NO_OWNER ? '' : payload.owner_id,
    }));

    const toggleMember = (id: number, checked: boolean) => {
        setData('members', checked ? [...data.members, id] : data.members.filter((m) => m !== id));
    };

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
                    <Select value={data.owner_id} onValueChange={(value) => setData('owner_id', value)}>
                        <SelectTrigger id="owner_id">
                            <SelectValue placeholder="Select an owner" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_OWNER}>No owner</SelectItem>
                            {users.map((user) => (
                                <SelectItem key={user.id} value={String(user.id)}>
                                    {user.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
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

            <div className="space-y-3">
                <div>
                    <Label>Members</Label>
                    <p className="text-muted-foreground text-xs">Members see the board and can be assigned tasks.</p>
                </div>

                <div className="grid gap-1 rounded-lg border p-2 sm:grid-cols-2">
                    {users.map((user) => (
                        <label
                            key={user.id}
                            htmlFor={`member-${user.id}`}
                            className="hover:bg-muted/60 flex cursor-pointer items-center gap-3 rounded-md p-2"
                        >
                            <Checkbox
                                id={`member-${user.id}`}
                                checked={data.members.includes(user.id)}
                                onCheckedChange={(checked) => toggleMember(user.id, checked === true)}
                            />
                            <UserAvatar name={user.name} className="size-6" />
                            <span className="min-w-0">
                                <span className="block truncate text-sm">{user.name}</span>
                                <span className="text-muted-foreground block truncate text-xs">{user.email}</span>
                            </span>
                        </label>
                    ))}
                </div>
                <InputError message={errors.members} />
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
