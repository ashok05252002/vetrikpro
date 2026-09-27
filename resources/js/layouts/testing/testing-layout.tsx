import PageHeader from '@/components/admin/page-header';
import TabNav, { type TabLink } from '@/components/tab-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { SECTIONS } from '@/lib/sections';
import type { BreadcrumbItem, ProjectWorkspaceHeader, TestingCounts } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import type { ReactNode } from 'react';

export type TestingTab = 'points' | 'runs';

interface Props {
    project: ProjectWorkspaceHeader;
    counts: TestingCounts;
    tab: TestingTab;
    /** Buttons for this tab, shown beside "Open project". */
    actions?: ReactNode;
    /** Extra crumbs below the tab, e.g. a single run. */
    crumbs?: BreadcrumbItem[];
    children: ReactNode;
}

/**
 * Frame for one project inside the Testing module: Testing › Project, and two
 * tabs — the testing points themselves and the runs over them. The project's
 * own workspace links here from its Testing tab.
 */
export default function TestingLayout({ project, counts, tab, actions, crumbs = [], children }: Props) {
    const tabs: TabLink<TestingTab>[] = [
        { key: 'points', label: 'Testing points', href: route('testing.points.index', project.id), count: counts.points },
        { key: 'runs', label: 'Test runs', href: route('testing.runs.index', project.id), count: counts.runs },
    ];
    const current = tabs.find((t) => t.key === tab)!;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Testing', href: route('testing.index') },
        { title: project.name, href: route('testing.points.index', project.id) },
        { title: current.label, href: current.href },
        ...crumbs,
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${current.label} · ${project.name}`} />

            <div className="flex h-full min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    icon={SECTIONS.testing.icon}
                    tone={SECTIONS.testing.tone}
                    back={route('testing.index')}
                    title={project.name}
                    description={`Testing · Owner: ${project.owner?.name ?? 'Unassigned'}`}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="font-mono text-[10px]">
                                {project.code}
                            </Badge>
                            <Button asChild variant="outline" size="sm">
                                <Link href={route('projects.show', project.id)}>
                                    <FolderKanban className="size-4" /> Open project
                                </Link>
                            </Button>
                            {actions}
                        </div>
                    }
                />

                <TabNav tabs={tabs} active={tab} label="Testing sections" />

                {children}
            </div>
        </AppLayout>
    );
}
