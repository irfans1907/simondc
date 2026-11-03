import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { CalendarSearch, Pencil, Search, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import Heading from '@/components/heading';
import {
    CreateMaintenanceDialog,
    MaintenanceFields,
    formatDate,
    resultBadgeClassFor,
    statusBadgeClassFor,
} from '@/components/maintenance-form';
import type { AssetOption, FormErrors, MaintenanceRow } from '@/components/maintenance-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { maintenance as maintenanceUrl } from '@/routes/assets';

type PaginatedMaintenances = {
    data: MaintenanceRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type MaintenanceFilters = {
    search: string;
    search_column: string;
    date: string;
    per_page: number;
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

/**
 * Display labels for stored ticket results: the UI speaks
 * Solved/Unresolved while the database keeps Normal/Masih Bermasalah.
 * Mirrors Troubleshoot::RESULT_LABELS on the backend.
 */
const ticketResultLabel = (result: string): string =>
    result === 'Normal' ? 'Solved' : 'Unresolved';

function FileLink({ path }: { path: string | null }) {
    if (!path) {
        return <span className="text-muted-foreground">-</span>;
    }

    return (
        <a
            href={`/storage/${path}`}
            target="_blank"
            rel="noreferrer"
            className="text-primary underline underline-offset-2"
        >
            Lihat
        </a>
    );
}

function UpdateMaintenanceDialog({
    maintenance,
    assetOptions,
}: {
    maintenance: MaintenanceRow;
    assetOptions: AssetOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Update
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Update maintenance</DialogTitle>
                <DialogDescription>
                    Update the maintenance record for{' '}
                    {maintenance.asset?.name ?? 'this asset'}. The asset
                    cannot be changed here; while its ticket is unresolved
                    the result is closed from the Troubleshoot page.
                </DialogDescription>

                <Form
                    {...MaintenanceController.update.form(maintenance.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['maintenances'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <MaintenanceFields
                                idPrefix={`edit-${maintenance.id}`}
                                maintenance={maintenance}
                                assetOptions={assetOptions}
                                errors={errors as FormErrors}
                                lockAsset
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteMaintenanceDialog({ maintenance }: { maintenance: MaintenanceRow }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete maintenance</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete this maintenance record?
                    Its trouble ticket survives as a manual ticket. This
                    action cannot be undone.
                </DialogDescription>

                <Form
                    {...MaintenanceController.destroy.form(maintenance.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['maintenances'] });
                    }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

const searchColumnOptions = [
    { value: 'all', label: 'Semua kolom' },
    { value: 'asset_name', label: 'Nama Perangkat' },
    { value: 'status', label: 'Status' },
    { value: 'result', label: 'Hasil' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari nama perangkat, status, atau hasil...',
    asset_name: 'Cari nama perangkat...',
    status: 'Cari status...',
    result: 'Cari hasil...',
};

function DateFilter({
    date,
    search,
    searchColumn,
    perPage,
    listUrl,
}: {
    date: string;
    search: string;
    searchColumn: string;
    perPage: number;
    listUrl: string;
}) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<Date | undefined>(
        date ? new Date(`${date}T00:00:00`) : undefined,
    );

    function applyDate(value: string) {
        router.get(
            listUrl,
            {
                search: search || undefined,
                search_column: searchColumn !== 'all' ? searchColumn : undefined,
                per_page: perPage,
                date: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['maintenances', 'filters'],
            },
        );
    }

    function onSelect(day: Date | undefined) {
        setSelected(day);
        setOpen(false);

        if (!day) {
            applyDate('');

            return;
        }

        const y = day.getFullYear();
        const m = String(day.getMonth() + 1).padStart(2, '0');
        const d = String(day.getDate()).padStart(2, '0');

        applyDate(`${y}-${m}-${d}`);
    }

    function clearDate() {
        setSelected(undefined);
        setOpen(false);
        applyDate('');
    }

    return (
        <div className="flex items-center gap-2">
            <Field className="w-45">
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="outline"
                            id="date"
                            className="justify-start gap-2 font-normal"
                        >
                            <CalendarSearch className="size-4 text-muted-foreground" />
                            {selected ? (
                                selected.toLocaleDateString('en-GB', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                })
                            ) : (
                                <span className="text-muted-foreground">
                                    Filter by date...
                                </span>
                            )}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                        <Calendar
                            mode="single"
                            selected={selected}
                            defaultMonth={selected}
                            captionLayout="dropdown"
                            onSelect={onSelect}
                        />
                    </PopoverContent>
                </Popover>
            </Field>
            {date && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={clearDate}
                    className="size-9"
                    aria-label="Clear date filter"
                    title="Clear date filter"
                >
                    <X className="size-4" />
                </Button>
            )}
        </div>
    );
}

function MaintenanceSearch({
    search,
    searchColumn,
    date,
    perPage,
    listUrl,
}: {
    search: string;
    searchColumn: string;
    date: string;
    perPage: number;
    listUrl: string;
}) {
    const [value, setValue] = useState(search);
    const [column, setColumn] = useState(searchColumn);

    const applyFilter = useCallback(
        (nextSearch: string, nextColumn: string) => {
            router.get(
                listUrl,
                {
                    search: nextSearch || undefined,
                    search_column:
                        nextColumn !== 'all' ? nextColumn : undefined,
                    date: date || undefined,
                    per_page: perPage,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['maintenances', 'filters'],
                },
            );
        },
        [date, perPage, listUrl],
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
        <div className="flex w-full flex-wrap items-center gap-2 sm:max-w-180">
            <Select
                value={column}
                onValueChange={(next) => {
                    setColumn(next);
                    applyFilter(value.trim(), next);
                }}
            >
                <SelectTrigger className="h-9 w-[160px] shrink-0">
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
                    id="maintenance-search"
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
            <DateFilter
                date={date}
                search={search}
                searchColumn={searchColumn}
                perPage={perPage}
                listUrl={listUrl}
            />
        </div>
    );
}

function PerPageSelector({
    search,
    searchColumn,
    date,
    perPage,
    listUrl,
}: {
    search: string;
    searchColumn: string;
    date: string;
    perPage: number;
    listUrl: string;
}) {
    function changePerPage(value: string) {
        router.get(
            listUrl,
            {
                search: search || undefined,
                search_column: searchColumn !== 'all' ? searchColumn : undefined,
                date: date || undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['maintenances', 'filters'],
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
    maintenances,
    search,
    searchColumn,
    date,
    perPage,
    listUrl,
}: {
    maintenances: PaginatedMaintenances;
    search: string;
    searchColumn: string;
    date: string;
    perPage: number;
    listUrl: string;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector
                search={search}
                searchColumn={searchColumn}
                date={date}
                perPage={perPage}
                listUrl={listUrl}
            />

            <span className="text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {maintenances.total}
                </span>{' '}
                total maintenance records
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {maintenances.links.map((link, index) =>
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
    'Tanggal Mulai Pengerjaan',
    'Tanggal Beres Pengerjaan',
    'Status',
    'Hasil',
    'Foto Eviden',
    'Dokumen',
    'Status Tiket',
    'Aksi',
] as const;

export default function AssetsMaintenance({
    maintenances,
    assetOptions,
    filters,
    canManage,
}: {
    maintenances: PaginatedMaintenances;
    assetOptions: AssetOption[];
    filters: MaintenanceFilters;
    canManage: boolean;
}) {
    const listUrl = maintenanceUrl.url();

    useEchoPublic('maintenance', '.maintenance.changed', () => {
        router.reload({ only: ['maintenances'] });
    });

    return (
        <>
            <Head title="Maintenance" />

            <h1 className="sr-only">Maintenance</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Maintenance"
                    description="Manage asset maintenance records and their trouble tickets"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <MaintenanceSearch
                        search={filters.search}
                        searchColumn={filters.search_column}
                        date={filters.date}
                        perPage={filters.per_page}
                        listUrl={listUrl}
                    />
                    {canManage && (
                        <div className="flex items-center gap-2">
                            <Button
                                variant="default"
                                className="bg-green-600 text-white hover:bg-green-700"
                                asChild
                            >
                                <a
                                    href={MaintenanceController.exportMethod.url(
                                        {
                                            query: {
                                                search:
                                                    filters.search ||
                                                    undefined,
                                                search_column:
                                                    filters.search_column !==
                                                    'all'
                                                        ? filters.search_column
                                                        : undefined,
                                                date:
                                                    filters.date || undefined,
                                            },
                                        },
                                    )}
                                >
                                    <Upload className="size-4" />
                                    Export
                                </a>
                            </Button>
                            <CreateMaintenanceDialog
                                assetOptions={assetOptions}
                            />
                        </div>
                    )}
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
                            {maintenances.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada maintenance ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                maintenances.data.map((maintenance, index) => (
                                    <tr
                                        key={maintenance.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {(maintenances.current_page - 1) *
                                                maintenances.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {maintenance.asset?.name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {formatDate(
                                                maintenance.maintenance_date,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
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
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClassFor(
                                                    maintenance.status,
                                                )}
                                            >
                                                {maintenance.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {maintenance.result ? (
                                                <Badge
                                                    variant="outline"
                                                    className={resultBadgeClassFor(
                                                        maintenance.result,
                                                    )}
                                                >
                                                    {maintenance.result}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <FileLink
                                                path={maintenance.evidence_photo}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <FileLink
                                                path={maintenance.document}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {maintenance.troubleshoot ? (
                                                <Badge
                                                    variant="outline"
                                                    className={resultBadgeClassFor(
                                                        maintenance.troubleshoot
                                                            .result,
                                                    )}
                                                >
                                                    {ticketResultLabel(
                                                        maintenance.troubleshoot
                                                            .result,
                                                    )}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <UpdateMaintenanceDialog
                                                        maintenance={maintenance}
                                                        assetOptions={
                                                            assetOptions
                                                        }
                                                    />
                                                    <DeleteMaintenanceDialog
                                                        maintenance={maintenance}
                                                    />
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    maintenances={maintenances}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    date={filters.date}
                    perPage={filters.per_page}
                    listUrl={listUrl}
                />
            </div>
        </>
    );
}

AssetsMaintenance.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, maintenanceUrl.url()) ?? [
            { title: 'Maintenance', href: maintenanceUrl.url() },
        ],
});
