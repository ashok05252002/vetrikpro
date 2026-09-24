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

export default function UserAvatar({ name, className }: { name?: string | null; className?: string }) {
    return (
        <Avatar className={cn('size-6', className)}>
            <AvatarFallback className="bg-muted text-muted-foreground text-[10px] font-medium">{name ? initials(name) : '—'}</AvatarFallback>
        </Avatar>
    );
}
