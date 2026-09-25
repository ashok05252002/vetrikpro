import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UserAvatar from '@/components/work/user-avatar';
import { useLookup } from '@/hooks/use-lookup';
import { cn } from '@/lib/utils';
import type { DirectoryUser } from '@/types';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Picked = Pick<DirectoryUser, 'id' | 'name'> & { email?: string };

interface Props {
    id?: string;
    /** A lookup endpoint returning `{ data: DirectoryUser[] }`. */
    url: string;
    value: Picked | null;
    onChange: (user: Picked | null) => void;
    placeholder?: string;
    /** Label for the "nobody" option; omit to make a choice required. */
    noneLabel?: string;
}

/**
 * Pick one person by searching, instead of scrolling a dropdown of everyone.
 */
export default function UserCombobox({ id, url, value, onChange, placeholder = 'Search people…', noneLabel }: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const root = useRef<HTMLDivElement>(null);
    const { results, loading } = useLookup<DirectoryUser>(url, { search }, open);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const pick = (user: Picked | null) => {
        onChange(user);
        setOpen(false);
        setSearch('');
    };

    return (
        <div ref={root} className="relative">
            <Button
                id={id}
                type="button"
                variant="outline"
                role="combobox"
                aria-expanded={open}
                className="w-full justify-between font-normal"
                onClick={() => setOpen((o) => !o)}
            >
                <span className={cn('flex min-w-0 items-center gap-2', !value && 'text-muted-foreground')}>
                    {value && <UserAvatar name={value.name} className="size-5" />}
                    <span className="truncate">{value?.name ?? noneLabel ?? 'Select a person'}</span>
                </span>
                <ChevronsUpDown className="size-4 opacity-50" />
            </Button>

            {open && (
                <div
                    className="bg-popover text-popover-foreground absolute z-50 mt-1 w-full rounded-md border p-1 shadow-md"
                    onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
                >
                    <Input autoFocus value={search} onChange={(e) => setSearch(e.target.value)} placeholder={placeholder} className="mb-1" />

                    <ul role="listbox" className="max-h-64 overflow-y-auto">
                        {noneLabel && (
                            <li>
                                <button
                                    type="button"
                                    className="hover:bg-muted text-muted-foreground flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm"
                                    onClick={() => pick(null)}
                                >
                                    {noneLabel}
                                </button>
                            </li>
                        )}

                        {results.map((user) => (
                            <li key={user.id}>
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected={value?.id === user.id}
                                    className="hover:bg-muted flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left"
                                    onClick={() => pick(user)}
                                >
                                    <UserAvatar name={user.name} className="size-6" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm">{user.name}</span>
                                        <span className="text-muted-foreground block truncate text-xs">
                                            {[user.email, user.department].filter(Boolean).join(' · ')}
                                        </span>
                                    </span>
                                    {value?.id === user.id && <Check className="size-4" />}
                                </button>
                            </li>
                        ))}

                        {loading && (
                            <li className="text-muted-foreground flex items-center gap-2 px-2 py-2 text-xs">
                                <Loader2 className="size-3 animate-spin" /> Searching…
                            </li>
                        )}
                        {!loading && results.length === 0 && <li className="text-muted-foreground px-2 py-2 text-xs">Nobody matches.</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}
