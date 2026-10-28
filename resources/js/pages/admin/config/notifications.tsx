import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import IconChip from '@/components/viz/icon-chip';
import { usePermission } from '@/hooks/use-permission';
import ConfigLayout from '@/layouts/config/config-layout';
import { SECTIONS } from '@/lib/sections';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlarmClock, ExternalLink, Info, Newspaper, X } from 'lucide-react';
import { useState, type FormEventHandler, type KeyboardEvent, type ReactNode } from 'react';

type Form = {
    task_assigned: boolean;
    task_urgent: boolean;
    task_status: string[];
    bug_assigned: boolean;
    bug_status: string[];
    overdue_enabled: boolean;
    overdue_time: string;
    summary_leaders: boolean;
    summary_emails: string[];
    summary_projects: boolean;
    summary_time: string;
};

interface Props {
    settings: Form;
    taskStatuses: Option[];
    bugStatuses: Option[];
    timezone: string;
    mailIsLocal: boolean;
}

/** One trigger: what it is, who hears, and its switch. */
function Trigger({
    id,
    label,
    hint,
    checked,
    disabled,
    onChange,
}: {
    id: string;
    label: ReactNode;
    hint?: string;
    checked: boolean;
    disabled: boolean;
    onChange: (on: boolean) => void;
}) {
    return (
        <div className="flex items-start justify-between gap-4 py-3">
            <div className="space-y-0.5">
                <Label htmlFor={id} className="text-sm font-medium">
                    {label}
                </Label>
                {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            </div>
            <Switch id={id} checked={checked} disabled={disabled} onCheckedChange={onChange} />
        </div>
    );
}

export default function NotificationSettings({ settings, taskStatuses, bugStatuses, timezone, mailIsLocal }: Props) {
    const canEdit = usePermission().can('settings.edit');
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm<Form>(settings);
    const [address, setAddress] = useState('');
    const [addressError, setAddressError] = useState<string | null>(null);
    const emailErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('summary_emails'))
        .map(([, message]) => message);

    // Added to the list here; saved with the rest of the page.
    const addAddress = () => {
        const email = address.trim().toLowerCase();
        if (email === '') return;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setAddressError('That doesn’t look like an email address.');
            return;
        }
        if (!data.summary_emails.includes(email)) setData('summary_emails', [...data.summary_emails, email]);
        setAddress('');
        setAddressError(null);
    };

    const onAddressKey = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addAddress();
        }
    };

    const toggleStatus = (field: 'task_status' | 'bug_status', value: string, on: boolean) =>
        setData(field, on ? [...data[field], value] : data[field].filter((v) => v !== value));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.config.notifications.update'), { preserveScroll: true });
    };

    const statusCard = (kind: 'task' | 'bug') => {
        const field = kind === 'task' ? 'task_status' : 'bug_status';
        const statuses = kind === 'task' ? taskStatuses : bugStatuses;

        return (
            <div className="divide-y">
                {statuses.map((status) => (
                    <Trigger
                        key={status.value}
                        id={`${kind}-${status.value}`}
                        label={
                            <>
                                Moved to <span className="font-semibold">{status.label}</span>
                            </>
                        }
                        checked={data[field].includes(status.value)}
                        disabled={!canEdit}
                        onChange={(on) => toggleStatus(field, status.value, on)}
                    />
                ))}
            </div>
        );
    };

    return (
        <ConfigLayout
            tab="notifications"
            actions={
                canEdit ? (
                    <div className="flex items-center gap-3">
                        {recentlySuccessful && <span className="text-muted-foreground text-sm">Saved</span>}
                        <Button onClick={submit} disabled={processing}>
                            Save
                        </Button>
                    </div>
                ) : (
                    <span className="text-muted-foreground text-xs">View only</span>
                )
            }
        >
            <form onSubmit={submit} className="grid max-w-6xl gap-4 lg:grid-cols-2">
                <div className="bg-muted/40 text-muted-foreground flex items-start gap-3 rounded-lg border px-4 py-3 text-sm lg:col-span-2">
                    <Info className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--tone-blue)' }} />
                    <p>
                        Status emails go to the person the work is <strong className="text-foreground">assigned to</strong> and the person who{' '}
                        <strong className="text-foreground">created or reported</strong> it. Nobody is emailed about their own change.
                        {mailIsLocal && ' Mail is currently written to the log, not sent.'}
                    </p>
                </div>

                <Card>
                    <CardHeader className="flex-row items-start gap-3 space-y-0">
                        <IconChip icon={SECTIONS.tasks.icon} tone={SECTIONS.tasks.tone} />
                        <div className="space-y-1">
                            <CardTitle className="text-base">Tasks</CardTitle>
                            <CardDescription>Email when a task is…</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <Trigger
                            id="task-assigned"
                            label="Assigned to someone"
                            hint="To the new assignee."
                            checked={data.task_assigned}
                            disabled={!canEdit}
                            onChange={(on) => setData('task_assigned', on)}
                        />
                        <Trigger
                            id="task-urgent"
                            label="Marked urgent"
                            hint="To the assignee, when a task is created urgent, raised to urgent, or an urgent task is handed to them."
                            checked={data.task_urgent}
                            disabled={!canEdit}
                            onChange={(on) => setData('task_urgent', on)}
                        />
                        {statusCard('task')}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start gap-3 space-y-0">
                        <IconChip icon={SECTIONS.testing.icon} tone={SECTIONS.testing.tone} />
                        <div className="space-y-1">
                            <CardTitle className="text-base">Testing (bugs)</CardTitle>
                            <CardDescription>Email when a bug is…</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <Trigger
                            id="bug-assigned"
                            label="Assigned to someone"
                            hint="To the developer it is given to."
                            checked={data.bug_assigned}
                            disabled={!canEdit}
                            onChange={(on) => setData('bug_assigned', on)}
                        />
                        {statusCard('bug')}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader className="flex-row items-start gap-3 space-y-0">
                        <IconChip icon={AlarmClock} tone="red" />
                        <div className="space-y-1">
                            <CardTitle className="text-base">Daily overdue email</CardTitle>
                            <CardDescription>
                                Once a day, everyone with overdue tasks gets one email listing them. Project owners see their projects’ overdue tasks
                                in the daily summary below.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-x-8 md:grid-cols-2">
                        <div className="divide-y">
                            <Trigger
                                id="overdue"
                                label="Send the daily overdue email"
                                checked={data.overdue_enabled}
                                disabled={!canEdit}
                                onChange={(on) => setData('overdue_enabled', on)}
                            />
                        </div>
                        <div className="grid content-start gap-2 py-3">
                            <Label htmlFor="overdue_time">Send at</Label>
                            <Input
                                id="overdue_time"
                                type="time"
                                className="w-36"
                                value={data.overdue_time}
                                disabled={!canEdit || !data.overdue_enabled}
                                onChange={(e) => setData('overdue_time', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">
                                Every day, {timezone} time. The server’s scheduler must be running (
                                <code className="font-mono">php artisan schedule:run</code> every minute in cron).
                            </p>
                            <InputError message={errors.overdue_time} />
                        </div>
                    </CardContent>
                </Card>
                <Card className="lg:col-span-2">
                    <CardHeader className="flex-row items-start gap-3 space-y-0">
                        <IconChip icon={Newspaper} tone="indigo" />
                        <div className="flex-1 space-y-1">
                            <CardTitle className="text-base">Daily summary</CardTitle>
                            <CardDescription>
                                Once a day, the status of every active project: progress, overdue and upcoming tasks, open bugs, merge requests
                                awaiting review, and what changed since that person’s last summary.
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <a href={route('admin.config.notifications.summary-preview')} target="_blank" rel="noreferrer">
                                <ExternalLink className="size-4" /> Preview
                            </a>
                        </Button>
                    </CardHeader>
                    <CardContent className="grid gap-x-8 md:grid-cols-2">
                        <div className="divide-y">
                            <Trigger
                                id="summary-leaders"
                                label="Send leadership the summary of every project"
                                hint="To the addresses below — founder, owner, administrators. They don’t need an account."
                                checked={data.summary_leaders}
                                disabled={!canEdit}
                                onChange={(on) => setData('summary_leaders', on)}
                            />
                            <div className="space-y-2 py-3">
                                <Label htmlFor="summary_email">Leadership addresses</Label>
                                <div className="flex flex-wrap gap-1.5">
                                    {data.summary_emails.length === 0 && (
                                        <p className="text-muted-foreground text-xs">No addresses yet — nobody gets the leadership summary.</p>
                                    )}
                                    {data.summary_emails.map((email) => (
                                        <span
                                            key={email}
                                            className="bg-muted inline-flex items-center gap-1 rounded-md border py-0.5 pr-1 pl-2 text-xs"
                                        >
                                            {email}
                                            {canEdit && (
                                                <button
                                                    type="button"
                                                    aria-label={`Remove ${email}`}
                                                    className="hover:bg-background rounded p-0.5"
                                                    onClick={() =>
                                                        setData(
                                                            'summary_emails',
                                                            data.summary_emails.filter((e) => e !== email),
                                                        )
                                                    }
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            )}
                                        </span>
                                    ))}
                                </div>
                                {canEdit && (
                                    <div className="flex gap-2">
                                        <Input
                                            id="summary_email"
                                            type="email"
                                            placeholder="name@company.com"
                                            value={address}
                                            onChange={(e) => setAddress(e.target.value)}
                                            onKeyDown={onAddressKey}
                                        />
                                        <Button type="button" variant="outline" onClick={addAddress} disabled={address.trim() === ''}>
                                            Add
                                        </Button>
                                    </div>
                                )}
                                <InputError message={addressError ?? emailErrors[0]} />
                                {canEdit && <p className="text-muted-foreground text-xs">Added addresses are kept when you press Save.</p>}
                            </div>
                            <Trigger
                                id="summary-projects"
                                label="Send each project’s summary to its owner and recipients"
                                hint="The owner always gets it; more people are added on the project’s Members tab."
                                checked={data.summary_projects}
                                disabled={!canEdit}
                                onChange={(on) => setData('summary_projects', on)}
                            />
                        </div>
                        <div className="grid content-start gap-2 py-3">
                            <Label htmlFor="summary_time">Send at</Label>
                            <Input
                                id="summary_time"
                                type="time"
                                className="w-36"
                                value={data.summary_time}
                                disabled={!canEdit || (!data.summary_leaders && !data.summary_projects)}
                                onChange={(e) => setData('summary_time', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">
                                Every day, {timezone} time. Someone on both lists gets one email covering all their projects.
                            </p>
                            <InputError message={errors.summary_time} />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </ConfigLayout>
    );
}
