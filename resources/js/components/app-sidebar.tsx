import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Briefcase, Building2, FolderKanban, GitPullRequest, IdCard, LayoutGrid, ListChecks, Settings, ShieldCheck, Users } from 'lucide-react';
import AppLogo from './app-logo';

/** Everyone signed in gets these. */
const workNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'My tasks', url: '/tasks', icon: ListChecks },
    { title: 'Projects', url: '/projects', icon: FolderKanban },
    { title: 'Merge requests', url: '/merge-requests', icon: GitPullRequest },
];

/** Each admin entry appears only for the permission its routes check. */
const adminNavItems: (NavItem & { permission: string | string[] })[] = [
    { title: 'Users', url: '/admin/users', icon: Users, permission: 'users.view' },
    { title: 'Roles & access', url: '/admin/roles', icon: ShieldCheck, permission: 'roles.view' },
    { title: 'Employees', url: '/admin/employees', icon: IdCard, permission: 'employees.view' },
    { title: 'Departments', url: '/admin/departments', icon: Building2, permission: 'departments.view' },
    { title: 'Designations', url: '/admin/designations', icon: Briefcase, permission: 'designations.view' },
    { title: 'Manage projects', url: '/admin/projects', icon: FolderKanban, permission: 'projects.view' },
    // Lands on the first Configuration tab the person may open.
    {
        title: 'Configuration',
        url: '/admin/settings',
        icon: Settings,
        permission: ['settings.view', 'document_types.view'],
        match: ['/admin/settings', '/admin/config'],
    },
];

export function AppSidebar() {
    const { can, canAny } = usePermission();
    const adminItems = adminNavItems
        .filter((item) => (Array.isArray(item.permission) ? canAny(...item.permission) : can(item.permission)))
        .map((item) => (item.title === 'Configuration' && !can('settings.view') ? { ...item, url: '/admin/config/document-types' } : item));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Workspace" items={workNavItems} />
                {adminItems.length > 0 && <NavMain label="Administration" items={adminItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
