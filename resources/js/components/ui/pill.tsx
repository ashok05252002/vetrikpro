import { cn } from '@/lib/utils';
import type { ComponentType, CSSProperties, ReactNode } from 'react';

/**
 * The one badge shape for a state: a soft tint of its colour, the icon in the
 * colour, the word in ink. Every status and priority badge uses it, so they
 * all read alike. The word carries the meaning; colour only helps.
 */
export default function Pill({
    color,
    icon: Icon,
    dot = false,
    children,
    className,
}: {
    color: string;
    icon?: ComponentType<{ className?: string; style?: CSSProperties; 'aria-hidden'?: boolean }>;
    /** A dot instead of an icon (ordinal stages). */
    dot?: boolean;
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn('text-foreground inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap', className)}
            style={{ background: `color-mix(in oklab, ${color} 14%, transparent)`, boxShadow: `inset 0 0 0 1px color-mix(in oklab, ${color} 28%, transparent)` }}
        >
            {Icon && <Icon aria-hidden className="size-3.5 shrink-0" style={{ color }} />}
            {dot && <span aria-hidden className="size-2 shrink-0 rounded-full" style={{ background: color }} />}
            {children}
        </span>
    );
}
