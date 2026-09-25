import { useEffect, useState } from 'react';

/**
 * Debounced JSON search against an endpoint returning `{ data: T[] }`.
 * Stale responses are dropped, so fast typing never shows an older result.
 */
export function useLookup<T>(url: string, params: Record<string, string | number | undefined>, enabled = true) {
    const [results, setResults] = useState<T[]>([]);
    const [loading, setLoading] = useState(false);
    const key = JSON.stringify(params);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const controller = new AbortController();
        const query = new URLSearchParams(
            Object.entries(JSON.parse(key) as Record<string, string | number | undefined>)
                .filter(([, value]) => value !== undefined && value !== '')
                .map(([name, value]) => [name, String(value)]),
        );

        setLoading(true);

        const timer = setTimeout(() => {
            fetch(`${url}?${query}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => (response.ok ? response.json() : { data: [] }))
                .then((body: { data: T[] }) => setResults(body.data))
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [url, key, enabled]);

    return { results, loading };
}
