import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { Power, PowerOff } from 'lucide-react';
import { useState } from 'react';

interface Props {
    /** The employee whose login this is. */
    employeeId: number;
    name: string;
    active: boolean;
    size?: 'sm' | 'default';
}

/**
 * Switch someone's portal access. Turning it off asks first, because it signs
 * them out straight away; turning it back on does not need confirming.
 */
export default function AccessToggle({ employeeId, name, active, size = 'sm' }: Props) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const submit = (isActive: boolean) =>
        router.patch(
            route('admin.employees.status', employeeId),
            { is_active: isActive },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                },
            },
        );

    if (!active) {
        return (
            <Button variant="outline" size={size} onClick={() => submit(true)} disabled={processing}>
                <Power className="size-4" /> Activate
            </Button>
        );
    }

    return (
        <>
            <Button variant="outline" size={size} className="text-destructive hover:text-destructive" onClick={() => setConfirming(true)}>
                <PowerOff className="size-4" /> Deactivate
            </Button>
            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Deactivate {name}?</DialogTitle>
                        <DialogDescription>
                            They are signed out on their next click and can’t sign in again until reactivated. Their records, documents and tasks stay
                            as they are.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" onClick={() => submit(false)} disabled={processing}>
                            Deactivate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
