import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface Props {
    /**
     * What the mark sits on. `dark` for the navy sidebar and sign-in panel
     * (navy in both themes); `auto` follows the light/dark theme.
     */
    surface: 'light' | 'dark' | 'auto';
    className?: string;
}

/**
 * The company's mark, chosen for its background: the dark-background logo on
 * dark surfaces, the light one elsewhere. A light logo on a dark surface gets
 * a white tile so it stays legible; with no logo at all, the default icon on
 * the brand gradient. Never a broken image.
 */
export default function CompanyMark({ surface, className }: Props) {
    const { company } = usePage<SharedData>().props;
    const { logo, logo_dark: logoDark } = company;

    const tile = 'flex shrink-0 items-center justify-center overflow-hidden rounded-lg';

    if (!logo && !logoDark) {
        return (
            <div
                className={cn(tile, 'text-white shadow-sm', className)}
                style={{ background: 'linear-gradient(135deg, var(--hero-from), var(--hero-to))' }}
            >
                <AppLogoIcon className="size-3/5 fill-current" />
            </div>
        );
    }

    const onLight = logo ? <img src={logo} alt="" className="size-full object-contain" /> : null;
    const onDark = logoDark ? (
        <img src={logoDark} alt="" className="size-full object-contain" />
    ) : (
        // No dark version: the light logo on a white tile.
        <span className="flex size-full items-center justify-center rounded-lg bg-white p-1">
            <img src={logo!} alt="" className="size-full object-contain" />
        </span>
    );

    if (surface === 'dark') {
        return <div className={cn(tile, className)}>{onDark}</div>;
    }

    if (surface === 'light' || !onLight) {
        // Light surface, or no light logo to show: the dark one on a navy tile.
        return <div className={cn(tile, !onLight && 'bg-sidebar p-1', className)}>{onLight ?? onDark}</div>;
    }

    return (
        <div className={cn(tile, className)}>
            <span className="contents dark:hidden">{onLight}</span>
            <span className="hidden size-full dark:contents">{onDark}</span>
        </div>
    );
}
