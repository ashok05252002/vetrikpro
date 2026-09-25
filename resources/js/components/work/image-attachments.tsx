import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useFormat } from '@/hooks/use-format';
import { formatBytes } from '@/lib/files';
import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

export interface ImageAttachment {
    id: number;
    original_name: string;
    size: number;
    created_at: string;
    url: string;
    uploaded_by: { id: number; name: string } | null;
    can_delete: boolean;
}

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_BYTES = 2 * 1024 * 1024;

interface Props {
    attachments: ImageAttachment[];
    uploadUrl: string;
    deleteUrl: (id: number) => string;
    canUpload: boolean;
}

/**
 * Evidence images: a thumbnail grid, a larger view on click, and an upload
 * that refuses non-images and anything over 2 MB before it leaves the browser.
 * The server checks again; this only saves a wasted upload.
 */
export default function ImageAttachments({ attachments, uploadUrl, deleteUrl, canUpload }: Props) {
    const format = useFormat();
    const input = useRef<HTMLInputElement>(null);
    const picked = useRef<File[]>([]);
    const [clientError, setClientError] = useState<string | null>(null);
    const [viewing, setViewing] = useState<ImageAttachment | null>(null);
    const { post, processing, errors, progress, transform, clearErrors } = useForm<{ images: File[] }>({ images: [] });

    transform(() => ({ images: picked.current }));

    const choose = (files: FileList | null) => {
        clearErrors();
        const list = Array.from(files ?? []);
        const wrongType = list.find((f) => !ALLOWED.includes(f.type));
        const tooBig = list.find((f) => f.size > MAX_BYTES);

        if (wrongType) {
            setClientError(`“${wrongType.name}” isn’t an image. Only JPG, PNG, WebP or GIF — no videos or documents.`);
            return;
        }
        if (tooBig) {
            setClientError(`“${tooBig.name}” is ${formatBytes(tooBig.size)}. Each image must be 2 MB or smaller.`);
            return;
        }

        setClientError(null);
        picked.current = list;
        if (list.length > 0) {
            post(uploadUrl, { forceFormData: true, preserveScroll: true });
        }
    };

    const serverError = errors.images ?? Object.entries(errors).find(([key]) => key.startsWith('images.'))?.[1];

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 pb-3">
                <div className="space-y-1">
                    <CardTitle className="text-sm">Attachments</CardTitle>
                    <CardDescription className="text-xs">Screenshots or photos — images only, up to 2 MB each.</CardDescription>
                </div>
                {canUpload && (
                    <>
                        <input
                            ref={input}
                            type="file"
                            multiple
                            accept={ALLOWED.join(',')}
                            className="hidden"
                            onChange={(e) => {
                                choose(e.target.files);
                                e.target.value = '';
                            }}
                        />
                        <Button size="sm" variant="outline" disabled={processing} onClick={() => input.current?.click()}>
                            <ImagePlus className="size-4" /> {processing ? `Uploading… ${progress?.percentage ?? 0}%` : 'Add images'}
                        </Button>
                    </>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {(clientError || serverError) && <p className="text-destructive text-sm">{clientError ?? serverError}</p>}

                {attachments.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No images yet.</p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {attachments.map((attachment) => (
                            <li key={attachment.id} className="group relative overflow-hidden rounded-lg border">
                                <button
                                    type="button"
                                    onClick={() => setViewing(attachment)}
                                    className="block w-full"
                                    aria-label={`View ${attachment.original_name}`}
                                >
                                    <img
                                        src={attachment.url}
                                        alt={attachment.original_name}
                                        loading="lazy"
                                        className="bg-muted aspect-video w-full object-cover"
                                    />
                                </button>
                                <div className="flex items-center justify-between gap-1 px-2 py-1.5">
                                    <span className="text-muted-foreground min-w-0 truncate text-[11px]">{attachment.original_name}</span>
                                    {attachment.can_delete && (
                                        <button
                                            type="button"
                                            onClick={() => router.delete(deleteUrl(attachment.id), { preserveScroll: true })}
                                            className="text-muted-foreground hover:text-destructive shrink-0"
                                            aria-label={`Remove ${attachment.original_name}`}
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            <Dialog open={viewing !== null} onOpenChange={(open) => !open && setViewing(null)}>
                <DialogContent className="max-w-4xl">
                    {viewing && (
                        <>
                            <DialogTitle className="truncate text-sm">{viewing.original_name}</DialogTitle>
                            <DialogDescription className="text-xs">
                                {formatBytes(viewing.size)} · added {format.date(viewing.created_at)}
                                {viewing.uploaded_by && ` by ${viewing.uploaded_by.name}`}
                            </DialogDescription>
                            <img src={viewing.url} alt={viewing.original_name} className="max-h-[70vh] w-full rounded-md object-contain" />
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </Card>
    );
}
