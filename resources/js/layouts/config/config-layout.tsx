import PageHeader from '@/components/admin/page-header';
import TabNav, { type TabLink } from '@/components/tab-nav';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type ConfigTab = 'hub' | 'organisation' | 'document-types' | 'offer-letter';

/**
 * The Configuration hub: an overview of every area, and one tab per area.
 * Each tab is its own route with its own permission.
 */
export default function ConfigLayout({ tab, children }: { tab: ConfigTab; children: ReactNode }) {
    const { can } = usePermission();

    const tabs = (
        [
            { key: 'hub', label: 'Overview', href: route('admin.config.hub'), show: true },
            { key: 'organisation', label: 'Organisation', href: route('admin.settings.edit'), show: can('settings.view') },
            { key: 'document-types', label: 'Document types', href: route('admin.config.document-types.index'), show: can('document_types.view') },
            { key: 'offer-letter', label: 'Offer letter', href: route('admin.config.offer-letter.edit'), show: can('settings.view') },
        ] as (TabLink<ConfigTab> & { show: boolean })[]
    ).filter((t) => t.show);

    const current = tabs.find((t) => t.key === tab);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Configuration hub', href: route('admin.config.hub') },
        ...(current && tab !== 'hub' ? [{ title: current.label, href: current.href }] : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={current && tab !== 'hub' ? `${current.label} · Configuration hub` : 'Configuration hub'} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    back={tab === 'hub' ? undefined : route('admin.config.hub')}
                    title="Configuration hub"
                    description="Everything that shapes how the portal works for your organisation, in one place."
                />
                <TabNav tabs={tabs} active={tab} label="Configuration sections" />
                {children}
            </div>
        </AppLayout>
    );
}
