import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

export interface StatusChangeRow {
    id: number;
    from_status: string | null;
    to_status: string;
    created_at: string;
    user: { id: number; name: string } | null;
}

/**
 * Every status change, oldest first: who moved it, from what, to what, when.
 * The first line is the status it was created in.
 */
export default function StatusHistory({ history, badge }: { history: StatusChangeRow[]; badge: (status: string) => ReactNode }) {
    const format = useFormat();

    if (history.length === 0) {
        return <p className="text-muted-foreground text-sm">No status changes yet.</p>;
    }

    return (
        <ol className="space-y-3">
            {history.map((change) => (
                <li key={change.id} className="flex items-start gap-3">
                    <UserAvatar name={change.user?.name} className="mt-0.5 size-6" />
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-1.5 text-sm">
                            <span className="font-medium">{change.user?.name ?? 'System'}</span>
                            {change.from_status ? (
                                <>
                                    <span className="text-muted-foreground">moved it</span>
                                    {badge(change.from_status)}
                                    <ArrowRight className="text-muted-foreground size-3.5" aria-label="to" />
                                    {badge(change.to_status)}
                                </>
                            ) : (
                                <>
                                    <span className="text-muted-foreground">created it in</span>
                                    {badge(change.to_status)}
                                </>
                            )}
                        </div>
                        <p className="text-muted-foreground text-xs">{format.dateTime(change.created_at)}</p>
                    </div>
                </li>
            ))}
        </ol>
    );
}
