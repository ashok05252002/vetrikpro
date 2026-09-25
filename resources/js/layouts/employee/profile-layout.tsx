import AccessToggle from '@/components/admin/access-toggle';
import PageHeader from '@/components/admin/page-header';
import TabNav, { type TabLink } from '@/components/tab-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UserAvatar from '@/components/work/user-avatar';
import { useFormat } from '@/hooks/use-format';
import AppLayout from '@/layouts/app-layout';
import { statusLabels } from '@/pages/admin/employees/labels';
import type { BreadcrumbItem, EmployeeProfileHeader } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { KeyRound, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';

export type ProfileTab = 'overview' | 'onboarding' | 'documents' | 'projects' | 'tasks' | 'access';

function tabs(employee: EmployeeProfileHeader): TabLink<ProfileTab>[] {
    const all: (TabLink<ProfileTab> & { show: boolean })[] = [
        { key: 'overview', label: 'Overview', href: route('admin.employees.show', employee.id), show: true },
        {
            key: 'onboarding',
            label: 'Onboarding',
            href: route('admin.employees.onboarding', employee.id),
            show: employee.onboarding_status !== null,
        },
        {
            key: 'documents',
            label: 'Documents',
            href: route('admin.employees.documents.index', employee.id),
            count: employee.counts.documents,
            show: employee.viewer.can_documents,
        },
        { key: 'projects', label: 'Projects', href: route('admin.employees.projects', employee.id), count: employee.counts.projects, show: true },
        { key: 'tasks', label: 'Tasks', href: route('admin.employees.tasks', employee.id), count: employee.counts.open_tasks, show: true },
        { key: 'access', label: 'Access', href: route('admin.employees.access', employee.id), show: employee.viewer.can_access },
    ];

    return all.filter((tab) => tab.show);
}

/**
 * Frame for every tab of a staff profile: trail back to the directory, who
 * this is at a glance, and the tabs the viewer is allowed to open.
 */
export default function EmployeeProfileLayout({
    employee,
    tab,
    actions,
    children,
}: {
    employee: EmployeeProfileHeader;
    tab: ProfileTab;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const format = useFormat();
    const all = tabs(employee);
    const current = all.find((t) => t.key === tab) ?? all[0];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: route('admin.employees.index') },
        { title: employee.name, href: route('admin.employees.show', employee.id) },
        ...(tab === 'overview' ? [] : [{ title: current.label, href: current.href }]),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={tab === 'overview' ? employee.name : `${current.label} · ${employee.name}`} />

            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    back={route('admin.employees.index')}
                    title={employee.name}
                    description={[employee.employee_code, employee.designation, employee.department].filter(Boolean).join(' · ')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            {actions}
                            {employee.viewer.can_toggle_access && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(route('admin.employees.password-reset', employee.id), {}, { preserveScroll: true })
                                        }
                                    >
                                        <KeyRound className="size-4" /> Send password reset
                                    </Button>
                                    <AccessToggle employeeId={employee.id} name={employee.name} active={employee.is_active} />
                                </>
                            )}
                            {employee.viewer.can_edit && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={route('admin.employees.edit', employee.id)}>
                                        <Pencil className="size-4" /> Edit
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <UserAvatar name={employee.name} className="size-9" />
                    <a href={`mailto:${employee.email}`} className="text-muted-foreground hover:underline">
                        {employee.email}
                    </a>
                    <Badge variant={employee.status === 'active' ? 'default' : 'secondary'}>{statusLabels[employee.status] ?? employee.status}</Badge>
                    {employee.role && <Badge variant="outline">{employee.role.name}</Badge>}
                    {!employee.is_active && (
                        <Badge variant="destructive">
                            Deactivated{employee.deactivated_at && ` ${format.date(employee.deactivated_at)}`}
                            {employee.deactivated_by && ` by ${employee.deactivated_by}`}
                        </Badge>
                    )}
                </div>

                <TabNav tabs={all} active={tab} label="Profile sections" />

                {children}
            </div>
        </AppLayout>
    );
}
