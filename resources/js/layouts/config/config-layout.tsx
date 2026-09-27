import PageHeader from '@/components/admin/page-header';
import { type Tone } from '@/components/viz/icon-chip';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BellRing, Building2, FileCheck2, FileSignature, Settings2, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type ConfigTab = 'hub' | 'organisation' | 'notifications' | 'document-types' | 'offer-letter';

/** Every section of the hub: its title, what it is for, its icon and colour. The hub's cards use the same. */
export const CONFIG_SECTIONS: Record<
    Exclude<ConfigTab, 'hub'>,
    { title: string; description: string; icon: LucideIcon; tone: Tone; href: () => string }
> = {
    organisation: {
        title: 'Organisation',
        description: 'Company details, branding, invoice defaults and regional formats.',
        icon: Building2,
        tone: 'indigo',
        href: () => route('admin.settings.edit'),
    },
    notifications: {
        title: 'Email notifications',
        description: 'Which task and bug events send email, and when the daily overdue email goes out.',
        icon: BellRing,
        tone: 'sky',
        href: () => route('admin.config.notifications.edit'),
    },
    'document-types': {
        title: 'Document types',
        description: 'What every new employee is asked to upload during onboarding.',
        icon: FileCheck2,
        tone: 'teal',
        href: () => route('admin.config.document-types.index'),
    },
    'offer-letter': {
        title: 'Offer letter',
        description: 'The letter generated for each new employee.',
        icon: FileSignature,
        tone: 'violet',
        href: () => route('admin.config.offer-letter.edit'),
    },
};

/**
 * The Configuration hub is a page of cards, one per section; each section is
 * its own page with a way back. No tabs: the hub is the menu.
 */
export default function ConfigLayout({ tab, actions, children }: { tab: ConfigTab; actions?: ReactNode; children: ReactNode }) {
    const section = tab === 'hub' ? null : CONFIG_SECTIONS[tab];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Configuration hub', href: route('admin.config.hub') },
        ...(section ? [{ title: section.title, href: section.href() }] : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={section ? `${section.title} · Configuration hub` : 'Configuration hub'} />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                {section ? (
                    <PageHeader
                        back={route('admin.config.hub')}
                        icon={section.icon}
                        tone={section.tone}
                        title={section.title}
                        description={section.description}
                        action={actions}
                    />
                ) : (
                    <PageHeader
                        icon={Settings2}
                        tone="slate"
                        title="Configuration hub"
                        description="Everything that shapes how the portal works for your organisation. Choose a section."
                        action={actions}
                    />
                )}
                {children}
            </div>
        </AppLayout>
    );
}
