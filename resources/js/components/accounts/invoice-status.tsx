import Pill from '@/components/ui/pill';
import type { InvoiceStatus } from '@/types';
import { AlertTriangle, CheckCircle2, CircleDashed, Send, XCircle, type LucideIcon } from 'lucide-react';

/**
 * Invoice states use the reserved status palette: paid is good, overdue is
 * critical, cancelled is muted. Always an icon and a word, never colour alone.
 */
const spec: Record<InvoiceStatus | 'overdue', { label: string; icon: LucideIcon; color: string }> = {
    draft: { label: 'Draft', icon: CircleDashed, color: 'var(--muted-foreground)' },
    sent: { label: 'Sent', icon: Send, color: 'var(--mail-bg)' },
    overdue: { label: 'Overdue', icon: AlertTriangle, color: 'var(--status-critical)' },
    paid: { label: 'Paid', icon: CheckCircle2, color: 'var(--status-good)' },
    cancelled: { label: 'Cancelled', icon: XCircle, color: 'var(--muted-foreground)' },
};

export default function InvoiceStatusBadge({ status, overdue = false, className }: { status: InvoiceStatus; overdue?: boolean; className?: string }) {
    const { label, icon, color } = spec[overdue && status === 'sent' ? 'overdue' : status];

    return (
        <Pill color={color} icon={icon} className={className}>
            {label}
        </Pill>
    );
}
