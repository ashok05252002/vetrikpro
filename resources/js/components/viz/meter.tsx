import { cn } from '@/lib/utils';

/**
 * A single ratio against its limit. The unfilled track is a lighter step of the
 * fill's own ramp, so the state reads across the whole bar.
 */
export default function Meter({ value, label, className }: { value: number; label?: string; className?: string }) {
    const clamped = Math.max(0, Math.min(100, value));

    return (
        <div
            className={cn('h-1.5 w-full overflow-hidden rounded-full', className)}
            style={{ background: 'var(--viz-track)' }}
            role="meter"
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={label ?? `${clamped}% complete`}
        >
            <div className="h-full rounded-full transition-[width] duration-300" style={{ width: `${clamped}%`, background: 'var(--stage-done)' }} />
        </div>
    );
}
