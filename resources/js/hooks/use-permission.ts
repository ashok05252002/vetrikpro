import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Checks the signed-in user's resolved permissions, for showing or hiding UI.
 * The server enforces every one of these independently.
 */
export function usePermission() {
    const { auth } = usePage<SharedData>().props;
    const granted = new Set(auth.permissions ?? []);

    return {
        can: (key: string) => granted.has(key),
        canAny: (...keys: string[]) => keys.some((key) => granted.has(key)),
    };
}
