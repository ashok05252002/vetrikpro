import FilterBar from '@/components/admin/filter-bar';
import PageHeader from '@/components/admin/page-header';
import Pagination from '@/components/admin/pagination';
import MergeRequestTable from '@/components/dev/merge-request-table';
import TabNav from '@/components/tab-nav';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, MergeRequestRow, Option, Paginated } from '@/types';
import { Head } from '@inertiajs/react';

type Scope = 'review' | 'mine' | 'all';

interface Props {
    mergeRequests: Paginated<MergeRequestRow>;
    scope: Scope;
    awaitingCount: number;
    statuses: Option[];
    projects: Option[];
    filters: { search?: string; status?: string; project?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Merge requests', href: '/merge-requests' },
];

/**
 * Merge requests across every project you are on.
 */
export default function MergeRequestInbox({ mergeRequests, scope, awaitingCount, statuses, projects, filters }: Props) {
    const url = route('merge-requests.index');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Merge requests" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    icon={SECTIONS.git.icon}
                    tone={SECTIONS.git.tone}
                    title="Merge requests"
                    description="Across every project you are on. Open one to review it, or to follow your own."
                />

                <TabNav<Scope>
                    label="Which merge requests"
                    active={scope}
                    tabs={[
                        { key: 'review', label: 'Awaiting my review', href: url, count: awaitingCount },
                        { key: 'mine', label: 'Requested by me', href: route('merge-requests.index', { scope: 'mine' }) },
                        { key: 'all', label: 'All', href: route('merge-requests.index', { scope: 'all' }) },
                    ]}
                />

                {scope !== 'review' && (
                    <FilterBar
                        key={scope}
                        url={url}
                        filters={filters}
                        keep={{ scope }}
                        searchPlaceholder="Title or branch…"
                        selects={[
                            { name: 'status', placeholder: 'Any status', options: statuses },
                            { name: 'project', placeholder: 'All projects', options: projects },
                        ]}
                    />
                )}

                <MergeRequestTable rows={mergeRequests.data} showProject />
                <Pagination meta={mergeRequests} />
            </div>
        </AppLayout>
    );
}
