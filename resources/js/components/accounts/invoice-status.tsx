import { cn } from '@/lib/utils';
import type { InvoiceStatus } from '@/types';
import { AlertTriangle, CheckCircle2, CircleDashed, Send, XCircle, type LucideIcon } from 'lucide-react';

/**
 * Invoice states use the reserved status palette: paid is good, overdue is
 * critical, cancelled is muted. Always an icon and a word, never colour alone.
 */
const spec: Record<InvoiceStatus | 'overdue', { label: string; icon: LucideIcon; className: string }> = {
    draft: { label: 'Draft', icon: CircleDashed, className: 'text-muted-foreground' },
    sent: { label: 'Sent', icon: Send, className: 'text-[var(--stage-in-review)]' },
    overdue: { label: 'Overdue', icon: AlertTriangle, className: 'text-[var(--status-critical)]' },
    paid: { label: 'Paid', icon: CheckCircle2, className: 'text-[var(--status-good)]' },
    cancelled: { label: 'Cancelled', icon: XCircle, className: 'text-muted-foreground line-through' },
};

export default function InvoiceStatusBadge({ status, overdue = false, className }: { status: InvoiceStatus; overdue?: boolean; className?: string }) {
    const { label, icon: Icon, className: tone } = spec[overdue && status === 'sent' ? 'overdue' : status];

    return (
        <span className={cn('inline-flex items-center gap-1.5 text-xs font-medium', className)}>
            <Icon aria-hidden className={cn('size-3.5 shrink-0', tone.replace('line-through', ''))} />
            <span className={status === 'cancelled' ? 'text-muted-foreground' : 'text-foreground'}>{label}</span>
        </span>
    );
}
