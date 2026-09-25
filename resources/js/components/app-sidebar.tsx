import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Briefcase, Building2, FolderKanban, IdCard, LayoutGrid, ListChecks, Settings, ShieldCheck, Users } from 'lucide-react';
import AppLogo from './app-logo';

/** Everyone signed in gets these. */
const workNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'My tasks', url: '/tasks', icon: ListChecks },
    { title: 'Projects', url: '/projects', icon: FolderKanban },
];

/** Each admin entry appears only for the permission its routes check. */
const adminNavItems: (NavItem & { permission: string })[] = [
    { title: 'Users', url: '/admin/users', icon: Users, permission: 'users.manage' },
    { title: 'Roles & access', url: '/admin/roles', icon: ShieldCheck, permission: 'roles.manage' },
    { title: 'Employees', url: '/admin/employees', icon: IdCard, permission: 'employees.view' },
    { title: 'Departments', url: '/admin/departments', icon: Building2, permission: 'masters.manage' },
    { title: 'Designations', url: '/admin/designations', icon: Briefcase, permission: 'masters.manage' },
    { title: 'Manage projects', url: '/admin/projects', icon: FolderKanban, permission: 'projects.manage' },
    { title: 'Settings', url: '/admin/settings', icon: Settings, permission: 'settings.manage' },
];

export function AppSidebar() {
    const { can } = usePermission();
    const adminItems = adminNavItems.filter((item) => can(item.permission));

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
