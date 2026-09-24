import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Debounced search box that pushes `search` into the query string.
 */
export default function SearchFilter({ url, initial = '', placeholder = 'Search…' }: { url: string; initial?: string; placeholder?: string }) {
    const [value, setValue] = useState(initial);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(url, value ? { search: value } : {}, { preserveState: true, replace: true });
        }, 350);

        return () => clearTimeout(timer);
    }, [value, url]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <Input className="pl-9" value={value} onChange={(e) => setValue(e.target.value)} placeholder={placeholder} />
        </div>
    );
}
