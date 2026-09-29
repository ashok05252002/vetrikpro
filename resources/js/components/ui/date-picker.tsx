import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useFormat } from '@/hooks/use-format';
import { todayIn } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    id?: string;
    /** YYYY-MM-DD, or '' for none. */
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    /** Earliest / latest allowed day, YYYY-MM-DD. */
    min?: string;
    max?: string;
    /** No day after today (dates of birth, payments received). */
    notAfterToday?: boolean;
    /** Required fields cannot be cleared. */
    required?: boolean;
    disabled?: boolean;
    className?: string;
}

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const pad = (n: number) => String(n).padStart(2, '0');
const iso = (y: number, m: number, d: number) => `${y}-${pad(m + 1)}-${pad(d)}`;

/**
 * A date field with a calendar that opens from it. Dates travel as
 * YYYY-MM-DD, exactly as the native date input sent them, and show in the
 * organisation's date format. Today is the organisation's today.
 */
export default function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Pick a date',
    min,
    max,
    notAfterToday = false,
    required = false,
    disabled = false,
    className,
}: Props) {
    const format = useFormat();
    const { display } = usePage<SharedData>().props;
    const today = todayIn(display.timezone);
    const [open, setOpen] = useState(false);
    const start = value || today;
    const [cursor, setCursor] = useState({ y: Number(start.slice(0, 4)), m: Number(start.slice(5, 7)) - 1 });

    const weeks = useMemo(() => {
        const first = new Date(Date.UTC(cursor.y, cursor.m, 1));
        const lead = (first.getUTCDay() + 6) % 7; // Monday first
        const days = new Date(Date.UTC(cursor.y, cursor.m + 1, 0)).getUTCDate();
        const cells: (number | null)[] = [...Array(lead).fill(null), ...Array.from({ length: days }, (_, i) => i + 1)];
        while (cells.length % 7) cells.push(null);
        return Array.from({ length: cells.length / 7 }, (_, w) => cells.slice(w * 7, w * 7 + 7));
    }, [cursor]);

    const move = (delta: number) => setCursor(({ y, m }) => ({ y: y + Math.floor((m + delta) / 12), m: (((m + delta) % 12) + 12) % 12 }));
    const latest = notAfterToday ? (max && max < today ? max : today) : max;
    const allowed = (day: string) => (!min || day >= min) && (!latest || day <= latest);
    const pick = (day: string) => {
        onChange(day);
        setOpen(false);
    };

    const monthLabel = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(cursor.y, cursor.m, 1)));

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    const s = value || today;
                    setCursor({ y: Number(s.slice(0, 4)), m: Number(s.slice(5, 7)) - 1 });
                }
            }}
        >
            <div className={cn('relative', className)}>
                <PopoverTrigger asChild>
                    <button
                        id={id}
                        type="button"
                        disabled={disabled}
                        aria-haspopup="dialog"
                        className={cn(
                            'border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full items-center gap-2 rounded-md border px-3 text-left text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                            !value && 'text-muted-foreground',
                            value && !required && 'pr-8',
                        )}
                    >
                        <CalendarDays className="size-4 shrink-0" style={{ color: 'var(--tone-sky)' }} aria-hidden />
                        <span className="truncate">{value ? format.date(value) : placeholder}</span>
                    </button>
                </PopoverTrigger>
                {value && !required && !disabled && (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 flex size-5 -translate-y-1/2 items-center justify-center rounded"
                        aria-label="Clear date"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            <PopoverContent className="w-72">
                <div className="mb-2 flex items-center justify-between">
                    <Button type="button" variant="ghost" size="sm" className="size-8 p-0" onClick={() => move(-1)} aria-label="Previous month">
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="text-sm font-semibold">{monthLabel}</span>
                    <Button type="button" variant="ghost" size="sm" className="size-8 p-0" onClick={() => move(1)} aria-label="Next month">
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                <table className="w-full table-fixed text-center text-sm" role="grid" aria-label={monthLabel}>
                    <thead>
                        <tr>
                            {WEEKDAYS.map((d) => (
                                <th key={d} className="text-muted-foreground pb-1 text-[11px] font-medium">
                                    {d}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {weeks.map((week, w) => (
                            <tr key={w}>
                                {week.map((day, i) => {
                                    if (day === null) return <td key={i} />;
                                    const key = iso(cursor.y, cursor.m, day);
                                    const selected = key === value;
                                    const isToday = key === today;
                                    return (
                                        <td key={i} className="p-0.5">
                                            <button
                                                type="button"
                                                disabled={!allowed(key)}
                                                onClick={() => pick(key)}
                                                aria-pressed={selected}
                                                aria-label={format.date(key)}
                                                className={cn(
                                                    'hover:bg-accent flex size-8 w-full items-center justify-center rounded-md text-sm tabular-nums transition-colors disabled:pointer-events-none disabled:opacity-30',
                                                    selected && 'bg-primary text-primary-foreground hover:bg-primary',
                                                    !selected && isToday && 'text-primary font-semibold ring-1 ring-[var(--ring)]',
                                                )}
                                            >
                                                {day}
                                            </button>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-2 flex items-center justify-between border-t pt-2">
                    <Button type="button" variant="ghost" size="sm" disabled={!allowed(today)} onClick={() => pick(today)}>
                        Today
                    </Button>
                    {!required && value && (
                        <Button type="button" variant="ghost" size="sm" className="text-muted-foreground" onClick={() => pick('')}>
                            Clear
                        </Button>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
