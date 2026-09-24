export function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * "Today" / "In 3 days" / "5 days late" — a due date read the way people
 * actually ask about it.
 */
export function relativeDue(value?: string | null): string {
    if (!value) {
        return 'No due date';
    }

    const due = new Date(`${value}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = Math.round((due.getTime() - today.getTime()) / 86_400_000);

    if (days === 0) return 'Today';
    if (days === 1) return 'Tomorrow';
    if (days === -1) return '1 day late';
    if (days < 0) return `${Math.abs(days)} days late`;
    if (days <= 14) return `In ${days} days`;

    return formatDate(value);
}
