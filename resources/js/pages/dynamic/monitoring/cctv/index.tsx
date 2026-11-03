import { Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import MonitoringPanel from '@/components/monitoring-panel';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

export default function MonitoringCctv() {
    useEchoPublic('monitoring', '.monitoring.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="CCTV · Monitoring" />
            <h1 className="sr-only">Monitoring CCTV</h1>
            <MonitoringPanel tab="cctv" />
        </>
    );
}

MonitoringCctv.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/monitoring/cctv') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
