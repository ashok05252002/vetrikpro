import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

export interface TabLink<K extends string = string> {
    key: K;
    label: string;
    href: string;
    /** A count shown beside the label; null hides it. */
    count?: number | null;
}

/**
 * Tabs that are links. Each tab is its own route, so a tab can be bookmarked,
 * shared and reloaded, and only loads its own data.
 */
export default function TabNav<K extends string>({ tabs, active, label }: { tabs: TabLink<K>[]; active: K; label: string }) {
    return (
        <nav aria-label={label} className="-mx-4 overflow-x-auto border-b px-4 md:-mx-6 md:px-6">
            <ul className="flex min-w-max gap-1">
                {tabs.map((tab) => (
                    <li key={tab.key}>
                        <Link
                            href={tab.href}
                            preserveScroll
                            aria-current={tab.key === active ? 'page' : undefined}
                            className={cn(
                                '-mb-px inline-flex h-10 items-center gap-1.5 border-b-2 px-3 text-sm transition-colors',
                                tab.key === active
                                    ? 'border-foreground text-foreground font-medium'
                                    : 'text-muted-foreground hover:text-foreground border-transparent',
                            )}
                        >
                            {tab.label}
                            {tab.count !== undefined && tab.count !== null && (
                                <span className="bg-muted text-muted-foreground rounded-full px-1.5 text-[11px] tabular-nums">{tab.count}</span>
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
