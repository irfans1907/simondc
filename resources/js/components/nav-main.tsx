import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

function normalizeUrl(url: string): string {
    try {
        const parsed = new URL(url, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');

        return parsed.pathname.replace(/\/$/, '');
    } catch {
        return url;
    }
}

export function NavMain({
    label,
    items = [],
}: {
    label: string;
    items: NavItem[];
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { url: currentUrl } = usePage();

    const currentNormalized = normalizeUrl(currentUrl);

    const activeParent = items.find(
        (item) =>
            (item.children?.length ?? 0) > 0 &&
            (item.children!.some(
                (child) => normalizeUrl(String(child.href)) === currentNormalized,
            ) || isCurrentUrl(item.href, undefined, true)),
    );

    const [openItem, setOpenItem] = useState<string | null>(activeParent?.title ?? null);
    const [prevUrl, setPrevUrl] = useState(currentNormalized);

    if (prevUrl !== currentNormalized) {
        setPrevUrl(currentNormalized);
        setOpenItem(activeParent?.title ?? null);
    }

    function isChildActive(child: NavItem): boolean {
        return normalizeUrl(String(child.href)) === currentNormalized;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasChildren = (item.children?.length ?? 0) > 0;

                    if (!hasChildren) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    const hasActiveChild = item.children!.some(isChildActive);

                    return (
                        <Collapsible
                            key={item.title}
                            open={openItem === item.title}
                            onOpenChange={(open) =>
                                setOpenItem(open ? item.title : null)
                            }
                            className="group/collapsible"
                            asChild
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton isActive={hasActiveChild}>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.children!.map((child) => (
                                            <SidebarMenuSubItem key={child.title}>
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={isChildActive(child)}
                                                >
                                                    <Link href={child.href} prefetch>
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
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
