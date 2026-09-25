import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Check, Copy, Link2 } from 'lucide-react';
import { useState } from 'react';

/**
 * While email only goes to the log, the invite would reach nobody. Show HR
 * the link so they can pass it on until SMTP is configured.
 */
export default function InviteLinkNotice() {
    const { flash } = usePage<SharedData>().props;
    const [copied, setCopied] = useState(false);

    if (!flash.invite_link) {
        return null;
    }

    const copy = () => {
        navigator.clipboard?.writeText(flash.invite_link!).then(() => setCopied(true));
    };

    return (
        <Alert>
            <Link2 className="size-4" />
            <AlertDescription className="space-y-2">
                <p>
                    Email isn’t connected yet (<code>MAIL_MAILER=log</code>), so the invite was written to the log instead of being sent. Send this
                    link to them yourself — it works once, for 72 hours:
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <code className="bg-muted max-w-full truncate rounded px-2 py-1 text-xs">{flash.invite_link}</code>
                    <Button type="button" variant="outline" size="sm" onClick={copy}>
                        {copied ? <Check className="size-4" /> : <Copy className="size-4" />} {copied ? 'Copied' : 'Copy'}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}
