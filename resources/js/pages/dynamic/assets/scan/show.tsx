import { Head, Link } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
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
import ChecklistCard from './checklist-card';
import type { ScanChecklist } from './checklist-card';

type ScannedAsset = {
    id: number;
    name: string;
    display_name: string;
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

type ScanMember = {
    id: number;
    name: string;
    category: string | null;
    brand: string;
    model: string;
    serial_number: string;
    identity: { label: string; value: string }[];
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
 * Compact one-line summary of a member's sheet-specific identity, falling
 * back to the generic model and serial number when the sheet has none.
 */
function memberDetail(member: ScanMember): string {
    if (member.identity.length > 0) {
        return member.identity
            .map((field) => `${field.label} ${field.value}`)
            .join(' - ');
    }

    return [member.model, member.serial_number].filter(Boolean).join(' - ');
}

/**
 * Reset the checklist card when the scanned asset or its saved entries
 * change, without an effect that writes state after render.
 */
function checklistStateKey(
    checklist: ScanChecklist | null,
    assetId: number,
): string {
    if (!checklist) {
        return `${assetId}:none`;
    }

    const entries = checklist.entries
        .map(
            (entry) =>
                `${entry.item_id}@${entry.time_slot}=${entry.value ?? ''}/${entry.status ?? ''}`,
        )
        .join(',');

    return `${assetId}:${checklist.date}:${checklist.category.id ?? 0}:${entries}`;
}

export default function AssetScanShow({
    asset,
    members,
    maintenances,
    assetOptions,
    checklist,
    canManage,
    canManageChecklist,
}: {
    asset: ScannedAsset;
    members: ScanMember[];
    maintenances: MaintenanceRow[];
    assetOptions: AssetOption[];
    checklist: ScanChecklist | null;
    canManage: boolean;
    canManageChecklist: boolean;
}) {
    return (
        <>
            <Head title={`Scan — ${asset.display_name}`} />

            <h1 className="sr-only">Scan {asset.display_name}</h1>

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="default"
                        title={asset.display_name}
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
                                        <p
                                            className={`text-sm font-medium ${
                                                field.key === 'capacity'
                                                    ? 'whitespace-pre-line'
                                                    : ''
                                            }`}
                                        >
                                            {field.key === 'capacity'
                                                ? asset[field.key].replace(
                                                      /;\s*/g,
                                                      '\n',
                                                  )
                                                : asset[field.key]}
                                        </p>
                                    </div>
                                ))}
                        </div>
                    </CardContent>
                </Card>

                {members.length > 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Anggota Grup</CardTitle>
                            <CardDescription>
                                {members.length} aset tergabung dalam perangkat
                                ini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                            <th className="px-4 py-3 font-medium">
                                                Kategori
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Merek
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Detail
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {members.map((member) => (
                                            <tr
                                                key={member.id}
                                                className="border-b border-sidebar-border/50 last:border-b-0"
                                            >
                                                <td className="px-4 py-3">
                                                    {member.category ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {member.brand || '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {memberDetail(member) ||
                                                        '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {asset.in_checklist && (
                    <ChecklistCard
                        key={checklistStateKey(checklist, asset.id)}
                        checklist={checklist}
                        canManage={canManageChecklist}
                    />
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
                                                <th className="w-1/4 truncate px-4 py-3 text-center font-medium">
                                                    Tanggal Mulai Maintenance
                                                </th>
                                                <th className="w-1/4 truncate px-4 py-3 text-center font-medium">
                                                    Tanggal Beres Maintenance
                                                </th>
                                                <th className="truncate px-4 py-3 text-center font-medium">
                                                    Status
                                                </th>
                                                <th className="truncate px-4 py-3 text-center font-medium">
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
                                                    <td className="truncate px-4 py-3 text-center">
                                                        {formatDate(
                                                            maintenance.maintenance_date,
                                                        )}
                                                    </td>
                                                    <td className="truncate px-4 py-3 text-center">
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
                                                    <td className="truncate px-4 py-3 text-center">
                                                        <Badge
                                                            variant="outline"
                                                            className={statusBadgeClassFor(
                                                                maintenance.status,
                                                            )}
                                                        >
                                                            {maintenance.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="truncate px-4 py-3 text-center">
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
                        Section checklist dan maintenance tidak diaktifkan untuk
                        aset ini.
                    </p>
                )}
            </div>
        </>
    );
}

AssetScanShow.layout = (props: {
    menus?: MenuRow[];
    asset?: { name: string; display_name?: string };
}) => {
    const parent = props.menus?.find((menu) => menu.url === '/assets');

    return {
        breadcrumbs: [
            {
                title: parent?.name ?? 'Asset Management',
                href: parent?.url ?? '/assets',
            },
            {
                title: props.asset?.display_name ?? props.asset?.name ?? 'Aset',
                href: '#',
            },
        ],
    };
};
