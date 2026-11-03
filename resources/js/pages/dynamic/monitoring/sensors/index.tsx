import { Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import MonitoringPanel from '@/components/monitoring-panel';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

export default function MonitoringSensors() {
    useEchoPublic('monitoring', '.monitoring.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Sensors · Monitoring" />
            <h1 className="sr-only">Monitoring Sensors</h1>
            <MonitoringPanel tab="sensors" />
        </>
    );
}

MonitoringSensors.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/monitoring/sensors') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
