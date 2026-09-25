import PageHeader from '@/components/admin/page-header';
import TabNav, { type TabLink } from '@/components/tab-nav';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type ConfigTab = 'organisation' | 'document-types';

/**
 * Configuration: one place for everything that shapes how the app behaves
 * for everyone. Each tab is its own route with its own permission.
 */
export default function ConfigLayout({ tab, children }: { tab: ConfigTab; children: ReactNode }) {
    const { can } = usePermission();

    const tabs = (
        [
            { key: 'organisation', label: 'Organisation', href: route('admin.settings.edit'), show: can('settings.view') },
            { key: 'document-types', label: 'Document types', href: route('admin.config.document-types.index'), show: can('document_types.view') },
        ] as (TabLink<ConfigTab> & { show: boolean })[]
    ).filter((t) => t.show);

    const current = tabs.find((t) => t.key === tab);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Configuration', href: tabs[0]?.href ?? '#' },
        ...(current ? [{ title: current.label, href: current.href }] : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={current ? `${current.label} · Configuration` : 'Configuration'} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader title="Configuration" description="Settings that apply to everyone in the organisation." />
                <TabNav tabs={tabs} active={tab} label="Configuration sections" />
                {children}
            </div>
        </AppLayout>
    );
}
