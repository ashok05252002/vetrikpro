import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import CardHeading from '@/components/ui/card-heading';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import ConfigLayout from '@/layouts/config/config-layout';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Braces, Eye, PenLine, RotateCcw, Signature } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

interface Letter {
    key: string;
    label: string;
    description: string;
    default_body: string;
    placeholders: { key: string; label: string }[];
    preview_url: string;
}

type Template = {
    signatory_name: string;
    signatory_title: string;
    valid_days: number;
    letters: Record<string, { title: string; body: string }>;
};

interface Props {
    template: Template;
    letters: Letter[];
    letterhead: { logo: boolean; address: boolean; legal_name: boolean };
    can: { edit: boolean };
}

export default function OfferLetterTemplate({ template, letters, letterhead, can }: Props) {
    const body = useRef<HTMLTextAreaElement>(null);
    const [active, setActive] = useState(letters[0].key);
    const letter = letters.find((l) => l.key === active) ?? letters[0];
    const { data, setData, put, processing, errors, isDirty, recentlySuccessful } = useForm<Template>(template);
    const current = data.letters[letter.key];
    const fieldErrors = errors as Record<string, string | undefined>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.config.offer-letter.update'), { preserveScroll: true });
    };

    const setLetter = (field: 'title' | 'body', value: string) =>
        setData('letters', { ...data.letters, [letter.key]: { ...current, [field]: value } });

    // Drop the placeholder in where the cursor is, not at the end.
    const insert = (key: string) => {
        const el = body.current;
        const text = current.body;
        const token = `{${key}}`;
        const start = el?.selectionStart ?? text.length;
        const end = el?.selectionEnd ?? text.length;
        setLetter('body', text.slice(0, start) + token + text.slice(end));
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const hasError = (key: string) => Boolean(fieldErrors[`letters.${key}.title`] || fieldErrors[`letters.${key}.body`]);
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
                            Letter wording
                        </CardHeading>
                        <CardDescription>
                            Every letter the portal makes as a PDF. Pick one to edit it. Plain text: a blank line starts a new paragraph, and{' '}
                            <code>**text**</code> makes it bold. The letterhead, summary table and signatures are added automatically.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={active}
                            onValueChange={(value) => value && setActive(value)}
                            className="flex-wrap justify-start"
                        >
                            {letters.map((l) => (
                                <ToggleGroupItem
                                    key={l.key}
                                    value={l.key}
                                    className={hasError(l.key) ? 'border-destructive px-3 text-xs' : 'px-3 text-xs'}
                                >
                                    {l.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>

                        <p className="text-muted-foreground text-xs">{letter.description}</p>

                        <div className="grid gap-2">
                            <Label htmlFor="title">Heading</Label>
                            <Input
                                id="title"
                                value={current.title}
                                onChange={(e) => setLetter('title', e.target.value)}
                                required
                                disabled={!can.edit}
                            />
                            <InputError message={fieldErrors[`letters.${letter.key}.title`]} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="body">Body</Label>
                                {can.edit && current.body !== letter.default_body && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7"
                                        onClick={() => setLetter('body', letter.default_body)}
                                    >
                                        <RotateCcw className="size-3.5" /> Restore standard wording
                                    </Button>
                                )}
                            </div>
                            <Textarea
                                id="body"
                                ref={body}
                                rows={18}
                                className="font-mono text-sm leading-relaxed"
                                value={current.body}
                                onChange={(e) => setLetter('body', e.target.value)}
                                required
                                disabled={!can.edit}
                            />
                            <InputError message={fieldErrors[`letters.${letter.key}.body`]} />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {can.edit && <Button disabled={processing || !isDirty}>Save templates</Button>}
                            <Button asChild type="button" variant="outline">
                                <a href={letter.preview_url} target="_blank" rel="noreferrer">
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
                        <CardHeading icon={Signature} tone="violet">
                            Signatory and validity
                        </CardHeading>
                        <CardDescription>Shared by every letter above.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
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
                        {can.edit && <Button disabled={processing || !isDirty}>Save templates</Button>}
                    </CardContent>
                </Card>

                <Card className="self-start xl:col-start-2 xl:row-span-2 xl:row-start-1">
                    <CardHeader>
                        <CardHeading icon={Braces} tone="violet">
                            Placeholders
                        </CardHeading>
                        <CardDescription>
                            {can.edit ? `Click one to insert it at the cursor in the ${letter.label.toLowerCase()}.` : 'Filled in for each person.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-1.5">
                            {letter.placeholders.map((p) => (
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
