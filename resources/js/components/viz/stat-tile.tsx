import { Card, CardContent } from '@/components/ui/card';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';

/**
 * Stat tile contract: label (sentence case), value (semibold, proportional
 * figures — never tabular-nums at this size), optional note. The number stays
 * in ink; the tone colours only the icon chip and the hover edge.
 */
interface Props {
    label: string;
    value: number | string;
    note?: ReactNode;
    icon?: ComponentType<{ className?: string }>;
    tone?: Tone;
    href?: string;
    /** Draws attention when the number is one the reader should act on. */
    alert?: boolean;
}

export default function StatTile({ label, value, note, icon, tone = 'indigo', href, alert = false }: Props) {
    const body = (
        <CardContent className="flex items-center gap-4 p-5">
            {icon && <IconChip icon={icon} tone={tone} />}
            <div className="min-w-0 space-y-0.5">
                <p className="text-muted-foreground truncate text-xs font-medium">{label}</p>
                <p className={cn('text-2xl font-semibold tracking-tight', alert && Number(value) > 0 && 'text-destructive')}>{value}</p>
                {note && <p className="text-muted-foreground truncate text-xs">{note}</p>}
            </div>
        </CardContent>
    );

    if (href) {
        return (
            <Card
                className="group relative overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-md"
                style={{ ['--tile-tone' as string]: `var(--tone-${tone})` }}
            >
                {/* A thin edge in the tile's tone, shown on hover. */}
                <span
                    aria-hidden
                    className="absolute inset-x-0 top-0 h-0.5 opacity-0 transition-opacity group-hover:opacity-100"
                    style={{ background: 'var(--tile-tone)' }}
                />
                <Link href={href} className="focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:outline-hidden">
                    {body}
                </Link>
            </Card>
        );
    }

    return <Card>{body}</Card>;
}
