import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    token: string;
    email: string;
    name: string | null;
    valid: boolean;
}

/**
 * Where the welcome email's link lands: pick a password, then carry on to
 * completing the profile.
 */
export default function AcceptInvitation({ token, email, name, valid }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({ token, email, password: '', password_confirmation: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitation.store'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    if (!valid) {
        return (
            <AuthLayout
                title="This link has expired"
                description="Invite links work once, for a limited time, and a newer invite replaces an older one."
            >
                <Head title="Invite expired" />
                <p className="text-muted-foreground text-center text-sm">
                    Ask HR to send you a new invite. If you have already set a password, sign in instead.
                </p>
                <div className="text-center text-sm">
                    <TextLink href={route('login')}>Go to sign in</TextLink>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={name ? `Welcome, ${name.split(' ')[0]}` : 'Welcome'} description="Choose a password to finish setting up your account.">
            <Head title="Set your password" />

            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={data.email} readOnly className="bg-muted" />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    Set password and continue
                </Button>
            </form>
        </AuthLayout>
    );
}
