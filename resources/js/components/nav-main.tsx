import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { CSSProperties } from 'react';

/**
 * A nav icon in its section's colour — the light step made for the navy
 * sidebar. On the active pill it turns white with the label.
 */
function NavIcon({ item, className }: { item: NavItem; className?: string }) {
    if (!item.icon) {
        return null;
    }

    return (
        <item.icon
            className={cn('text-[var(--nav-icon)] in-data-[active=true]:text-current', className)}
            style={item.tone ? ({ '--nav-icon': `var(--nav-${item.tone})` } as CSSProperties) : undefined}
        />
    );
}

/** The active page is a brand-coloured pill, not a faint grey wash. */
const ACTIVE = 'data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground data-[active=true]:shadow-sm';

function useIsActive() {
    const page = usePage();

    // Keep the section highlighted on nested routes like /admin/roles/3/edit.
    return (item: NavItem) =>
        [item.url, ...(item.match ?? [])].some((url) => page.url === url || page.url.startsWith(`${url}/`) || page.url.startsWith(`${url}?`));
}

/**
 * A group of pages under one entry. Expanded, it opens in place like an
 * accordion (already open when you are on one of its pages); collapsed to
 * icons, the icon opens the same pages as a dropdown menu.
 */
function NavGroupItem({ item }: { item: NavItem & { children: NavItem[] } }) {
    const isActive = useIsActive();
    const { state, isMobile } = useSidebar();
    const open = item.children.some(isActive);

    if (state === 'collapsed' && !isMobile) {
        return (
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton isActive={open} tooltip={item.title} className={ACTIVE}>
                            <NavIcon item={item} />
                            <span>{item.title}</span>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent side="right" align="start" className="min-w-48">
                        <DropdownMenuLabel>{item.title}</DropdownMenuLabel>
                        {item.children.map((child) => (
                            <DropdownMenuItem key={child.url} asChild>
                                <Link href={child.url} prefetch className="flex items-center gap-2">
                                    <NavIcon item={child} className="size-4" />
                                    {child.title}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible asChild defaultOpen={open} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={item.title}>
                        <NavIcon item={item} />
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.children.map((child) => (
                            <SidebarMenuSubItem key={child.url}>
                                <SidebarMenuSubButton asChild isActive={isActive(child)} className={ACTIVE}>
                                    <Link href={child.url} prefetch>
                                        <NavIcon item={child} />
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({ items = [], label = 'Platform' }: { items: NavItem[]; label?: string }) {
    const isActive = useIsActive();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="text-sidebar-foreground/60 text-[11px] font-semibold tracking-wider uppercase">{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children?.length ? (
                        <NavGroupItem key={item.title} item={item as NavItem & { children: NavItem[] }} />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={isActive(item)} tooltip={item.title} className={ACTIVE}>
                                <Link href={item.url} prefetch>
                                    <NavIcon item={item} />
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
