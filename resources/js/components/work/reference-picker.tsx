import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLookup } from '@/hooks/use-lookup';
import { Check, Loader2, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

export interface Reference {
    id: number;
    reference: string;
    title: string;
}

interface Props<T extends Reference> {
    projectId: number;
    kind: 'tasks' | 'test-points';
    /** What is already picked, shown as chips. */
    value: T[];
    onChange: (next: T[]) => void;
    /** At most one, e.g. the task a test point verifies. */
    single?: boolean;
    placeholder?: string;
    /** Extra detail on a result row, e.g. its status. */
    renderMeta?: (item: T) => ReactNode;
}

/**
 * Pick tasks or test points from a project by number ("T-12", "12") or title.
 */
export default function ReferencePicker<T extends Reference>({
    projectId,
    kind,
    value,
    onChange,
    single = false,
    placeholder,
    renderMeta,
}: Props<T>) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const root = useRef<HTMLDivElement>(null);
    const { results, loading } = useLookup<T>(route('projects.lookups', [projectId, kind]), { search }, open);
    const picked = new Set(value.map((v) => v.id));

    useEffect(() => {
        if (!open) {
            return;
        }
        const close = (event: MouseEvent) => !root.current?.contains(event.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const toggle = (item: T) => {
        if (picked.has(item.id)) {
            onChange(value.filter((v) => v.id !== item.id));
        } else {
            onChange(single ? [item] : [...value, item]);
        }
        if (single) {
            setOpen(false);
        }
    };

    return (
        <div ref={root} className="relative space-y-2">
            {value.length > 0 && (
                <ul className="flex flex-wrap gap-1.5">
                    {value.map((item) => (
                        <li key={item.id} className="bg-muted inline-flex max-w-full items-center gap-1.5 rounded-md py-0.5 pr-1 pl-2 text-xs">
                            <span className="font-mono">{item.reference}</span>
                            <span className="truncate">{item.title}</span>
                            <button
                                type="button"
                                onClick={() => onChange(value.filter((v) => v.id !== item.id))}
                                className="hover:bg-background rounded p-0.5"
                                aria-label={`Remove ${item.reference}`}
                            >
                                <X className="size-3" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {(!single || value.length === 0) && (
                <Button type="button" variant="outline" size="sm" onClick={() => setOpen((o) => !o)}>
                    <Plus className="size-4" /> {placeholder ?? (kind === 'tasks' ? 'Add task' : 'Add testing point')}
                </Button>
            )}

            {open && (
                <div
                    className="bg-popover text-popover-foreground absolute z-50 mt-1 w-full max-w-lg rounded-md border p-1 shadow-md"
                    onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
                >
                    <div className="relative mb-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            autoFocus
                            className="pl-9"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={kind === 'tasks' ? 'T-12 or a title…' : 'TP-4 or a title…'}
                        />
                    </div>
                    <ul role="listbox" className="max-h-64 overflow-y-auto">
                        {results.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected={picked.has(item.id)}
                                    onClick={() => toggle(item)}
                                    className="hover:bg-muted flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                >
                                    <span className="text-muted-foreground w-14 shrink-0 font-mono text-xs">{item.reference}</span>
                                    <span className="min-w-0 flex-1 truncate">{item.title}</span>
                                    {renderMeta?.(item)}
                                    {picked.has(item.id) && <Check className="size-4 shrink-0" />}
                                </button>
                            </li>
                        ))}
                        {loading && (
                            <li className="text-muted-foreground flex items-center gap-2 px-2 py-2 text-xs">
                                <Loader2 className="size-3 animate-spin" /> Searching…
                            </li>
                        )}
                        {!loading && results.length === 0 && <li className="text-muted-foreground px-2 py-2 text-xs">Nothing matches.</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}
