import CompanyMark from '@/components/company-mark';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * The sidebar mark. The sidebar is navy in both themes, so it takes the
 * dark-background logo (see CompanyMark for the fallbacks).
 */
export default function AppLogo() {
    const { company } = usePage<SharedData>().props;

    return (
        <>
            <CompanyMark surface="dark" className="size-8" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="text-sidebar-accent-foreground mb-0.5 truncate leading-none font-semibold">{company.name}</span>
            </div>
        </>
    );
}
