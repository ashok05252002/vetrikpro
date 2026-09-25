import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

/**
 * Destructive action behind a confirmation dialog — never a bare browser confirm(),
 * which would block the page.
 */
interface Props {
    url: string;
    label: string;
    description?: string;
    /** The verb, for actions that detach rather than destroy, e.g. "Remove". */
    verb?: string;
}

export default function DeleteButton({ url, label, description, verb = 'Delete' }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm();

    const confirm = () => {
        destroy(url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                    <Trash2 className="size-4" />
                    <span className="sr-only">{verb}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {verb} {label}?
                    </DialogTitle>
                    <DialogDescription>{description ?? 'This action cannot be undone.'}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" disabled={processing} onClick={confirm}>
                        {verb}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
