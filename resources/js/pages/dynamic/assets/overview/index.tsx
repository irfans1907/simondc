import { Head } from '@inertiajs/react';
import { AlertTriangle, Boxes, FolderTree, Wrench } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { overview as assetsOverview } from '@/routes/assets';

type Summary = {
    total_assets: number | null;
    total_categories: number | null;
    total_maintenances: number | null;
    total_troubleshoots: number | null;
};

const summaryCards = [
    {
        key: 'total_assets',
        title: 'Total Aset',
        hint: 'Seluruh aset terdaftar',
        icon: Boxes,
    },
    {
        key: 'total_categories',
        title: 'Kategori',
        hint: 'Kategori aset aktif',
        icon: FolderTree,
    },
    {
        key: 'total_maintenances',
        title: 'Maintenance',
        hint: 'Jadwal maintenance',
        icon: Wrench,
    },
    {
        key: 'total_troubleshoots',
        title: 'Troubleshoot',
        hint: 'Laporan troubleshoot',
        icon: AlertTriangle,
    },
] as const;

export default function AssetsOverview({ summary }: { summary: Summary }) {
    return (
        <>
            <Head title="Asset Overview" />

            <h1 className="sr-only">Asset Overview</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Asset Overview"
                    description="Ringkasan inventaris aset data center"
                />

                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => {
                        const Icon = card.icon;
                        const value = summary[card.key];

                        return (
                            <Card key={card.key}>
                                <CardHeader className="flex flex-row items-center justify-between gap-2 pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        {card.title}
                                    </CardTitle>
                                    <Icon className="size-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <p className="text-2xl font-semibold">
                                        {value ?? '-'}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {value === null
                                            ? 'Menunggu data'
                                            : card.hint}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

AssetsOverview.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs: showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, assetsOverview.url()) ?? [
            { title: 'Asset Management', href: assetsOverview() },
        ],
});
