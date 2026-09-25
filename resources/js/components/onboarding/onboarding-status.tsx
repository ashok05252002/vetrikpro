import { cn } from '@/lib/utils';
import type { OnboardingStatus } from '@/types';
import type { LucideIcon } from 'lucide-react';
import { CheckCircle2, CircleDashed, Hourglass, Mail, Undo2 } from 'lucide-react';

/** Each state carries an icon and the word; colour never stands alone. */
export const onboardingSpec: Record<OnboardingStatus, { label: string; color: string; icon: LucideIcon }> = {
    invited: { label: 'Invited', color: 'var(--tone-sky)', icon: Mail },
    in_progress: { label: 'In progress', color: 'var(--tone-indigo)', icon: CircleDashed },
    submitted: { label: 'Awaiting review', color: 'var(--tone-amber)', icon: Hourglass },
    returned: { label: 'Sent back', color: 'var(--tone-red)', icon: Undo2 },
    completed: { label: 'Completed', color: 'var(--status-good)', icon: CheckCircle2 },
};

export default function OnboardingBadge({ status, className }: { status: OnboardingStatus; className?: string }) {
    const { label, color, icon: Icon } = onboardingSpec[status];

    return (
        <span className={cn('text-foreground inline-flex items-center gap-1.5 text-xs font-medium whitespace-nowrap', className)}>
            <Icon aria-hidden className="size-3.5 shrink-0" style={{ color }} />
            {label}
        </span>
    );
}
