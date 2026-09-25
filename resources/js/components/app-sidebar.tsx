import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Briefcase, Building2, FolderKanban, GitPullRequest, IdCard, LayoutGrid, ListChecks, Settings, ShieldCheck, UsersRound } from 'lucide-react';
import AppLogo from './app-logo';

/** Everyone signed in gets these. */
const workNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'My tasks', url: '/tasks', icon: ListChecks },
    { title: 'Projects', url: '/projects', icon: FolderKanban },
    { title: 'Merge requests', url: '/merge-requests', icon: GitPullRequest },
];

/** Each admin entry appears only for the permission its routes check. */
type AdminItem = Omit<NavItem, 'children'> & { permission: string | string[]; children?: (NavItem & { permission: string })[] };

const adminNavItems: AdminItem[] = [
    { title: 'Employees', url: '/admin/employees', icon: IdCard, permission: 'employees.view' },
    // Who can do what, and how the organisation is structured, under one entry.
    {
        title: 'People setup',
        url: '/admin/roles',
        icon: UsersRound,
        permission: ['roles.view', 'departments.view', 'designations.view'],
        children: [
            { title: 'Roles & access', url: '/admin/roles', icon: ShieldCheck, permission: 'roles.view' },
            { title: 'Departments', url: '/admin/departments', icon: Building2, permission: 'departments.view' },
            { title: 'Designations', url: '/admin/designations', icon: Briefcase, permission: 'designations.view' },
        ],
    },
    { title: 'Manage projects', url: '/admin/projects', icon: FolderKanban, permission: 'projects.view' },
    // The hub's overview shows every area the person may open.
    {
        title: 'Configuration hub',
        url: '/admin/config',
        icon: Settings,
        permission: ['settings.view', 'document_types.view'],
        match: ['/admin/settings', '/admin/config'],
    },
];

export function AppSidebar() {
    const { can, canAny } = usePermission();
    const adminItems = adminNavItems
        .filter((item) => (Array.isArray(item.permission) ? canAny(...item.permission) : can(item.permission)))
        // A group only lists the pages this person may open.
        .map((item) => (item.children ? { ...item, children: item.children.filter((child) => can(child.permission)) } : item));

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
