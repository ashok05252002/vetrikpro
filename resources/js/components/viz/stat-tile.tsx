import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';

/**
 * Stat tile contract: label (sentence case), value (semibold, proportional
 * figures — never tabular-nums at this size), optional note.
 */
interface Props {
    label: string;
    value: number | string;
    note?: ReactNode;
    icon?: ComponentType<{ className?: string }>;
    href?: string;
    /** Draws attention when the number is one the reader should act on. */
    alert?: boolean;
}

export default function StatTile({ label, value, note, icon: Icon, href, alert = false }: Props) {
    const body = (
        <CardContent className="flex items-start justify-between gap-3 p-5">
            <div className="min-w-0 space-y-1">
                <p className="text-muted-foreground truncate text-xs">{label}</p>
                <p className={cn('text-3xl font-semibold tracking-tight', alert && Number(value) > 0 && 'text-destructive')}>{value}</p>
                {note && <p className="text-muted-foreground truncate text-xs">{note}</p>}
            </div>
            {Icon && <Icon className="text-muted-foreground size-4 shrink-0" />}
        </CardContent>
    );

    if (href) {
        return (
            <Card className="hover:border-foreground/20 transition-colors">
                <Link href={href} className="focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:outline-hidden">
                    {body}
                </Link>
            </Card>
        );
    }

    return <Card>{body}</Card>;
}
