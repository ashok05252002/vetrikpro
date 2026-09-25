import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export const DOCUMENT_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.md';

type Form = { title: string; description: string; file: File | null; change_note: string };

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    title: string;
    description: string;
    /** A new document asks for a title; a new version of one does not. */
    withTitle?: boolean;
    /** A new version must say what changed. */
    noteRequired?: boolean;
    submitLabel: string;
}

/**
 * Upload a requirement document, or a new version of one.
 */
export default function FileUploadDialog({
    open,
    onOpenChange,
    url,
    title,
    description,
    withTitle = false,
    noteRequired = false,
    submitLabel,
}: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors, progress } = useForm<Form>({
        title: '',
        description: '',
        file: null,
        change_note: '',
    });

    useEffect(() => {
        if (open) {
            reset();
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>

                    {withTitle && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="req-title">Title</Label>
                                <Input
                                    id="req-title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    required
                                    placeholder="Leave management — functional spec"
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="req-description">
                                    Description <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Textarea
                                    id="req-description"
                                    rows={2}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} />
                            </div>
                        </>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="req-file">File</Label>
                        <Input
                            id="req-file"
                            type="file"
                            required
                            accept={DOCUMENT_ACCEPT}
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                setData((current) => ({
                                    ...current,
                                    file,
                                    title: current.title || (withTitle && file ? file.name.replace(/\.[^.]+$/, '') : current.title),
                                }));
                            }}
                        />
                        <p className="text-muted-foreground text-xs">PDF, Office, image, text or Markdown, up to 10 MB.</p>
                        <InputError message={errors.file} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="req-note">
                            {noteRequired ? 'What changed' : 'Note'} {!noteRequired && <span className="text-muted-foreground">(optional)</span>}
                        </Label>
                        <Textarea
                            id="req-note"
                            rows={2}
                            required={noteRequired}
                            value={data.change_note}
                            onChange={(e) => setData('change_note', e.target.value)}
                            placeholder={noteRequired ? 'Added half-day leave; carry-forward capped at 10 days.' : 'First version'}
                        />
                        <InputError message={errors.change_note} />
                    </div>

                    {progress && (
                        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                            <div className="bg-primary h-full transition-[width]" style={{ width: `${progress.percentage ?? 0}%` }} />
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
