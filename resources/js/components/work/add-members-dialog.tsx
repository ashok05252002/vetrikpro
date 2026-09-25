import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import UserAvatar from '@/components/work/user-avatar';
import { useLookup } from '@/hooks/use-lookup';
import type { DirectoryUser, Option } from '@/types';
import { router } from '@inertiajs/react';
import { Loader2, Search } from 'lucide-react';
import { useState } from 'react';

const ANY = '__any__';

interface Props {
    projectId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    departments: Option[];
    roles: Option[];
}

/**
 * Search, filter and multi-select people to add. The selection survives
 * changing the search, so a team can be built across several queries.
 */
export default function AddMembersDialog({ projectId, open, onOpenChange, departments, roles }: Props) {
    const [search, setSearch] = useState('');
    const [department, setDepartment] = useState<string>(ANY);
    const [role, setRole] = useState('member');
    const [selected, setSelected] = useState<Map<number, DirectoryUser>>(new Map());
    const [processing, setProcessing] = useState(false);

    const { results, loading } = useLookup<DirectoryUser>(
        route('projects.members.candidates', projectId),
        { search, department: department === ANY ? undefined : department },
        open,
    );

    const toggle = (user: DirectoryUser, on: boolean) =>
        setSelected((current) => {
            const next = new Map(current);
            if (on) {
                next.set(user.id, user);
            } else {
                next.delete(user.id);
            }
            return next;
        });

    const reset = () => {
        setSearch('');
        setDepartment(ANY);
        setRole('member');
        setSelected(new Map());
    };

    const submit = () => {
        router.post(
            route('projects.members.store', projectId),
            { user_ids: Array.from(selected.keys()), role },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add members</DialogTitle>
                    <DialogDescription>Only active people who aren’t on this project yet are listed.</DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            autoFocus
                            className="pl-9"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Name, email or employee code…"
                        />
                    </div>
                    <Select value={department} onValueChange={setDepartment}>
                        <SelectTrigger className="sm:w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>All departments</SelectItem>
                            {departments.map((d) => (
                                <SelectItem key={d.value} value={d.value}>
                                    {d.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <ul className="min-h-40 flex-1 divide-y overflow-y-auto rounded-md border">
                    {results.map((user) => (
                        <li key={user.id}>
                            <label className="hover:bg-muted/60 flex cursor-pointer items-center gap-3 px-3 py-2">
                                <Checkbox checked={selected.has(user.id)} onCheckedChange={(checked) => toggle(user, checked === true)} />
                                <UserAvatar name={user.name} className="size-7" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm">{user.name}</span>
                                    <span className="text-muted-foreground block truncate text-xs">
                                        {[user.email, user.designation, user.department].filter(Boolean).join(' · ')}
                                    </span>
                                </span>
                            </label>
                        </li>
                    ))}

                    {loading && results.length === 0 && (
                        <li className="text-muted-foreground flex items-center gap-2 px-3 py-6 text-sm">
                            <Loader2 className="size-4 animate-spin" /> Searching…
                        </li>
                    )}
                    {!loading && results.length === 0 && <li className="text-muted-foreground px-3 py-6 text-center text-sm">Nobody matches.</li>}
                </ul>

                {selected.size > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {Array.from(selected.values()).map((user) => (
                            <button
                                key={user.id}
                                type="button"
                                onClick={() => toggle(user, false)}
                                className="bg-muted hover:bg-muted/70 rounded-full px-2.5 py-0.5 text-xs"
                                aria-label={`Remove ${user.name} from selection`}
                            >
                                {user.name} ×
                            </button>
                        ))}
                    </div>
                )}

                <DialogFooter className="items-center gap-3 sm:justify-between">
                    <div className="flex items-center gap-2">
                        <Label htmlFor="member-role" className="text-sm font-normal whitespace-nowrap">
                            Add as
                        </Label>
                        <Select value={role} onValueChange={setRole}>
                            <SelectTrigger id="member-role" className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((r) => (
                                    <SelectItem key={r.value} value={r.value}>
                                        {r.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button onClick={submit} disabled={selected.size === 0 || processing}>
                        Add {selected.size > 0 ? selected.size : ''} {selected.size === 1 ? 'person' : 'people'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
