import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Option } from '@/types';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** Radix Select cannot hold an empty value, so "no filter" travels as this sentinel and never reaches the URL. */
const ALL = '__all__';

export interface SelectFilter {
    /** Query-string key, e.g. `role`. */
    name: string;
    /** Shown when nothing is picked, e.g. "All roles". */
    placeholder: string;
    options: Option[];
}

type Filters = Record<string, string | undefined>;

interface FilterBarProps {
    url: string;
    /** The filters the server echoed back, so the controls survive a reload. */
    filters: Filters;
    searchPlaceholder?: string;
    selects?: SelectFilter[];
    /** Query keys that are not filters but must survive a filter change, e.g. `view`. */
    keep?: Record<string, string | undefined>;
}

/**
 * Search box plus any number of dropdown filters, all pushed into the query
 * string together. Changing one filter keeps the others and resets to page 1.
 */
export default function FilterBar({ url, filters, searchPlaceholder = 'Search…', selects = [], keep = {} }: FilterBarProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [values, setValues] = useState<Filters>(() => Object.fromEntries(selects.map((s) => [s.name, filters[s.name]])));
    const firstRender = useRef(true);

    const push = (next: Filters) => {
        const query = Object.fromEntries(Object.entries({ ...keep, ...next }).filter(([, value]) => value !== undefined && value !== ''));
        router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Typing is debounced; dropdowns apply at once.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => push({ ...values, search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const setSelect = (name: string, value: string) => {
        const next = { ...values, [name]: value === ALL ? undefined : value };
        setValues(next);
        push({ ...next, search });
    };

    const active = search !== '' || Object.values(values).some((value) => value !== undefined);

    const clear = () => {
        const cleared = Object.fromEntries(selects.map((s) => [s.name, undefined]));
        setSearch('');
        setValues(cleared);
        push(cleared);
    };

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div className="relative w-full sm:max-w-xs">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input className="pl-9" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={searchPlaceholder} />
            </div>

            {selects.map((select) => (
                <Select key={select.name} value={values[select.name] ?? ALL} onValueChange={(value) => setSelect(select.name, value)}>
                    <SelectTrigger className="w-full sm:w-44">
                        <SelectValue placeholder={select.placeholder} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>{select.placeholder}</SelectItem>
                        {select.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ))}

            {active && (
                <Button variant="ghost" size="sm" onClick={clear}>
                    <X className="size-4" /> Clear
                </Button>
            )}
        </div>
    );
}
