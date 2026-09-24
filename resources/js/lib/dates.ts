import type { DateFormat, DisplaySettings } from '@/types';

const DEFAULTS: DisplaySettings = { timezone: 'UTC', dateFormat: 'dmy', currency: 'INR' };

function dateOptions(format: DateFormat): Intl.DateTimeFormatOptions {
    switch (format) {
        case 'mdy':
            return { month: 'short', day: 'numeric', year: 'numeric' };
        case 'ymd':
            return { year: 'numeric', month: '2-digit', day: '2-digit' };
        default:
            return { day: 'numeric', month: 'short', year: 'numeric' };
    }
}

/** 'ymd' is only unambiguous in an ISO-style locale, so pin it. */
function locale(format: DateFormat): string | undefined {
    return format === 'ymd' ? 'en-CA' : undefined;
}

export function formatDate(value?: string | null, settings: DisplaySettings = DEFAULTS): string {
    if (!value) {
        return '—';
    }

    // A bare Y-M-D is a calendar date, not an instant: read it as local noon so
    // no timezone can shift it onto the previous or next day.
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T12:00:00`) : new Date(value);

    return date.toLocaleDateString(locale(settings.dateFormat), dateOptions(settings.dateFormat));
}

export function formatDateTime(value?: string | null, settings: DisplaySettings = DEFAULTS): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(locale(settings.dateFormat), {
        ...dateOptions(settings.dateFormat),
        hour: '2-digit',
        minute: '2-digit',
        // An instant is rendered in the organisation's timezone; a calendar
        // date above is not, because it has no timezone to begin with.
        timeZone: settings.timezone || undefined,
    });
}

/**
 * "Today" / "In 3 days" / "5 days late" — a due date read the way people
 * actually ask about it.
 */
export function relativeDue(value?: string | null, settings: DisplaySettings = DEFAULTS): string {
    if (!value) {
        return 'No due date';
    }

    const due = new Date(`${value}T12:00:00`);
    const today = new Date();
    today.setHours(12, 0, 0, 0);

    const days = Math.round((due.getTime() - today.getTime()) / 86_400_000);

    if (days === 0) return 'Today';
    if (days === 1) return 'Tomorrow';
    if (days === -1) return '1 day late';
    if (days < 0) return `${Math.abs(days)} days late`;
    if (days <= 14) return `In ${days} days`;

    return formatDate(value, settings);
}

export function formatMoney(value?: string | number | null, settings: DisplaySettings = DEFAULTS): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const amount = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(amount)) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: settings.currency }).format(amount);
    } catch {
        // An unknown ISO code should degrade to a plain number, not an exception.
        return `${settings.currency} ${amount.toLocaleString()}`;
    }
}
