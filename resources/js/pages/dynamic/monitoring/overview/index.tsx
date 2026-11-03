import { Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import MonitoringPanel from '@/components/monitoring-panel';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

export default function MonitoringOverview() {
    useEchoPublic('monitoring', '.monitoring.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Overview · Monitoring" />
            <h1 className="sr-only">Monitoring Overview</h1>
            <MonitoringPanel tab="overview" />
        </>
    );
}

MonitoringOverview.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/monitoring/overview') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
