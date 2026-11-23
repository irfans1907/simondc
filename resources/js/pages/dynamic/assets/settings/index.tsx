import { Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { QrCode, Search, X } from 'lucide-react';
import { toDataURL } from 'qrcode';
import { useCallback, useEffect, useState } from 'react';
import AssetScanController from '@/actions/App/Http/Controllers/AssetScanController';
import AssetSettingsController from '@/actions/App/Http/Controllers/AssetSettingsController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

type AssetMember = {
    id: number;
    name: string;
    category: string | null;
    serial_number: string;
};

type AssetRow = {
    id: number;
    asset_ids: number[];
    name: string;
    category: string | null;
    brand: string;
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
    checklist_category_id: number | null;
    scan_group: { name: string; category_id: number } | null;
    members: AssetMember[];
};

type ChecklistCategoryOption = {
    id: number;
    name: string;
    slug: string;
};

type PaginatedAssets = {
    data: AssetRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type AssetSettingsFilters = {
    search: string;
    search_column: string;
    per_page: number;
};

type FlagColumn = 'in_checklist' | 'in_maintenance';

const CHECKLIST_NONE = 'none';
const CHECKLIST_UNASSIGNED = 'unassigned';

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

const searchColumnOptions = [
    { value: 'all', label: 'Semua kolom' },
    { value: 'name', label: 'Nama Perangkat' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari nama, merek, model, atau SN...',
    name: 'Cari nama perangkat...',
};

function FlagRadios({
    assetId,
    column,
    value,
    disabled,
    onChange,
}: {
    assetId: number;
    column: FlagColumn;
    value: boolean;
    disabled: boolean;
    onChange: (next: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-center gap-4">
            {[true, false].map((option) => (
                <label
                    key={String(option)}
                    className={`flex items-center gap-1.5 text-sm ${
                        disabled
                            ? 'cursor-not-allowed text-muted-foreground'
                            : 'cursor-pointer'
                    }`}
                >
                    <input
                        type="radio"
                        name={`${column}-${assetId}`}
                        value={String(option)}
                        checked={value === option}
                        onChange={() => onChange(option)}
                        disabled={disabled}
                        className="size-4 accent-green-600"
                    />
                    {option ? 'Ya' : 'Tidak'}
                </label>
            ))}
        </div>
    );
}

function ChecklistSelect({
    asset,
    categories,
    disabled,
    onChange,
}: {
    asset: AssetRow;
    categories: ChecklistCategoryOption[];
    disabled: boolean;
    onChange: (next: {
        inChecklist: boolean;
        categoryId: number | null;
    }) => void;
}) {
    const value = !asset.in_checklist
        ? CHECKLIST_NONE
        : asset.checklist_category_id !== null
          ? String(asset.checklist_category_id)
          : CHECKLIST_UNASSIGNED;

    return (
        <Select
            value={value}
            disabled={disabled}
            onValueChange={(next) => {
                if (next === CHECKLIST_NONE) {
                    onChange({ inChecklist: false, categoryId: null });

                    return;
                }

                if (next === CHECKLIST_UNASSIGNED) {
                    onChange({ inChecklist: true, categoryId: null });

                    return;
                }

                onChange({ inChecklist: true, categoryId: Number(next) });
            }}
        >
            <SelectTrigger className="h-10 w-full min-w-[240px]">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={CHECKLIST_NONE} className="py-2">
                    Tidak masuk checklist
                </SelectItem>
                <SelectItem value={CHECKLIST_UNASSIGNED} className="py-2">
                    Belum dipilih
                </SelectItem>
                {categories.map((category) => (
                    <SelectItem
                        key={category.id}
                        value={String(category.id)}
                        className="py-2"
                    >
                        {category.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

type QrCodeEntry = AssetMember & { src: string };

function qrSlug(name: string, id: number): string {
    return (
        name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || `asset-${id}`
    );
}

function QrCodeDialog({
    asset,
    onClose,
}: {
    asset: AssetRow | null;
    onClose: () => void;
}) {
    const [qrCode, setQrCode] = useState<QrCodeEntry | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!asset) {
            return;
        }

        let cancelled = false;

        const target: AssetMember = asset.members[0] ?? {
            id: asset.id,
            name: asset.name,
            category: asset.category,
            serial_number: asset.serial_number,
        };

        // The QR encodes the scan page URL on the origin the admin is
        // browsing from, so devices on the same host can open it. Grouped
        // rows use one sticker for the whole group: UPS by name, CCTV by
        // name plus device category.
        const scanUrl = new URL(
            asset.members.length > 1
                ? AssetScanController.group.url({
                      query: asset.scan_group
                          ? {
                                name: asset.scan_group.name,
                                category: asset.scan_group.category_id,
                            }
                          : { name: asset.name },
                  })
                : AssetScanController.show.url(target.id),
            window.location.origin,
        ).href;

        toDataURL(scanUrl, { width: 320, margin: 2 })
            .then((src) => {
                if (!cancelled) {
                    setQrCode({ ...target, src });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('QR code gagal dibuat.');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [asset]);

    function close(): void {
        setQrCode(null);
        setError(null);
        onClose();
    }

    if (!asset) {
        return null;
    }

    const isGroup = asset.members.length > 1;

    return (
        <Dialog
            open={asset !== null}
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>QR {asset.name}</DialogTitle>
                    <DialogDescription>
                        {isGroup
                            ? `Satu QR untuk seluruh grup ini (${asset.members.length} aset). Pindai untuk membuka halaman scan grup.`
                            : 'Pindai QR untuk membuka halaman scan aset ini.'}
                    </DialogDescription>
                </DialogHeader>

                {error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : qrCode === null ? (
                    <p className="text-sm text-muted-foreground">
                        Membuat QR code...
                    </p>
                ) : (
                    <div className="flex justify-center">
                        <div className="flex flex-col items-center gap-2 rounded-lg border border-sidebar-border/70 p-3">
                            <img
                                src={qrCode.src}
                                alt={`QR ${qrCode.name}`}
                                className="size-48 rounded-md bg-white"
                            />
                            <div className="text-center text-sm">
                                <p className="font-medium">{qrCode.name}</p>
                                <p className="text-xs text-muted-foreground">
                                    {isGroup
                                        ? `${asset.members.length} aset dalam grup`
                                        : [
                                              qrCode.category,
                                              qrCode.serial_number,
                                          ]
                                              .filter(Boolean)
                                              .join(' · ') || `ID ${qrCode.id}`}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const link = document.createElement('a');
                                    link.href = qrCode.src;
                                    link.download = `qr-${qrSlug(qrCode.name, qrCode.id)}.png`;
                                    link.click();
                                }}
                            >
                                Unduh PNG
                            </Button>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button>Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AssetSettingsSearch({
    search,
    searchColumn,
    perPage,
}: {
    search: string;
    searchColumn: string;
    perPage: number;
}) {
    const [value, setValue] = useState(search);
    const [column, setColumn] = useState(searchColumn);

    const applyFilter = useCallback(
        (nextSearch: string, nextColumn: string) => {
            router.get(
                AssetSettingsController.index.url(),
                {
                    search: nextSearch || undefined,
                    search_column:
                        nextColumn !== 'all' ? nextColumn : undefined,
                    per_page: perPage,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['assets', 'filters'],
                },
            );
        },
        [perPage],
    );

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search && column === searchColumn) {
                return;
            }

            applyFilter(query, column);
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, column, search, searchColumn, applyFilter]);

    return (
        <div className="flex w-full flex-wrap items-center gap-2 sm:max-w-xl">
            <Select
                value={column}
                onValueChange={(next) => {
                    setColumn(next);
                    applyFilter(value.trim(), next);
                }}
            >
                <SelectTrigger className="h-9 w-[150px] shrink-0">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {searchColumnOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <div className="relative min-w-[200px] flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id="asset-settings-search"
                    name="search"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Escape') {
                            setValue('');
                        }
                    }}
                    placeholder={
                        searchColumnPlaceholders[column] ??
                        searchColumnPlaceholders.all
                    }
                    className="pr-9 pl-9"
                />
                {value && (
                    <button
                        type="button"
                        onClick={() => setValue('')}
                        className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        aria-label="Clear search"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
        </div>
    );
}

function PerPageSelector({
    search,
    searchColumn,
    perPage,
}: {
    search: string;
    searchColumn: string;
    perPage: number;
}) {
    function changePerPage(value: string) {
        router.get(
            AssetSettingsController.index.url(),
            {
                search: search || undefined,
                search_column:
                    searchColumn !== 'all' ? searchColumn : undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['assets', 'filters'],
            },
        );
    }

    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Show</span>
            <Select value={String(perPage)} onValueChange={changePerPage}>
                <SelectTrigger className="h-9 w-[72px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent side="top">
                    {perPageOptions.map((option) => (
                        <SelectItem key={option} value={String(option)}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <span className="text-muted-foreground">per page</span>
        </div>
    );
}

function Pagination({
    assets,
    search,
    searchColumn,
    perPage,
}: {
    assets: PaginatedAssets;
    search: string;
    searchColumn: string;
    perPage: number;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector
                search={search}
                searchColumn={searchColumn}
                perPage={perPage}
            />

            <span className="text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {assets.total}
                </span>{' '}
                total assets
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {assets.links.map((link, index) =>
                        link.url === null ? (
                            <span
                                key={index}
                                className="px-3 py-1.5 text-muted-foreground"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 ${
                                    link.active
                                        ? 'bg-primary font-medium text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </div>
            </div>
        </div>
    );
}

const tableHeaders = [
    'No',
    'Nama Perangkat',
    'Checklist',
    'Maintenance',
    'QR',
] as const;

export default function AssetsSettings({
    assets,
    filters,
    canManage,
    checklistCategories,
}: {
    assets: PaginatedAssets;
    filters: AssetSettingsFilters;
    canManage: boolean;
    checklistCategories: ChecklistCategoryOption[];
}) {
    useEchoPublic('assets', '.assets.changed', () => {
        router.reload();
    });

    const [savingId, setSavingId] = useState<number | null>(null);
    const [qrAsset, setQrAsset] = useState<AssetRow | null>(null);

    function toggleFlag(
        asset: AssetRow,
        column: FlagColumn,
        next: boolean,
    ): void {
        router.patch(
            AssetSettingsController.bulkUpdate.url(),
            { asset_ids: asset.asset_ids, [column]: next },
            {
                preserveScroll: true,
                onStart: () => setSavingId(asset.id),
                onFinish: () => setSavingId(null),
            },
        );
    }

    function saveChecklist(
        asset: AssetRow,
        inChecklist: boolean,
        categoryId: number | null,
    ): void {
        router.patch(
            AssetSettingsController.bulkUpdate.url(),
            {
                asset_ids: asset.asset_ids,
                in_checklist: inChecklist,
                checklist_category_id: categoryId,
            },
            {
                preserveScroll: true,
                onStart: () => setSavingId(asset.id),
                onFinish: () => setSavingId(null),
            },
        );
    }

    return (
        <>
            <Head title="Asset Settings" />

            <h1 className="sr-only">Asset Settings</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Asset Settings"
                    description="Atur checklist, maintenance, dan deskripsi yang tampil saat barcode aset dipindai"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <AssetSettingsSearch
                        search={filters.search}
                        searchColumn={filters.search_column}
                        perPage={filters.per_page}
                    />

                    <Button variant="outline" asChild>
                        <Link href={AssetScanController.index.url()}>
                            <QrCode className="size-4" />
                            Pindai QR
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {tableHeaders.map((header) => (
                                    <th
                                        key={header}
                                        className={`px-4 py-3 text-center font-medium ${
                                            header === 'No' ? 'w-14' : ''
                                        }`}
                                    >
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {assets.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada aset ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                assets.data.map((asset, index) => (
                                    <tr
                                        key={asset.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {(assets.current_page - 1) *
                                                assets.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {asset.name}
                                        </td>
                                        <td className="min-w-[260px] px-4 py-3">
                                            <ChecklistSelect
                                                asset={asset}
                                                categories={checklistCategories}
                                                disabled={
                                                    !canManage ||
                                                    savingId !== null
                                                }
                                                onChange={({
                                                    inChecklist,
                                                    categoryId,
                                                }) =>
                                                    saveChecklist(
                                                        asset,
                                                        inChecklist,
                                                        categoryId,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <FlagRadios
                                                assetId={asset.id}
                                                column="in_maintenance"
                                                value={asset.in_maintenance}
                                                disabled={
                                                    !canManage ||
                                                    savingId !== null
                                                }
                                                onChange={(next) =>
                                                    toggleFlag(
                                                        asset,
                                                        'in_maintenance',
                                                        next,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setQrAsset(asset)
                                                }
                                                aria-label={`Tampilkan QR ${asset.name}`}
                                            >
                                                <QrCode className="size-4" />
                                                QR
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    assets={assets}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    perPage={filters.per_page}
                />

                <QrCodeDialog
                    key={qrAsset?.id ?? 'closed'}
                    asset={qrAsset}
                    onClose={() => setQrAsset(null)}
                />
            </div>
        </>
    );
}

AssetsSettings.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs: showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/assets/settings') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
