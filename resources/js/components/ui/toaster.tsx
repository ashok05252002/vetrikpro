import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner } from 'sonner';

export default function Toaster() {
    const { appearance } = useAppearance();

    return (
        <Sonner
            position="bottom-right"
            theme={appearance === 'system' ? 'system' : appearance}
            toastOptions={{
                classNames: {
                    toast: 'bg-background text-foreground border border-border shadow-lg',
                    description: 'text-muted-foreground',
                },
            }}
        />
    );
}
