import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Briefcase, Building2, FolderKanban, IdCard, LayoutGrid, ListChecks, Users } from 'lucide-react';
import AppLogo from './app-logo';

/** Everyone signed in gets these. */
const workNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'My tasks', url: '/tasks', icon: ListChecks },
    { title: 'Projects', url: '/projects', icon: FolderKanban },
];

/** Only shown to users who can reach /admin. */
const adminNavItems: NavItem[] = [
    { title: 'Users', url: '/admin/users', icon: Users },
    { title: 'Employees', url: '/admin/employees', icon: IdCard },
    { title: 'Departments', url: '/admin/departments', icon: Building2 },
    { title: 'Designations', url: '/admin/designations', icon: Briefcase },
    { title: 'Manage projects', url: '/admin/projects', icon: FolderKanban },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const canManagePeople = auth.user?.role === 'admin' || auth.user?.role === 'hr';

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
                {canManagePeople && <NavMain label="Administration" items={adminNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
