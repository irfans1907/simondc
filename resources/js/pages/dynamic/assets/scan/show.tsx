import { Head, Link } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    CreateMaintenanceDialog,
    formatDate,
    resultBadgeClassFor,
    statusBadgeClassFor,
} from '@/components/maintenance-form';
import type { AssetOption } from '@/components/maintenance-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

type ScannedAsset = {
    id: number;
    name: string;
    category: string | null;
    brand: string;
    type: string;
    model: string;
    serial_number: string;
    capacity: string;
    manufacture_year: string;
    operation_year: string;
    quantity: string;
    floor: string;
    room: string;
    in_checklist: boolean;
    in_maintenance: boolean;
};

type MaintenanceRow = {
    id: number;
    maintenance_date: string;
    end_date: string | null;
    status: string;
    result: string;
};

const infoFields = [
    { label: 'Merek', key: 'brand' },
    { label: 'Model', key: 'model' },
    { label: 'Serial Number', key: 'serial_number' },
    { label: 'Kapasitas', key: 'capacity' },
    { label: 'Tahun Manufaktur', key: 'manufacture_year' },
    { label: 'Tahun Operasi', key: 'operation_year' },
    { label: 'Jumlah', key: 'quantity' },
    { label: 'Lantai', key: 'floor' },
    { label: 'Ruang', key: 'room' },
] as const;

/**
 * Placeholder checklist shown until the real item list is configured —
 * ticking boxes only holds local state for this visit.
 */
const placeholderChecklist = [
    'Kondisi fisik casing baik, tidak penyok atau retak',
    'Kabel terpasang rapi dan terlabel',
    'Label identitas perangkat lengkap dan terbaca',
    'Tidak ada tanda panas atau bekas korsleting',
    'Bersih dari debu dan sirkulasi udara lancar',
    'Dokumentasi pemutakhiran terbaru tersedia',
];

export default function AssetScanShow({
    asset,
    maintenances,
    assetOptions,
    canManage,
}: {
    asset: ScannedAsset;
    maintenances: MaintenanceRow[];
    assetOptions: AssetOption[];
    canManage: boolean;
}) {
    const [checkedItems, setCheckedItems] = useState<Set<number>>(
        () => new Set(),
    );

    function toggleItem(index: number): void {
        setCheckedItems((current) => {
            const next = new Set(current);

            if (next.has(index)) {
                next.delete(index);
            } else {
                next.add(index);
            }

            return next;
        });
    }

    return (
        <>
            <Head title={`Scan — ${asset.name}`} />

            <h1 className="sr-only">Scan {asset.name}</h1>

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="default"
                        title={asset.name}
                        description={
                            asset.category
                                ? `Kategori: ${asset.category}`
                                : 'Halaman hasil scan aset'
                        }
                        className="mb-0"
                    />

                    <Button variant="outline" asChild>
                        <Link href="/assets/scan">
                            <ScanLine className="size-4" />
                            Pindai Lagi
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Info Perangkat</CardTitle>
                        <CardDescription>
                            Data identitas aset yang berhasil dipindai.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            {infoFields
                                .filter((field) => asset[field.key])
                                .map((field) => (
                                    <div key={field.key}>
                                        <p className="text-xs text-muted-foreground">
                                            {field.label}
                                        </p>
                                        <p className="text-sm font-medium">
                                            {asset[field.key]}
                                        </p>
                                    </div>
                                ))}
                        </div>
                    </CardContent>
                </Card>

                {asset.in_checklist && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Checklist</CardTitle>
                            <CardDescription>
                                Placeholder — daftar item belum dikonfigurasi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {placeholderChecklist.map((item, index) => (
                                <label
                                    key={item}
                                    className="flex cursor-pointer items-start gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={checkedItems.has(index)}
                                        onChange={() => toggleItem(index)}
                                        className="mt-0.5 size-4 accent-green-600"
                                    />
                                    <span>{item}</span>
                                </label>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {asset.in_maintenance && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Riwayat Maintenance</CardTitle>
                            {canManage && (
                                <CreateMaintenanceDialog
                                    assetOptions={assetOptions}
                                    initialAssetId={asset.id}
                                    lockAsset
                                />
                            )}
                        </CardHeader>
                        <CardContent>
                            {maintenances.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada riwayat maintenance untuk aset
                                    ini.
                                </p>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                                    <table className="w-full table-fixed text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                                <th className="w-1/4 px-4 py-3 text-center font-medium truncate">
                                                    Tanggal Mulai Maintenance
                                                </th>
                                                <th className="w-1/4 px-4 py-3 text-center font-medium truncate">
                                                    Tanggal Beres Maintenance
                                                </th>
                                                <th className="px-4 py-3 text-center font-medium truncate">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-center font-medium truncate">
                                                    Hasil
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {maintenances.map((maintenance) => (
                                                <tr
                                                    key={maintenance.id}
                                                    className="border-b border-sidebar-border/50 last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 text-center truncate">
                                                        {formatDate(
                                                            maintenance.maintenance_date,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-center truncate">
                                                        {maintenance.end_date ? (
                                                            formatDate(
                                                                maintenance.end_date,
                                                            )
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-center truncate">
                                                        <Badge
                                                            variant="outline"
                                                            className={statusBadgeClassFor(
                                                                maintenance.status,
                                                            )}
                                                        >
                                                            {maintenance.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-center truncate">
                                                        {maintenance.result ? (
                                                            <Badge
                                                                variant="outline"
                                                                className={resultBadgeClassFor(
                                                                    maintenance.result,
                                                                )}
                                                            >
                                                                {
                                                                    maintenance.result
                                                                }
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {!asset.in_checklist && !asset.in_maintenance && (
                    <p className="text-sm text-muted-foreground">
                        Section checklist dan maintenance tidak diaktifkan
                        untuk aset ini.
                    </p>
                )}
            </div>
        </>
    );
}

AssetScanShow.layout = (props: {
    menus?: MenuRow[];
    asset?: { name: string };
}) => {
    const parent = props.menus?.find((menu) => menu.url === '/assets');

    return {
        breadcrumbs: [
            {
                title: parent?.name ?? 'Asset Management',
                href: parent?.url ?? '/assets',
            },
            {
                title: props.asset?.name ?? 'Aset',
                href: '#',
            },
        ],
    };
};
