import { Button } from '@/components/ui/button';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import { Link } from '@inertiajs/react';
import { ArrowLeft, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    action?: ReactNode;
    /** Where the Back button goes — the parent page, never browser history, so a deep link still has a way up. */
    back?: string;
    /** The section's icon, in the section's colour (see lib/sections.ts), so every page says where you are. */
    icon?: LucideIcon;
    tone?: Tone;
}

export default function PageHeader({ title, description, action, back, icon, tone = 'indigo' }: PageHeaderProps) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex min-w-0 items-start gap-3">
                {back && (
                    <Button asChild variant="outline" size="icon" className="shrink-0">
                        <Link href={back} aria-label="Back">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                )}
                {icon && <IconChip icon={icon} tone={tone} className="hidden sm:inline-flex" />}
                <div className="min-w-0 space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                    {description && <p className="text-muted-foreground text-sm">{description}</p>}
                </div>
            </div>
            {action}
        </div>
    );
}
