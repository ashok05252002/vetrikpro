import IconChip, { type Tone } from '@/components/viz/icon-chip';
import ConfigLayout, { CONFIG_SECTIONS } from '@/layouts/config/config-layout';
import { SECTIONS } from '@/lib/sections';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CheckCircle2, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface Props {
    areas: {
        organisation: { name: string; logo: string | null; missing: string[] } | null;
        notifications: { on: number; overdue: string | null } | null;
        invoices: { state: string; due_days: number; bank: boolean } | null;
        documents: { active: number; required: number } | null;
        offer: { title: string; signatory: string; valid_days: number } | null;
    };
}

/** One section of the hub: the whole card is the link. */
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
        <Link
            href={href}
            className="group bg-card hover:border-foreground/20 focus-visible:ring-ring flex flex-col gap-4 rounded-xl border p-5 shadow-xs transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
        >
            <div className="flex items-start gap-3">
                <IconChip icon={icon} tone={tone} />
                <div className="min-w-0 flex-1">
                    <h2 className="font-semibold">{title}</h2>
                    {status === 'ok' ? (
                        <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                            <CheckCircle2 className="size-3.5" style={{ color: 'var(--status-good)' }} aria-hidden /> Set up
                        </span>
                    ) : (
                        <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                            <AlertTriangle className="size-3.5" style={{ color: 'var(--tone-amber)' }} aria-hidden /> Needs attention
                        </span>
                    )}
                </div>
                <ArrowRight className="text-muted-foreground size-4 transition-transform group-hover:translate-x-0.5" aria-hidden />
            </div>
            <div className="text-muted-foreground space-y-1 text-sm">{children}</div>
        </Link>
    );
}

export default function ConfigHub({ areas }: Props) {
    const { organisation, notifications, invoices, documents, offer } = areas;
    const section = (key: keyof typeof CONFIG_SECTIONS) => ({
        icon: CONFIG_SECTIONS[key].icon,
        tone: CONFIG_SECTIONS[key].tone,
        title: CONFIG_SECTIONS[key].title,
        href: CONFIG_SECTIONS[key].href(),
    });

    return (
        <ConfigLayout tab="hub">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {organisation && (
                    <Area {...section('organisation')} status={organisation.missing.length === 0 ? 'ok' : 'attention'}>
                        <p className="text-foreground font-medium">{organisation.name}</p>
                        <p>Name, logos, contact details, date format and currency.</p>
                        {organisation.missing.length > 0 && <p className="text-foreground">Missing: {organisation.missing.join(', ')}.</p>}
                    </Area>
                )}

                {notifications && (
                    <Area {...section('notifications')} status="ok">
                        <p>
                            <span className="text-foreground font-medium">{notifications.on} triggers on</span> for tasks and bugs.
                        </p>
                        <p>{notifications.overdue ? `Overdue email daily at ${notifications.overdue}.` : 'The daily overdue email is off.'}</p>
                    </Area>
                )}

                {invoices && (
                    <Area
                        icon={SECTIONS.invoices.icon}
                        tone={SECTIONS.invoices.tone}
                        title="Invoices"
                        href={`${route('admin.settings.edit')}#invoices`}
                        status={invoices.state ? 'ok' : 'attention'}
                    >
                        <p>Payment due after {invoices.due_days} days, terms and bank details for every new invoice.</p>
                        {!invoices.state && <p className="text-foreground">GST state not set: every invoice is treated as within the state.</p>}
                        {invoices.state && !invoices.bank && <p>No bank details yet — they print on every invoice.</p>}
                    </Area>
                )}

                {documents && (
                    <Area {...section('document-types')} status="ok">
                        <p>
                            <span className="text-foreground font-medium">{documents.required} required</span> of {documents.active} active document
                            types.
                        </p>
                        <p>What every new employee is asked to upload during onboarding.</p>
                    </Area>
                )}

                {offer && (
                    <Area {...section('offer-letter')} status={offer.signatory ? 'ok' : 'attention'}>
                        <p className="text-foreground font-medium">{offer.title}</p>
                        <p>Generated for each new employee, with your logo, valid for {offer.valid_days} days.</p>
                        {!offer.signatory && <p className="text-foreground">No signatory set: letters are signed “Authorised signatory”.</p>}
                    </Area>
                )}
            </div>
        </ConfigLayout>
    );
}
