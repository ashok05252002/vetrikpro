import PageHeader from '@/components/admin/page-header';
import TabNav, { type TabLink } from '@/components/tab-nav';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type AccountsTab = 'invoices' | 'customers' | 'products';

/**
 * Accounts: invoices, and the customers and products they are raised from.
 * One tab per area, each its own route and permission.
 */
export default function AccountsLayout({
    tab,
    title,
    description,
    back,
    actions,
    trail = [],
    children,
}: {
    tab: AccountsTab;
    title?: string;
    description?: string;
    back?: string;
    actions?: ReactNode;
    /** Extra breadcrumbs below the tab, e.g. an invoice. */
    trail?: BreadcrumbItem[];
    children: ReactNode;
}) {
    const { can } = usePermission();

    const tabs = (
        [
            { key: 'invoices', label: 'Invoices', href: route('accounts.invoices.index'), show: can('invoices.view') },
            { key: 'customers', label: 'Customers', href: route('accounts.customers.index'), show: can('customers.view') },
            { key: 'products', label: 'Products & services', href: route('accounts.products.index'), show: can('products.view') },
        ] as (TabLink<AccountsTab> & { show: boolean })[]
    ).filter((t) => t.show);

    const current = tabs.find((t) => t.key === tab);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Accounts', href: route('accounts.home') },
        ...(current ? [{ title: current.label, href: current.href }] : []),
        ...trail,
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${trail.at(-1)?.title ?? current?.label ?? 'Accounts'} · Accounts`} />

            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader back={back} title={title ?? 'Accounts'} description={description} action={actions} />
                {trail.length === 0 && <TabNav tabs={tabs} active={tab} label="Accounts sections" />}
                {children}
            </div>
        </AppLayout>
    );
}
