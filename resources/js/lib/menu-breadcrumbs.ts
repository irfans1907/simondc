import type { BreadcrumbItem } from '@/types';

export type MenuRow = {
    id: number;
    parent_id: number | null;
    name: string;
    url: string;
};

export type ChildMenuProps = {
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
};

/**
 * Build the parent > child breadcrumb straight from the server-provided menu
 * props (PageController::showChild and the asset/rack overview controllers).
 * Preferred over menuBreadcrumbs because it does not depend on the shared
 * menus lookup; returns undefined when either prop is missing.
 */
export function showChildCrumbs(
    props: ChildMenuProps,
): BreadcrumbItem[] | undefined {
    if (!props.menu || !props.activeChild) {
        return undefined;
    }

    return [
        { title: props.menu.name, href: props.menu.url || '#' },
        { title: props.activeChild.name, href: props.activeChild.url },
    ];
}

/**
 * Build a two-level parent > child breadcrumb for a child menu page from the
 * shared menus prop, keyed by the page's own URL. Returns undefined when the
 * URL is not a known child menu so callers can keep their single-crumb
 * fallback.
 */
export function menuBreadcrumbs(
    menus: MenuRow[] | undefined,
    currentUrl: string,
): BreadcrumbItem[] | undefined {
    const child = menus?.find((menu) => menu.url === currentUrl);

    if (!child) {
        return undefined;
    }

    const parent = child.parent_id
        ? menus?.find((menu) => menu.id === child.parent_id)
        : undefined;

    if (!parent) {
        return [{ title: child.name, href: child.url }];
    }

    return [
        { title: parent.name, href: parent.url || '#' },
        { title: child.name, href: child.url },
    ];
}
