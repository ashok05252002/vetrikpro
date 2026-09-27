import { cn } from '@/lib/utils';
import type { ComponentType } from 'react';

export type Tone = 'indigo' | 'violet' | 'sky' | 'teal' | 'pink' | 'amber' | 'red' | 'green' | 'blue' | 'slate' | 'github' | 'git';

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
    size?: 'xs' | 'sm' | 'md';
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-lg',
                size === 'md' ? 'size-10' : size === 'sm' ? 'size-8' : 'size-7 rounded-md',
                className,
            )}
            style={{ color: `var(--tone-${tone})`, background: `color-mix(in oklab, var(--tone-${tone}) var(--tone-tint), transparent)` }}
        >
            <Icon className={size === 'md' ? 'size-5' : size === 'sm' ? 'size-4' : 'size-3.5'} />
        </span>
    );
}
