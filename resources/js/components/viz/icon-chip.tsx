import { cn } from '@/lib/utils';
import type { ComponentType } from 'react';

export type Tone = 'indigo' | 'violet' | 'sky' | 'teal' | 'pink' | 'amber' | 'red';

/**
 * An icon on a soft tint of its tone. Colour here marks identity (which tile,
 * which card) — the label beside it always carries the meaning. Amber and red
 * are the status hues: keep them for tiles that are about urgency.
 */
export default function IconChip({
    icon: Icon,
    tone,
    size = 'md',
    className,
}: {
    icon: ComponentType<{ className?: string }>;
    tone: Tone;
    size?: 'sm' | 'md';
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn('inline-flex shrink-0 items-center justify-center rounded-lg', size === 'md' ? 'size-10' : 'size-8', className)}
            style={{ color: `var(--tone-${tone})`, background: `color-mix(in oklab, var(--tone-${tone}) var(--tone-tint), transparent)` }}
        >
            <Icon className={size === 'md' ? 'size-5' : 'size-4'} />
        </span>
    );
}
