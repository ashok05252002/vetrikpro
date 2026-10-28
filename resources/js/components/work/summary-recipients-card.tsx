import DeleteButton from '@/components/admin/delete-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import IconChip from '@/components/viz/icon-chip';
import type { Option, ProjectWorkspaceHeader, SummaryRecipients } from '@/types';
import { useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import type { FormEventHandler } from 'react';

/**
 * Who gets this project's daily summary email: always the owner, plus anyone
 * added here — a member picked from the list, or any email address.
 */
export default function SummaryRecipientsCard({ project, summary }: { project: ProjectWorkspaceHeader; summary: SummaryRecipients }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ user_id: string; email: string }>({ user_id: '', email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.summary-recipients.store', project.id), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3 space-y-0">
                <IconChip icon={Mail} tone="indigo" />
                <div className="space-y-1">
                    <CardTitle className="text-base">Daily summary email</CardTitle>
                    <CardDescription>
                        Once a day: this project’s progress, what is pending, and what changed since the last email. The owner always gets it; add
                        anyone else here.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <ul className="divide-y rounded-lg border text-sm">
                    <li className="flex items-center justify-between gap-3 px-3 py-2">
                        <span className="font-medium">{project.owner?.name ?? 'No owner'}</span>
                        <Badge variant="outline">Owner</Badge>
                    </li>
                    {summary.recipients.map((r) => (
                        <li key={r.id} className="flex items-center justify-between gap-3 px-3 py-2">
                            <div className="min-w-0">
                                <p className="truncate font-medium">{r.name ?? r.email}</p>
                                {r.name && <p className="text-muted-foreground truncate text-xs">{r.email}</p>}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {!r.name && <Badge variant="secondary">Outside</Badge>}
                                {!r.is_active && <Badge variant="secondary">Disabled — not sent</Badge>}
                                <DeleteButton
                                    verb="Remove"
                                    url={route('projects.summary-recipients.destroy', [project.id, r.id])}
                                    label={`${r.name ?? r.email} from the daily summary`}
                                    description="They stop getting this project’s daily summary. Nothing else changes."
                                />
                            </div>
                        </li>
                    ))}
                </ul>

                <form onSubmit={submit} className="grid gap-2 sm:grid-cols-[1fr_auto_1fr_auto] sm:items-start">
                    <Select
                        value={data.user_id}
                        onValueChange={(v) => {
                            setData({ user_id: v, email: '' });
                        }}
                        disabled={summary.candidates.length === 0}
                    >
                        <SelectTrigger aria-label="Pick a member">
                            <SelectValue placeholder={summary.candidates.length === 0 ? 'No members left to add' : 'Pick a member…'} />
                        </SelectTrigger>
                        <SelectContent>
                            {summary.candidates.map((c: Option) => (
                                <SelectItem key={c.value} value={c.value}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span className="text-muted-foreground self-center text-center text-xs">or</span>
                    <Input
                        type="email"
                        placeholder="any email address"
                        value={data.email}
                        onChange={(e) => setData({ user_id: '', email: e.target.value })}
                        aria-label="Email address"
                    />
                    <Button type="submit" disabled={processing || (!data.user_id && !data.email.trim())}>
                        Add
                    </Button>
                </form>
                <InputError message={errors.email ?? errors.user_id} />
            </CardContent>
        </Card>
    );
}
