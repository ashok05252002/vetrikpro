import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/** Identity tones only — never the status reds and ambers. */
const TONES = ['indigo', 'violet', 'sky', 'teal', 'pink', 'green', 'blue'];

/** The same person always gets the same colour, everywhere. */
function toneFor(name: string): string {
    let hash = 0;
    for (const char of name) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return TONES[hash % TONES.length];
}

export default function UserAvatar({ name, className }: { name?: string | null; className?: string }) {
    const tone = name ? toneFor(name) : null;

    return (
        <Avatar className={cn('size-6', className)}>
            <AvatarFallback
                className={cn('text-[10px] font-semibold', !tone && 'bg-muted text-muted-foreground')}
                style={tone ? { color: `var(--tone-${tone})`, background: `color-mix(in oklab, var(--tone-${tone}) 16%, transparent)` } : undefined}
            >
                {name ? initials(name) : '—'}
            </AvatarFallback>
        </Avatar>
    );
}
