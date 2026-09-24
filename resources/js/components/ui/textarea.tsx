import { cn } from '@/lib/utils';
import * as React from 'react';

const Textarea = React.forwardRef<HTMLTextAreaElement, React.ComponentProps<'textarea'>>(({ className, ...props }, ref) => (
    <textarea
        ref={ref}
        className={cn(
            'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-colors',
            'placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-hidden',
            'disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            className,
        )}
        {...props}
    />
));
Textarea.displayName = 'Textarea';

export { Textarea };
