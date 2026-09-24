import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/react';

interface Props<T> {
    meta: Paginated<T>;
}

export default function Pagination<T>({ meta }: Props<T>) {
    if (meta.last_page <= 1) {
        return <p className="text-muted-foreground px-1 text-sm">{meta.total} record(s)</p>;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-1">
            <p className="text-muted-foreground text-sm">
                Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
            </p>

            <div className="flex flex-wrap gap-1">
                {meta.links.map((link, i) => (
                    <Button key={i} asChild={Boolean(link.url)} size="sm" variant={link.active ? 'default' : 'outline'} disabled={!link.url}>
                        {link.url ? (
                            <Link href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}
