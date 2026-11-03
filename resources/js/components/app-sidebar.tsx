import { Link, router, usePage } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { icons as lucideIcons } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as activityLogIndex } from '@/routes/activity-log';
import { index as menusIndex } from '@/routes/menus';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { Auth, Menu as MenuType, NavItem } from '@/types';

function resolveIcon(iconName: string | null): LucideIcon {
    if (iconName && iconName in lucideIcons) {
        return lucideIcons[iconName as keyof typeof lucideIcons] as LucideIcon;
    }

    return lucideIcons.Menu as LucideIcon;
}

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth, menus } = usePage<{ auth: Auth; menus: MenuType[] }>().props;
    const { permissions, is_superadmin } = auth;
    const dynamicMenus = menus ?? [];

    function hasPermission(permission: string): boolean {
        return is_superadmin || permissions.includes(permission);
    }

    useEchoPublic('roles', '.roles.changed', () => {
        router.reload();
    });

    useEchoPublic('permissions', '.permissions.changed', () => {
        router.reload();
    });

    useEchoPublic('menus', '.menus.changed', () => {
        router.reload();
    });

    // Build menu tree: top-level items get children; child visibility follows parent permission.
    const visibleMenus = dynamicMenus.filter((menu) => {
        if (menu.parent_id) {
            const parent = dynamicMenus.find((m) => m.id === menu.parent_id);

            return parent ? hasPermission('view '.concat(parent.name.toLowerCase())) : false;
        }

        return hasPermission('view '.concat(menu.name.toLowerCase()));
    });

    const groupedMenus = visibleMenus
        .filter((menu) => !menu.parent_id)
        .reduce<Record<string, NavItem[]>>((acc, menu) => {
            const group = menu.group || 'Menus';

            if (!acc[group]) {
                acc[group] = [];
            }

            acc[group].push({
                title: menu.name,
                href: menu.url || '#',
                icon: resolveIcon(menu.icon),
                children: visibleMenus
                    .filter((child) => child.parent_id === menu.id)
                    .map((child) => ({
                        title: child.name,
                        href: child.url || '#',
                        icon: null,
                    })),
            });

            return acc;
        }, {});

    const userRoleNavItems = [
        ...(hasPermission('manage activity log')
            ? [{ title: 'Activity Log', href: activityLogIndex(), icon: lucideIcons.ScrollText as LucideIcon }]
            : []),
        ...(hasPermission('manage users')
            ? [{ title: 'Users Management', href: usersIndex(), icon: lucideIcons.UserCog as LucideIcon }]
            : []),
        ...(hasPermission('manage roles')
            ? [{ title: 'Roles Management', href: rolesIndex(), icon: lucideIcons.UserPen as LucideIcon }]
            : []),
        ...(hasPermission('manage permissions')
            ? [{ title: 'Permissions Management', href: permissionsIndex(), icon: lucideIcons.Shield as LucideIcon }]
            : []),
        ...(hasPermission('manage menus')
            ? [{ title: 'Menu Management', href: menusIndex(), icon: lucideIcons.Menu as LucideIcon }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <div className="border-b-2 border-sidebar-border/50" />

            <SidebarContent>
                {Object.entries(groupedMenus).map(([group, items]) => (
                    <NavMain key={group} label={group} items={items} />
                ))}
                {userRoleNavItems.length > 0 && (
                    <NavMain label="Application Management" items={userRoleNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <div className="px-2 pb-2 text-center text-xs text-sidebar-foreground/60 group-data-[collapsible=icon]:hidden">
                    © 2026 Sistem Monitoring dan Kontrol Data Center.<br />All rights reserved.
                </div>
                <div
                    className="hidden justify-center pb-2 text-sidebar-foreground/60 group-data-[collapsible=icon]:flex"
                    title="© 2026 Sistem Monitoring dan Kontrol Data Center. All rights reserved."
                >
                    <lucideIcons.Copyright className="h-4 w-4" />
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
