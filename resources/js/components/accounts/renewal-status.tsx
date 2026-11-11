import Pill from '@/components/ui/pill';
import { AlertTriangle, BellRing, CalendarCheck2, PauseCircle, XCircle, type LucideIcon } from 'lucide-react';

export interface RenewalState {
    status: string;
    is_snoozed: boolean;
    open: { days_left: number; in_window?: boolean; paid: number } | null;
    remind_days_before?: number;
}

/**
 * Where a renewal stands, in the reserved status palette: overdue is
 * critical, inside its notice period is a warning, otherwise neutral. An icon
 * and words, never colour alone.
 */
export function renewalState(r: RenewalState): { label: string; icon: LucideIcon; color: string } {
    if (r.status === 'cancelled') return { label: 'Cancelled', icon: XCircle, color: 'var(--muted-foreground)' };
    if (!r.open) return { label: 'No open period', icon: CalendarCheck2, color: 'var(--muted-foreground)' };

    const d = r.open.days_left;
    const partly = r.open.paid > 0 ? ' · part paid' : '';

    if (r.is_snoozed) return { label: `Paused${partly}`, icon: PauseCircle, color: 'var(--muted-foreground)' };
    if (d < 0) return { label: `Overdue ${-d} day${d === -1 ? '' : 's'}${partly}`, icon: AlertTriangle, color: 'var(--status-critical)' };
    if (d === 0) return { label: `Due today${partly}`, icon: AlertTriangle, color: 'var(--status-critical)' };

    const inWindow = r.open.in_window ?? (r.remind_days_before !== undefined && d <= r.remind_days_before);
    if (inWindow) return { label: `${d} day${d === 1 ? '' : 's'} left${partly}`, icon: BellRing, color: 'var(--status-warning)' };

    return { label: `${d} days left${partly}`, icon: CalendarCheck2, color: 'var(--status-good)' };
}

export default function RenewalStatusBadge({ renewal, className }: { renewal: RenewalState; className?: string }) {
    const { label, icon, color } = renewalState(renewal);

    return (
        <Pill color={color} icon={icon} className={className}>
            {label}
        </Pill>
    );
}
