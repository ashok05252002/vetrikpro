import { formatDate, formatDateTime, formatMoney, relativeDue } from '@/lib/dates';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Date and money formatters bound to the organisation's display settings, so a
 * page never has to thread them through by hand.
 */
export function useFormat() {
    const { display } = usePage<SharedData>().props;

    return {
        date: (value?: string | null) => formatDate(value, display),
        dateTime: (value?: string | null) => formatDateTime(value, display),
        due: (value?: string | null) => relativeDue(value, display),
        money: (value?: string | number | null) => formatMoney(value, display),
    };
}
