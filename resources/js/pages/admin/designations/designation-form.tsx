import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Department } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const NONE = 'none';

interface FormData {
    department_id: string;
    name: string;
    description: string;
    [key: string]: string;
}

interface Props {
    departments: Department[];
    initial: FormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
}

export default function DesignationForm({ departments, initial, action, submitLabel }: Props) {
    const { data, setData, post, put, processing, errors, transform } = useForm<FormData>(initial);

    // Radix Select cannot hold an empty string value, so "no department" rides
    // as a sentinel and is converted back to null on the way out.
    transform((payload) => ({
        ...payload,
        department_id: payload.department_id === NONE ? '' : payload.department_id,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Title</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    autoFocus
                    placeholder="Senior Software Engineer"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="department_id">Department</Label>
                <Select value={data.department_id} onValueChange={(value) => setData('department_id', value)}>
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
                <Label htmlFor="description">
                    Description <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                <InputError message={errors.description} />
            </div>

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={route('admin.designations.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}

export { NONE };
