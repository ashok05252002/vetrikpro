import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import CardHeading from '@/components/ui/card-heading';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ConfigLayout from '@/layouts/config/config-layout';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Braces, Eye, HandHeart, PenLine, RotateCcw } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

interface Props {
    template: {
        title: string;
        body: string;
        signatory_name: string;
        signatory_title: string;
        valid_days: number;
        welcome_title: string;
        welcome_body: string;
    };
    defaultBody: string;
    defaultWelcomeBody: string;
    placeholders: { key: string; label: string }[];
    letterhead: { logo: boolean; address: boolean; legal_name: boolean };
    can: { edit: boolean };
}

export default function OfferLetterTemplate({ template, defaultBody, defaultWelcomeBody, placeholders, letterhead, can }: Props) {
    const body = useRef<HTMLTextAreaElement>(null);
    const welcomeBody = useRef<HTMLTextAreaElement>(null);
    // Placeholders go into whichever body was last focused.
    const [target, setTarget] = useState<'body' | 'welcome_body'>('body');
    const { data, setData, put, processing, errors, isDirty, recentlySuccessful } = useForm(template);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.config.offer-letter.update'), { preserveScroll: true });
    };

    // Drop the placeholder in where the cursor is, not at the end.
    const insert = (key: string) => {
        const el = (target === 'body' ? body : welcomeBody).current;
        const text = data[target];
        const token = `{${key}}`;
        const start = el?.selectionStart ?? text.length;
        const end = el?.selectionEnd ?? text.length;
        setData(target, text.slice(0, start) + token + text.slice(end));
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const missing = [!letterhead.logo && 'logo', !letterhead.legal_name && 'legal name', !letterhead.address && 'address'].filter(Boolean);

    return (
        <ConfigLayout tab="offer-letter">
            {missing.length > 0 && (
                <Alert>
                    <AlertTriangle className="size-4" />
                    <AlertDescription>
                        The letterhead is built from your organisation details, and the company {missing.join(', ')}{' '}
                        {missing.length === 1 ? 'is' : 'are'} not set.{' '}
                        <Link href={route('admin.settings.edit')} className="font-medium underline">
                            Add them under Organisation
                        </Link>
                        .
                    </AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="grid gap-4 xl:grid-cols-[1fr_320px]">
                <Card>
                    <CardHeader>
                        <CardHeading icon={PenLine} tone="violet">
                            Offer letter wording
                        </CardHeading>
                        <CardDescription>
                            Plain text. A blank line starts a new paragraph, and <code>**text**</code> makes it bold. The offer summary table and
                            signature blocks are added automatically.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Heading</Label>
                            <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required disabled={!can.edit} />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="body">Body</Label>
                                {can.edit && data.body !== defaultBody && (
                                    <Button type="button" variant="ghost" size="sm" className="h-7" onClick={() => setData('body', defaultBody)}>
                                        <RotateCcw className="size-3.5" /> Restore standard wording
                                    </Button>
                                )}
                            </div>
                            <Textarea
                                id="body"
                                ref={body}
                                rows={18}
                                className="font-mono text-sm leading-relaxed"
                                value={data.body}
                                onChange={(e) => setData('body', e.target.value)}
                                onFocus={() => setTarget('body')}
                                required
                                disabled={!can.edit}
                            />
                            <InputError message={errors.body} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="signatory_name">Signed by</Label>
                                <Input
                                    id="signatory_name"
                                    value={data.signatory_name}
                                    onChange={(e) => setData('signatory_name', e.target.value)}
                                    placeholder="Ashok Kumar"
                                    disabled={!can.edit}
                                />
                                <InputError message={errors.signatory_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="signatory_title">Their title</Label>
                                <Input
                                    id="signatory_title"
                                    value={data.signatory_title}
                                    onChange={(e) => setData('signatory_title', e.target.value)}
                                    placeholder="Director"
                                    disabled={!can.edit}
                                />
                                <InputError message={errors.signatory_title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="valid_days">Offer valid for (days)</Label>
                                <Input
                                    id="valid_days"
                                    type="number"
                                    min={1}
                                    max={90}
                                    value={data.valid_days}
                                    onChange={(e) => setData('valid_days', Number(e.target.value))}
                                    required
                                    disabled={!can.edit}
                                />
                                <InputError message={errors.valid_days} />
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {can.edit && <Button disabled={processing || !isDirty}>Save templates</Button>}
                            <Button asChild type="button" variant="outline">
                                <a href={route('admin.config.offer-letter.preview')} target="_blank" rel="noreferrer">
                                    <Eye className="size-4" /> Preview PDF
                                </a>
                            </Button>
                            {isDirty && <span className="text-muted-foreground text-xs">Unsaved — the preview shows the saved version.</span>}
                            {recentlySuccessful && <span className="text-muted-foreground text-xs">Saved</span>}
                        </div>
                    </CardContent>
                </Card>

                <Card className="xl:col-start-1">
                    <CardHeader>
                        <CardHeading icon={HandHeart} tone="violet">
                            Welcome letter — no salary
                        </CardHeading>
                        <CardDescription>
                            For staff whose pay isn&rsquo;t put in writing, such as contract hires. Chosen as &ldquo;Welcome letter&rdquo; when adding
                            an employee. Same letterhead, signatory and validity as the offer letter; the summary table leaves out the salary, and{' '}
                            <code>{'{monthly_salary}'}</code> and <code>{'{annual_ctc}'}</code> print as &ldquo;as discussed&rdquo;.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="welcome_title">Heading</Label>
                            <Input
                                id="welcome_title"
                                value={data.welcome_title}
                                onChange={(e) => setData('welcome_title', e.target.value)}
                                required
                                disabled={!can.edit}
                            />
                            <InputError message={errors.welcome_title} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="welcome_body">Body</Label>
                                {can.edit && data.welcome_body !== defaultWelcomeBody && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7"
                                        onClick={() => setData('welcome_body', defaultWelcomeBody)}
                                    >
                                        <RotateCcw className="size-3.5" /> Restore standard wording
                                    </Button>
                                )}
                            </div>
                            <Textarea
                                id="welcome_body"
                                ref={welcomeBody}
                                rows={14}
                                className="font-mono text-sm leading-relaxed"
                                value={data.welcome_body}
                                onChange={(e) => setData('welcome_body', e.target.value)}
                                onFocus={() => setTarget('welcome_body')}
                                required
                                disabled={!can.edit}
                            />
                            <InputError message={errors.welcome_body} />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {can.edit && <Button disabled={processing || !isDirty}>Save templates</Button>}
                            <Button asChild type="button" variant="outline">
                                <a href={route('admin.config.offer-letter.preview', { kind: 'welcome' })} target="_blank" rel="noreferrer">
                                    <Eye className="size-4" /> Preview PDF
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card className="self-start xl:col-start-2 xl:row-span-2 xl:row-start-1">
                    <CardHeader>
                        <CardHeading icon={Braces} tone="violet">
                            Placeholders
                        </CardHeading>
                        <CardDescription>
                            {can.edit
                                ? 'Click one to insert it at the cursor, in whichever letter you last clicked into.'
                                : 'Filled in for each employee.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-1.5">
                            {placeholders.map((p) => (
                                <li key={p.key}>
                                    <button
                                        type="button"
                                        disabled={!can.edit}
                                        onClick={() => insert(p.key)}
                                        className="hover:bg-muted w-full rounded-md px-2 py-1.5 text-left disabled:cursor-default disabled:hover:bg-transparent"
                                    >
                                        <code className="text-primary text-xs">{`{${p.key}}`}</code>
                                        <span className="text-muted-foreground block text-xs">{p.label}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </form>
        </ConfigLayout>
    );
}
