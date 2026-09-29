import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { SECTIONS, type SectionKey } from '@/lib/sections';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import AppLogo from './app-logo';

/** A section's icon and colour, from the one table every page uses. */
const s = (key: SectionKey) => ({ icon: SECTIONS[key].icon, tone: SECTIONS[key].tone });

/** Everyone signed in gets these. */
const workNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', ...s('dashboard') },
    { title: 'My tasks', url: '/tasks', ...s('tasks') },
    { title: 'Projects', url: '/projects', ...s('projects') },
    { title: 'Testing', url: '/testing', ...s('testing') },
    { title: 'Merge requests', url: '/merge-requests', ...s('git') },
];

/** Each admin entry appears only for the permission its routes check. */
type AdminItem = Omit<NavItem, 'children'> & { permission: string | string[]; children?: (NavItem & { permission: string })[] };

const adminNavItems: AdminItem[] = [
    { title: 'Employees', url: '/admin/employees', ...s('employees'), permission: 'employees.view' },
    { title: 'Interns', url: '/admin/interns', ...s('interns'), permission: 'interns.view' },
    // Who can do what, and how the organisation is structured, under one entry.
    {
        title: 'People setup',
        url: '/admin/roles',
        ...s('people'),
        permission: ['roles.view', 'departments.view', 'designations.view'],
        children: [
            { title: 'Roles & access', url: '/admin/roles', ...s('roles'), permission: 'roles.view' },
            { title: 'Departments', url: '/admin/departments', ...s('departments'), permission: 'departments.view' },
            { title: 'Designations', url: '/admin/designations', ...s('designations'), permission: 'designations.view' },
        ],
    },
    { title: 'Manage projects', url: '/admin/projects', ...s('projects'), permission: 'projects.view' },
    {
        title: 'Accounts',
        url: '/accounts/invoices',
        ...s('accounts'),
        permission: ['invoices.view', 'customers.view', 'products.view'],
        children: [
            { title: 'Invoices', url: '/accounts/invoices', ...s('invoices'), permission: 'invoices.view' },
            { title: 'Customers', url: '/accounts/customers', ...s('customers'), permission: 'customers.view' },
            { title: 'Products & services', url: '/accounts/products', ...s('products'), permission: 'products.view' },
        ],
    },
    // The hub's overview shows every area the person may open.
    {
        title: 'Configuration hub',
        url: '/admin/config',
        ...s('config'),
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
