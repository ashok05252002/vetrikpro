import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import IconChip, { type Tone } from '@/components/viz/icon-chip';
import ConfigLayout from '@/layouts/config/config-layout';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Building2, CheckCircle2, FileCheck2, FileSignature, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface Props {
    areas: {
        organisation: { name: string; logo: string | null; missing: string[] } | null;
        documents: { active: number; required: number } | null;
        offer: { title: string; signatory: string; valid_days: number } | null;
    };
}

function Area({
    icon,
    tone,
    title,
    href,
    status,
    children,
}: {
    icon: LucideIcon;
    tone: Tone;
    title: string;
    href: string;
    status: 'ok' | 'attention';
    children: ReactNode;
}) {
    return (
        <Card className="flex flex-col transition-shadow hover:shadow-md">
            <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                <IconChip icon={icon} tone={tone} />
                <CardTitle className="flex-1 text-base">{title}</CardTitle>
                {status === 'ok' ? (
                    <span className="inline-flex items-center gap-1 text-xs font-medium">
                        <CheckCircle2 className="size-4" style={{ color: 'var(--status-good)' }} aria-hidden /> Set up
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1 text-xs font-medium">
                        <AlertTriangle className="size-4" style={{ color: 'var(--tone-amber)' }} aria-hidden /> Needs attention
                    </span>
                )}
            </CardHeader>
            <CardContent className="flex flex-1 flex-col justify-between gap-4">
                <div className="text-muted-foreground space-y-1 text-sm">{children}</div>
                <Button asChild variant="outline" size="sm" className="self-start">
                    <Link href={href}>
                        Open <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

export default function ConfigHub({ areas }: Props) {
    const { organisation, documents, offer } = areas;

    return (
        <ConfigLayout tab="hub">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {organisation && (
                    <Area
                        icon={Building2}
                        tone="indigo"
                        title="Organisation"
                        href={route('admin.settings.edit')}
                        status={organisation.missing.length === 0 ? 'ok' : 'attention'}
                    >
                        <p className="text-foreground font-medium">{organisation.name}</p>
                        <p>Name, logo, contact details, date format and currency.</p>
                        {organisation.missing.length > 0 && (
                            <p className="text-foreground">Missing: {organisation.missing.join(', ')}. These print on offer letters.</p>
                        )}
                    </Area>
                )}

                {documents && (
                    <Area icon={FileCheck2} tone="teal" title="Document types" href={route('admin.config.document-types.index')} status="ok">
                        <p>
                            <span className="text-foreground font-medium">{documents.required} required</span> of {documents.active} active document
                            types.
                        </p>
                        <p>What every new employee is asked to upload during onboarding.</p>
                    </Area>
                )}

                {offer && (
                    <Area
                        icon={FileSignature}
                        tone="violet"
                        title="Offer letter"
                        href={route('admin.config.offer-letter.edit')}
                        status={offer.signatory ? 'ok' : 'attention'}
                    >
                        <p className="text-foreground font-medium">{offer.title}</p>
                        <p>Generated automatically for each new employee, with your logo, and valid for {offer.valid_days} days.</p>
                        {!offer.signatory && <p className="text-foreground">No signatory set: letters are signed “Authorised signatory”.</p>}
                    </Area>
                )}
            </div>
        </ConfigLayout>
    );
}
