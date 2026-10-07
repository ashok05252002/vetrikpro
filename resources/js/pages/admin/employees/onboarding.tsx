import InputError from '@/components/input-error';
import InviteLinkNotice from '@/components/onboarding/invite-link-notice';
import OnboardingBadge from '@/components/onboarding/onboarding-status';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import CardHeading from '@/components/ui/card-heading';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import Meter from '@/components/viz/meter';
import { useFormat } from '@/hooks/use-format';
import EmployeeProfileLayout from '@/layouts/employee/profile-layout';
import { formatBytes } from '@/lib/files';
import type { EmployeeProfileHeader, OnboardingState } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, Circle, Download, FileSignature, FileUp, Landmark, ListChecks, Mail, Undo2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    employee: EmployeeProfileHeader;
    onboarding: OnboardingState | null;
    mailIsLocal: boolean;
    letterKinds: { kind: string; label: string }[];
    can: { manage: boolean; documents: boolean; offer_letter: boolean };
}

function Row({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="space-y-0.5">
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

function SendBackDialog({ employeeId, open, onOpenChange }: { employeeId: number; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ note: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.employees.onboarding.send-back', employeeId), { preserveScroll: true, onSuccess: () => (reset(), onOpenChange(false)) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Send back for changes</DialogTitle>
                        <DialogDescription>They’re taken back to their checklist with your note the next time they sign in.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="send-back-note">What needs fixing</Label>
                        <Textarea
                            id="send-back-note"
                            rows={4}
                            autoFocus
                            required
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            placeholder="The PAN card photo is blurred — please upload a clearer scan."
                        />
                        <InputError message={errors.note} />
                    </div>
                    <DialogFooter>
                        <Button disabled={processing}>Send back</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeOnboarding({ employee, onboarding, mailIsLocal, letterKinds, can }: Props) {
    const format = useFormat();
    const [sendingBack, setSendingBack] = useState(false);
    const letter = useForm<{ offer_letter: File | null }>({ offer_letter: null });

    if (!onboarding) {
        return (
            <EmployeeProfileLayout employee={employee} tab="onboarding">
                <p className="text-muted-foreground text-sm">This person was not onboarded through the portal.</p>
            </EmployeeProfileLayout>
        );
    }

    const post = (name: string) => router.post(route(name, employee.id), {}, { preserveScroll: true });
    const uploadLetter: FormEventHandler = (e) => {
        e.preventDefault();
        letter.post(route('admin.employees.onboarding.offer-letter.store', employee.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => letter.reset(),
        });
    };

    const beforeSubmit = ['invited', 'in_progress', 'returned'].includes(onboarding.status);

    return (
        <EmployeeProfileLayout employee={employee} tab="onboarding">
            <InviteLinkNotice />

            <Card>
                <CardContent className="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                    <div className="min-w-0 flex-1 space-y-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <OnboardingBadge status={onboarding.status} />
                            <span className="text-muted-foreground text-xs">
                                {onboarding.invited_at ? `Invited ${format.date(onboarding.invited_at)}` : 'Invite not sent yet'}
                                {onboarding.submitted_at && ` · submitted ${format.date(onboarding.submitted_at)}`}
                                {onboarding.completed_at && ` · completed ${format.date(onboarding.completed_at)}`}
                            </span>
                        </div>
                        <div className="max-w-md space-y-1.5">
                            <div className="text-muted-foreground flex justify-between text-xs">
                                <span>Required items</span>
                                <span className="tabular-nums">
                                    {onboarding.progress.done} of {onboarding.progress.total}
                                </span>
                            </div>
                            <Meter value={onboarding.progress.percent} label={`${onboarding.progress.percent}% of onboarding complete`} />
                        </div>
                    </div>

                    {can.manage && (
                        <div className="flex flex-wrap gap-2">
                            {beforeSubmit && (
                                <Button variant="outline" size="sm" onClick={() => post('admin.employees.onboarding.invite')}>
                                    <Mail className="size-4" style={{ color: 'var(--tone-blue)' }} />{' '}
                                    {onboarding.invited_at ? 'Resend invite' : 'Send invite'}
                                </Button>
                            )}
                            {onboarding.status === 'submitted' && (
                                <>
                                    <Button variant="outline" size="sm" onClick={() => setSendingBack(true)}>
                                        <Undo2 className="size-4" /> Send back
                                    </Button>
                                    <Button size="sm" onClick={() => post('admin.employees.onboarding.approve')}>
                                        <CheckCircle2 className="size-4" /> Approve
                                    </Button>
                                </>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {mailIsLocal && beforeSubmit && can.manage && (
                <p className="text-muted-foreground text-xs">
                    Email is not connected yet, so invites are only written to the log. Sending one shows you the link to pass on.
                </p>
            )}

            {onboarding.status === 'returned' && onboarding.note && (
                <Alert>
                    <Undo2 className="size-4" />
                    <AlertDescription>
                        Sent back: <span className="whitespace-pre-wrap">{onboarding.note}</span>
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2">
                        <CardHeading icon={ListChecks} tone="teal">
                            Checklist
                        </CardHeading>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2">
                            {onboarding.checklist.map((item) => (
                                <li key={item.key} className="flex items-center gap-2 text-sm">
                                    {item.done ? (
                                        <CheckCircle2 className="size-4 shrink-0" style={{ color: 'var(--status-good)' }} aria-label="Done" />
                                    ) : (
                                        <Circle className="text-muted-foreground size-4 shrink-0" aria-label="Not done" />
                                    )}
                                    <span className={item.done ? '' : 'text-muted-foreground'}>{item.label}</span>
                                    {!item.required && <span className="text-muted-foreground text-xs">(optional)</span>}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardHeading icon={Landmark} tone="green">
                                Bank details
                            </CardHeading>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4">
                                <Row label="Account holder" value={onboarding.bank.account_name} />
                                <Row label="Account number" value={onboarding.bank.account_number} />
                                <Row label="IFSC" value={onboarding.bank.ifsc} />
                                <Row label="Bank" value={[onboarding.bank.bank_name, onboarding.bank.branch].filter(Boolean).join(', ')} />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardHeading icon={FileSignature} tone="violet">
                                Offer letter
                            </CardHeading>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {onboarding.offer_letter ? (
                                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span className="truncate">{onboarding.offer_letter.name}</span>
                                    {can.offer_letter && (
                                        <Button asChild variant="outline" size="sm">
                                            <a href={route('admin.employees.onboarding.offer-letter', employee.id)}>
                                                <Download className="size-4" /> Download
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">No offer letter attached, so no signed copy is asked for.</p>
                            )}

                            {(() => {
                                const signed = onboarding.slots.find((slot) => slot.is_offer_letter)?.document;
                                return signed ? (
                                    <p className="text-sm">
                                        Signed copy: {signed.original_name}{' '}
                                        <span className="text-muted-foreground text-xs">({formatBytes(signed.size)})</span>
                                    </p>
                                ) : null;
                            })()}

                            {can.manage && beforeSubmit && (
                                <div className="flex flex-wrap gap-2">
                                    {letterKinds.map(({ kind, label }) => (
                                        <Button
                                            key={kind}
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    route('admin.employees.onboarding.offer-letter.generate', employee.id),
                                                    { kind },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <FileSignature className="size-4" /> {onboarding.offer_letter?.kind === kind ? 'Regenerate' : 'Generate'}{' '}
                                            {label}
                                        </Button>
                                    ))}
                                </div>
                            )}

                            {can.manage && beforeSubmit && (
                                <form onSubmit={uploadLetter} className="flex flex-wrap items-center gap-2 border-t pt-3">
                                    <Input
                                        type="file"
                                        accept=".pdf,.doc,.docx"
                                        className="max-w-xs"
                                        onChange={(e) => letter.setData('offer_letter', e.target.files?.[0] ?? null)}
                                    />
                                    <Button variant="outline" size="sm" disabled={!letter.data.offer_letter || letter.processing}>
                                        <FileUp className="size-4" /> {onboarding.offer_letter ? 'Replace' : 'Attach'}
                                    </Button>
                                    <InputError message={letter.errors.offer_letter} />
                                </form>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {can.documents && <p className="text-muted-foreground text-xs">Uploaded documents are on the Documents tab.</p>}

            <SendBackDialog employeeId={employee.id} open={sendingBack} onOpenChange={setSendingBack} />
        </EmployeeProfileLayout>
    );
}
