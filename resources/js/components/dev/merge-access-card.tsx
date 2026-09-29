import { Button } from '@/components/ui/button';
import Pill from '@/components/ui/pill';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import UserAvatar from '@/components/work/user-avatar';
import { router } from '@inertiajs/react';
import { Crown, GitMerge, Star, X } from 'lucide-react';
import { useState } from 'react';

type Person = { id: number; name: string; email: string };

export interface MergeAccess {
    people: (Person & { via: 'owner' | 'lead' | 'granted' })[];
    eligible: Person[];
}

const via = {
    owner: { label: 'Owner', icon: Crown, color: 'var(--status-warning)' },
    lead: { label: 'Project lead', icon: Star, color: 'var(--tone-violet)' },
    granted: { label: 'Merge access', icon: GitMerge, color: 'var(--gh-merged)' },
};

/**
 * Who may review and merge on this project. The owner and leads always can;
 * the owner or a lead grants it to other members — only those whose role
 * makes them eligible (Roles & access → Project roles).
 */
export default function MergeAccessCard({ projectId, access, canManage }: { projectId: number; access: MergeAccess; canManage: boolean }) {
    const [pick, setPick] = useState('');
    const [busy, setBusy] = useState(false);

    const setRole = (userId: number, role: 'dev_admin' | 'member') =>
        router.patch(
            route('projects.members.update', [projectId, userId]),
            { role },
            { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => (setBusy(false), setPick('')) },
        );

    return (
        <section className="bg-card rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <GitMerge className="size-4" style={{ color: 'var(--gh-merged)' }} aria-hidden /> Who can merge
                    </h2>
                    <p className="text-muted-foreground text-xs">Reviews and merges this project's merge requests.</p>
                </div>

                {canManage && (
                    <div className="flex items-center gap-2">
                        <Select value={pick} onValueChange={setPick} disabled={access.eligible.length === 0}>
                            <SelectTrigger className="h-8 w-56 text-xs">
                                <SelectValue placeholder={access.eligible.length ? 'Give merge access to…' : 'No other eligible members'} />
                            </SelectTrigger>
                            <SelectContent>
                                {access.eligible.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)} className="text-xs">
                                        {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button size="sm" disabled={!pick || busy} onClick={() => setRole(Number(pick), 'dev_admin')}>
                            Grant
                        </Button>
                    </div>
                )}
            </div>

            <ul className="mt-3 flex flex-wrap gap-2">
                {access.people.length === 0 && <li className="text-muted-foreground text-sm">Nobody yet — only administrators can merge.</li>}
                {access.people.map((person) => {
                    const spec = via[person.via];
                    return (
                        <li
                            key={`${person.via}-${person.id}`}
                            className="flex items-center gap-2 rounded-full border py-1 pr-2 pl-1 text-sm"
                            title={person.email}
                        >
                            <UserAvatar name={person.name} className="size-6" />
                            {person.name}
                            <Pill color={spec.color} icon={spec.icon}>
                                {spec.label}
                            </Pill>
                            {canManage && person.via === 'granted' && (
                                <button
                                    type="button"
                                    onClick={() => setRole(person.id, 'member')}
                                    disabled={busy}
                                    className="text-muted-foreground hover:text-destructive rounded p-0.5"
                                    aria-label={`Remove merge access from ${person.name}`}
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
