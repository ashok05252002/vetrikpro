import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import { cn } from '@/lib/utils';
import type { ProjectWorkspaceHeader } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Download, FileSpreadsheet, Info, Loader2, Lock, Upload, XCircle } from 'lucide-react';
import { useRef, useState } from 'react';

interface CheckResult {
    header_errors: string[];
    errors: { row: number; column: string; message: string }[];
    points: { number: number; module: string; description: string; notes: string | null }[];
}

interface Props {
    project: ProjectWorkspaceHeader;
    next: string;
    norms: string[];
    headers: string[];
}

function Step({ n, title, done, children }: { n: number; title: string; done?: boolean; children: React.ReactNode }) {
    return (
        <li className="bg-card relative rounded-xl border p-4">
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        done ? 'bg-[var(--status-good)] text-white' : 'bg-primary/10 text-primary',
                    )}
                >
                    {done ? <CheckCircle2 className="size-4" /> : n}
                </span>
                <div className="min-w-0 flex-1 space-y-3">
                    <h2 className="pt-0.5 text-sm font-semibold">{title}</h2>
                    {children}
                </div>
            </div>
        </li>
    );
}

/** "How to fill it in" — the norms, behind an ⓘ. */
function Norms({ norms, headers }: { norms: string[]; headers: string[] }) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button type="button" variant="ghost" size="sm" className="h-8 gap-1.5 text-xs" aria-label="How to fill in the template">
                    <Info className="size-4" style={{ color: 'var(--tone-blue)' }} /> How to fill it in
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[26rem] max-w-[calc(100vw-2rem)]" align="start">
                <h3 className="mb-2 text-sm font-semibold">How to fill in the template</h3>
                <div className="mb-3 flex overflow-hidden rounded-md border text-[11px] font-semibold">
                    {headers.map((h, i) => (
                        <span
                            key={h}
                            className={cn('flex-1 px-2 py-1.5 text-white', i > 0 && 'border-l border-white/30')}
                            style={{ background: '#4f46e5' }}
                        >
                            {h}
                        </span>
                    ))}
                </div>
                <ol className="text-muted-foreground list-decimal space-y-1.5 pl-4 text-xs">
                    {norms.map((norm) => (
                        <li key={norm}>{norm}</li>
                    ))}
                </ol>
            </PopoverContent>
        </Popover>
    );
}

export default function RequirementsUpload({ project, next, norms, headers }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [checking, setChecking] = useState(false);
    const [result, setResult] = useState<CheckResult | null>(null);
    const [checkError, setCheckError] = useState<string | null>(null);
    const { setData, post, processing, errors } = useForm<{ file: File | null }>({ file: null });

    const clean = result !== null && result.header_errors.length === 0 && result.errors.length === 0;

    const pick = (chosen: File | null) => {
        setFile(chosen);
        setData('file', chosen);
        setResult(null);
        setCheckError(null);
        if (chosen) void check(chosen);
    };

    // Checking never imports: it only reports. Import is a separate, deliberate step.
    const check = async (chosen: File) => {
        setChecking(true);
        const body = new FormData();
        body.append('file', chosen);
        const xsrf = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );
        try {
            const response = await fetch(route('projects.requirements.check', project.id), {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await response.json();
            if (!response.ok) {
                setCheckError(json.errors?.file?.[0] ?? json.message ?? 'The file could not be checked.');
            } else {
                setResult(json as CheckResult);
            }
        } catch {
            setCheckError('The file could not be checked. Try again.');
        } finally {
            setChecking(false);
        }
    };

    const problems = result ? result.header_errors.length + result.errors.length : 0;

    return (
        <ProjectWorkspaceLayout
            project={project}
            tab="requirements"
            actions={
                <Button asChild size="sm" variant="ghost">
                    <Link href={route('projects.requirements.index', project.id)}>
                        <ArrowLeft className="size-4" /> Requirements
                    </Link>
                </Button>
            }
        >
            <ol className="grid max-w-4xl gap-3">
                <Step n={1} title="Version" done>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="bg-primary/10 text-primary inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-lg font-bold">
                            <Lock className="size-3.5" aria-hidden /> {next}
                        </span>
                        <p className="text-muted-foreground text-xs">
                            Set automatically, in sequence: V1, V1.1 … V1.10, then V2. It can't be changed, so no version is skipped or repeated.
                        </p>
                    </div>
                </Step>

                <Step n={2} title="Fill in the template">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild size="sm" variant="outline">
                            <a href={route('projects.requirements.template', project.id)}>
                                <Download className="size-4" /> Download template (.xlsx)
                            </a>
                        </Button>
                        <Norms norms={norms} headers={headers} />
                    </div>
                </Step>

                <Step n={3} title="Upload and check" done={clean}>
                    <input
                        ref={fileInput}
                        type="file"
                        accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                        className="hidden"
                        onChange={(e) => pick(e.target.files?.[0] ?? null)}
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="button" size="sm" variant={file ? 'outline' : 'default'} onClick={() => fileInput.current?.click()}>
                            <Upload className="size-4" /> {file ? 'Choose another file' : 'Choose Excel file'}
                        </Button>
                        {file && (
                            <span className="inline-flex items-center gap-1.5 text-sm">
                                <FileSpreadsheet className="size-4" style={{ color: 'var(--tone-green)' }} aria-hidden />
                                <span className="font-medium">{file.name}</span>
                            </span>
                        )}
                        {file && !checking && (
                            <Button type="button" size="sm" variant="ghost" onClick={() => file && check(file)}>
                                Check again
                            </Button>
                        )}
                        {checking && (
                            <span className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                <Loader2 className="size-3.5 animate-spin" /> Checking…
                            </span>
                        )}
                    </div>

                    {checkError && <InputError message={checkError} />}

                    {result && !clean && (
                        <div className="border-destructive/30 bg-destructive/5 space-y-3 rounded-lg border p-3">
                            <p className="flex items-center gap-2 text-sm font-semibold">
                                <XCircle className="text-destructive size-4" />
                                {problems} {problems === 1 ? 'problem' : 'problems'} to fix — nothing has been imported
                            </p>
                            {result.header_errors.length > 0 && (
                                <div className="space-y-1">
                                    <p className="text-xs font-semibold tracking-wide uppercase">Header row</p>
                                    <ul className="list-disc space-y-1 pl-5 text-sm">
                                        {result.header_errors.map((e) => (
                                            <li key={e}>{e}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {result.errors.length > 0 && (
                                <div className="bg-background max-h-80 overflow-auto rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-20">Row</TableHead>
                                                <TableHead className="w-44">Column</TableHead>
                                                <TableHead>Problem</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {result.errors.map((e, i) => (
                                                <TableRow key={i}>
                                                    <TableCell className="py-2 font-mono text-xs">{e.row}</TableCell>
                                                    <TableCell className="py-2 text-xs font-medium">{e.column}</TableCell>
                                                    <TableCell className="py-2 text-sm">{e.message}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                            <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <AlertTriangle className="size-3.5" /> Fix these in Excel, save, and choose the file again.
                            </p>
                        </div>
                    )}

                    {clean && (
                        <p className="flex items-center gap-2 text-sm font-medium" style={{ color: 'var(--status-good)' }}>
                            <CheckCircle2 className="size-4" /> The file is clean: {result.points.length} requirements ready.
                        </p>
                    )}
                </Step>

                <Step n={4} title={`Import as ${next}`}>
                    {clean ? (
                        <>
                            <div className="max-h-72 overflow-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-20">S.No</TableHead>
                                            <TableHead className="w-40">Module</TableHead>
                                            <TableHead>Description</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {result.points.slice(0, 50).map((p) => (
                                            <TableRow key={p.number}>
                                                <TableCell className="py-2 font-mono text-xs">{p.number}</TableCell>
                                                <TableCell className="py-2 text-xs">{p.module}</TableCell>
                                                <TableCell className="py-2 text-sm">{p.description}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                            {result.points.length > 50 && <p className="text-muted-foreground text-xs">…and {result.points.length - 50} more.</p>}
                            <InputError message={errors.file} />
                            <Button
                                disabled={processing}
                                onClick={() => post(route('projects.requirements.import', project.id), { forceFormData: true })}
                            >
                                {processing ? <Loader2 className="size-4 animate-spin" /> : <CheckCircle2 className="size-4" />} Import{' '}
                                {result.points.length} requirements as {next}
                            </Button>
                        </>
                    ) : (
                        <p className="text-muted-foreground text-sm">Available once the file passes every check.</p>
                    )}
                </Step>
            </ol>
        </ProjectWorkspaceLayout>
    );
}
