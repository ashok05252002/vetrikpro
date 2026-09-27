import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { Archive, ArchiveRestore } from 'lucide-react';
import { useState } from 'react';

interface Props {
    employeeId: number;
    name: string;
    archived: boolean;
    /** Icon-only, for table rows. */
    compact?: boolean;
}

/**
 * Archive someone who has left, or bring them back. Archiving asks first,
 * because it signs them out; restoring does not need confirming.
 */
export default function ArchiveButton({ employeeId, name, archived, compact = false }: Props) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const submit = (action: 'archive' | 'restore') =>
        router.post(
            route(`admin.employees.${action}`, employeeId),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                },
            },
        );

    if (archived) {
        return (
            <Button variant="outline" size="sm" onClick={() => submit('restore')} disabled={processing}>
                <ArchiveRestore className="size-4" />
                {compact ? <span className="sr-only">Restore {name}</span> : 'Restore'}
            </Button>
        );
    }

    return (
        <>
            <Button variant={compact ? 'ghost' : 'outline'} size="sm" onClick={() => setConfirming(true)} title={compact ? 'Archive' : undefined}>
                <Archive className="size-4" />
                {compact ? <span className="sr-only">Archive {name}</span> : 'Archive'}
            </Button>
            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Archive {name}?</DialogTitle>
                        <DialogDescription>
                            They are signed out and can’t sign in, and they leave the employee list and every picker. Their HR record, documents and
                            the tasks and bugs they worked on all stay, under their name. You can restore them at any time.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button onClick={() => submit('archive')} disabled={processing}>
                            Archive
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
