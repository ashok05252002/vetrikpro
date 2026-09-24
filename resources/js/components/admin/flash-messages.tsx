import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Turns Laravel's session flash into a toast. Mounted once in the app layout;
 * the key makes a repeated identical message fire again.
 */
export default function FlashMessages() {
    const page = usePage<SharedData>();
    const flash = page.props.flash;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error, page.props]);

    return null;
}
