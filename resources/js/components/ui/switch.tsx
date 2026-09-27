import { cn } from '@/lib/utils';
import * as React from 'react';

interface SwitchProps extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'onChange'> {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}

/**
 * An on/off toggle: a button with role="switch", so screen readers announce
 * it as on or off and Space/Enter flip it. No extra dependency.
 */
const Switch = React.forwardRef<HTMLButtonElement, SwitchProps>(({ checked, onCheckedChange, className, disabled, ...props }, ref) => (
    <button
        ref={ref}
        type="button"
        role="switch"
        aria-checked={checked}
        data-state={checked ? 'checked' : 'unchecked'}
        disabled={disabled}
        onClick={() => onCheckedChange(!checked)}
        className={cn(
            'focus-visible:ring-ring focus-visible:ring-offset-background inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
            checked ? 'bg-primary' : 'bg-input',
            className,
        )}
        {...props}
    >
        <span
            aria-hidden
            className={cn('bg-background pointer-events-none block size-4 rounded-full shadow-sm ring-0 transition-transform', checked ? 'translate-x-4' : 'translate-x-0')}
        />
    </button>
));
Switch.displayName = 'Switch';

export { Switch };
