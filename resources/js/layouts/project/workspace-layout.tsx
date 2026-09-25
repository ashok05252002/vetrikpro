import PageHeader from '@/components/admin/page-header';
import TabNav, { type TabLink } from '@/components/tab-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Meter from '@/components/viz/meter';
import { useFormat } from '@/hooks/use-format';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ProjectWorkspaceHeader } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import type { ReactNode } from 'react';

export type WorkspaceTab = 'tasks' | 'testing' | 'members';

function tabs(projectId: number): TabLink<WorkspaceTab>[] {
    return [
        { key: 'tasks', label: 'Tasks', href: route('projects.show', projectId) },
        { key: 'testing', label: 'Testing', href: route('projects.testing.index', projectId) },
        { key: 'members', label: 'Members', href: route('projects.members.index', projectId) },
    ];
}

interface Props {
    project: ProjectWorkspaceHeader;
    tab: WorkspaceTab;
    /** Buttons for this tab, shown beside Settings. */
    actions?: ReactNode;
    /** Extra crumbs below the tab, e.g. a single merge request. */
    crumbs?: BreadcrumbItem[];
    children: ReactNode;
}

/**
 * Frame shared by every project tab: breadcrumb trail, header with a way back,
 * and the tab bar. Each tab is its own route, so links are shareable and a
 * tab only loads its own data.
 */
export default function ProjectWorkspaceLayout({ project, tab, actions, crumbs = [], children }: Props) {
    const format = useFormat();
    const { can } = usePermission();
    const all = tabs(project.id);
    const current = all.find((t) => t.key === tab)!;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: route('projects.index') },
        { title: project.name, href: route('projects.show', project.id) },
        { title: current.label, href: current.href },
        ...crumbs,
    ];

    const meta = [
        `Owner: ${project.owner?.name ?? 'Unassigned'}`,
        project.due_date ? `Due ${format.date(project.due_date)}` : null,
        `${project.members_count} ${project.members_count === 1 ? 'member' : 'members'}`,
        project.viewer.is_dev_admin ? 'You are a dev admin here' : null,
    ].filter(Boolean);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${current.label} · ${project.name}`} />

            <div className="flex h-full min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    back={route('projects.index')}
                    title={project.name}
                    description={meta.join(' · ')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="font-mono text-[10px]">
                                {project.code}
                            </Badge>
                            {can('projects.manage') && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={route('admin.projects.edit', project.id)}>
                                        <Settings2 className="size-4" /> Settings
                                    </Link>
                                </Button>
                            )}
                            {actions}
                        </div>
                    }
                />

                <div className="max-w-md space-y-1.5">
                    <div className="text-muted-foreground flex items-baseline justify-between text-xs">
                        <span>Progress</span>
                        <span className="tabular-nums">{project.progress}%</span>
                    </div>
                    <Meter value={project.progress} label={`${project.name} is ${project.progress}% complete`} />
                </div>

                <TabNav tabs={all} active={tab} label="Project sections" />

                {children}
            </div>
        </AppLayout>
    );
}
