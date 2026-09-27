import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { Power, PowerOff } from 'lucide-react';
import { useState } from 'react';

interface Props {
    /** The PATCH route that takes { is_active }. */
    url: string;
    active: boolean;
}

/**
 * Switch a master-data row on or off. Off only takes it out of pickers —
 * nothing that already uses it changes — so neither way needs confirming.
 */
export default function ActiveToggle({ url, active }: Props) {
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.patch(url, { is_active: !active }, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });

    return (
        <TooltipProvider delayDuration={200}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={submit}
                        disabled={processing}
                        className={active ? 'text-muted-foreground' : 'text-emerald-700 dark:text-emerald-400'}
                    >
                        {active ? <PowerOff className="size-4" /> : <Power className="size-4" />}
                        <span className="sr-only">{active ? 'Mark inactive' : 'Mark active'}</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{active ? 'Mark inactive — stop offering it' : 'Mark active again'}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/** The state beside a master-data row's name. Only inactive rows are labelled. */
export function InactiveBadge({ active }: { active: boolean }) {
    return active ? null : <span className="text-muted-foreground ml-2 rounded-full border px-2 py-0.5 text-[11px] font-medium">Inactive</span>;
}
