import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

/**
 * The sidebar mark. Shows the uploaded company logo when there is one, and
 * falls back to the default icon otherwise — never a broken image.
 */
export default function AppLogo() {
    const { company } = usePage<SharedData>().props;

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
                {company.logo ? (
                    <img src={company.logo} alt="" className="size-full object-contain" />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">{company.name}</span>
            </div>
        </>
    );
}
