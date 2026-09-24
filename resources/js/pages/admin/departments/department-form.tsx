import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface FormData {
    name: string;
    code: string;
    description: string;
    [key: string]: string;
}

interface Props {
    initial: FormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
}

export default function DepartmentForm({ initial, action, submitLabel }: Props) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>(initial);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus placeholder="Engineering" />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="code">
                    Code <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="ENG" />
                <InputError message={errors.code} />
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
                    <Link href={route('admin.departments.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
