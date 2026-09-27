import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import * as React from 'react';

interface AppContentProps extends React.ComponentProps<'div'> {
    variant?: 'header' | 'sidebar';
}

export function AppContent({ variant = 'header', children, className, ...props }: AppContentProps) {
    if (variant === 'sidebar') {
        // min-w-0: a flex child otherwise grows to its widest content, so one
        // wide table or board stretched the whole page sideways. With it, wide
        // content scrolls inside its own box and the page fits the screen.
        return (
            <SidebarInset className={cn('min-w-0', className)} {...props}>
                {children}
            </SidebarInset>
        );
    }

    return (
        <main className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl" {...props}>
            {children}
        </main>
    );
}
