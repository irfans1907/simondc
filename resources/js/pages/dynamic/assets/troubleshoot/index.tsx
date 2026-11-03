import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { ArrowLeft, CalendarSearch, History, Pencil, Search, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import TroubleshootController from '@/actions/App/Http/Controllers/TroubleshootController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
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
import { troubleshoot as troubleshootUrl } from '@/routes/assets';

type AssetOption = {
    id: number;
    name: string;
};

type TroubleshootRow = {
    id: number;
    ticket_number: string;
    maintenance_id: number | null;
    troubleshoot_date: string;
    started_date: string | null;
    closed_date: string | null;
    result: string;
    evidence_photo: string | null;
    document: string | null;
    asset?: { id: number; name: string } | null;
    maintenance?: {
        id: number;
        maintenance_date: string;
        result: string;
    } | null;
};

type PaginatedTroubleshoots = {
    data: TroubleshootRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type TroubleshootFilters = {
    search: string;
    search_column: string;
    date: string;
    per_page: number;
};

/**
 * Per-asset history panel: every ticket the asset ever had (with its
 * origin maintenance) plus its maintenance records. Null when no
 * ?history_asset= param is present.
 */
type HistoryPayload = {
    asset: { id: number; name: string };
    tickets: TroubleshootRow[];
    maintenances: {
        id: number;
        maintenance_date: string;
        end_date: string | null;
        status: string;
        result: string | null;
    }[];
} | null;

type FormErrors = Partial<Record<string, string | undefined>>;

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

const resultOptions = [
    { value: 'Normal', label: 'Solved' },
    { value: 'Masih Bermasalah', label: 'Unresolved' },
] as const;

/**
 * Badge colours by result: green for Normal, rose for anything still
 * broken (Masih Bermasalah), slate as the fallback.
 */
const resultBadgeClassFor = (result: string): string => {
    if (result === 'Normal') {
        return 'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400';
    }

    if (result === 'Bermasalah' || result === 'Masih Bermasalah') {
        return 'border-rose-600/50 bg-rose-600/10 text-rose-600 dark:text-rose-400';
    }

    return 'border-slate-600/50 bg-slate-600/10 text-slate-600 dark:text-slate-400';
};

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

/**
 * Render a Y-m-d value as dd/mm/yyyy (en-GB), the format used across
 * the project (Activity Log, Files). The T00:00:00 suffix pins parsing
 * to local midnight so a date-only string never shifts a day.
 */
function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Badge colours by maintenance workflow status: slate for Scheduled,
 * amber for On Progress, green for Done (mirrors the Maintenance page).
 */
const maintenanceStatusBadgeClassFor = (status: string): string => {
    if (status === 'Scheduled') {
        return 'border-slate-600/50 bg-slate-600/10 text-slate-600 dark:text-slate-400';
    }

    if (status === 'On Progress') {
        return 'border-amber-600/50 bg-amber-600/10 text-amber-600 dark:text-amber-400';
    }

    return 'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400';
};

function TroubleshootFields({
    idPrefix,
    troubleshoot,
    assetOptions,
    errors,
}: {
    idPrefix: string;
    troubleshoot?: TroubleshootRow;
    assetOptions: AssetOption[];
    errors: FormErrors;
}) {
    const [assetId, setAssetId] = useState(
        troubleshoot?.asset?.id ? String(troubleshoot.asset.id) : '',
    );
    const [result, setResult] = useState(troubleshoot?.result ?? '');
    const [closedDate, setClosedDate] = useState(
        troubleshoot?.closed_date ?? '',
    );

    const isSolved = result === 'Normal';
    const lockAsset = troubleshoot !== undefined;

    const resultHint = () => {
        if (result === 'Normal') {
            return 'Apabila Solved, hasil maintenance kembali Normal.';
        }

        if (result === 'Masih Bermasalah') {
            return 'Tiket tetap terbuka untuk ditindaklanjuti.';
        }

        return 'Solved menutup tiket; Unresolved membuka kembali.';
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-asset_id`}>Nama Perangkat</Label>
                <Select
                    value={assetId}
                    onValueChange={setAssetId}
                    disabled={lockAsset}
                >
                    <SelectTrigger id={`${idPrefix}-asset_id`} className="w-full">
                        <SelectValue placeholder="Pilih nama perangkat" />
                    </SelectTrigger>
                    <SelectContent>
                        {assetOptions.map((option) => (
                            <SelectItem key={option.id} value={String(option.id)}>
                                {option.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="asset_id" value={assetId} />
                <InputError message={errors.asset_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-troubleshoot_date`}>Tanggal Tiket Dibuat</Label>
                <Input
                    id={`${idPrefix}-troubleshoot_date`}
                    name="troubleshoot_date"
                    type="date"
                    className="mt-0 block w-full"
                    defaultValue={troubleshoot?.troubleshoot_date ?? ''}
                    disabled={lockAsset}
                    required
                />
                {lockAsset && (
                    <input
                        type="hidden"
                        name="troubleshoot_date"
                        value={troubleshoot?.troubleshoot_date ?? ''}
                    />
                )}
                <InputError message={errors.troubleshoot_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-started_date`}>
                    Tanggal Mulai Pengerjaan
                </Label>
                <Input
                    id={`${idPrefix}-started_date`}
                    name="started_date"
                    type="date"
                    className="mt-0 block w-full"
                    defaultValue={troubleshoot?.started_date ?? ''}
                />
                <p className="text-muted-foreground text-xs">
                    {isSolved
                        ? 'Wajib diisi untuk menutup tiket.'
                        : 'Diisi saat pengerjaan dimulai.'}
                </p>
                <InputError message={errors.started_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-closed_date`}>
                    Tanggal Selesai Pengerjaan
                </Label>
                <Input
                    id={`${idPrefix}-closed_date`}
                    name="closed_date"
                    type="date"
                    className="mt-0 block w-full"
                    value={closedDate}
                    onChange={(e) => setClosedDate(e.target.value)}
                    disabled={!isSolved}
                    required={isSolved}
                />
                <p className="text-muted-foreground text-xs">
                    {isSolved
                        ? 'Wajib diisi untuk menutup tiket.'
                        : 'Diisi saat tiket ditutup (Solved).'}
                </p>
                <InputError message={errors.closed_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-result`}>Hasil</Label>
                <Select
                    value={result}
                    onValueChange={(next) => {
                        setResult(next);

                        // Only a Solved ticket carries a close date.
                        if (next !== 'Normal') {
                            setClosedDate('');
                        }
                    }}
                >
                    <SelectTrigger id={`${idPrefix}-result`} className="w-full">
                        <SelectValue placeholder="Pilih hasil" />
                    </SelectTrigger>
                    <SelectContent>
                        {resultOptions.map((option) => (
                            <SelectItem
                                key={option.value}
                                value={option.value}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="result" value={result} />
                <p className="text-muted-foreground text-xs">
                    {resultHint()}
                </p>
                <InputError message={errors.result} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-evidence_photo`}>Foto Eviden</Label>
                <Input
                    id={`${idPrefix}-evidence_photo`}
                    name="evidence_photo"
                    type="file"
                    accept="image/*"
                    className="mt-0 block w-full"
                />
                <p className="text-muted-foreground text-xs">
                    {troubleshoot?.evidence_photo
                        ? 'Kosongkan untuk mempertahankan foto saat ini.'
                        : 'Format gambar, maksimal 4 MB.'}
                </p>
                <InputError message={errors.evidence_photo} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-document`}>Upload Dokumen</Label>
                <Input
                    id={`${idPrefix}-document`}
                    name="document"
                    type="file"
                    className="mt-0 block w-full"
                />
                <p className="text-muted-foreground text-xs">
                    {troubleshoot?.document
                        ? 'Kosongkan untuk mempertahankan dokumen saat ini.'
                        : 'Maksimal 10 MB.'}
                </p>
                <InputError message={errors.document} />
            </div>
        </div>
    );
}

function EditTroubleshootDialog({
    troubleshoot,
    assetOptions,
}: {
    troubleshoot: TroubleshootRow;
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
                <DialogTitle>Update Trouble Ticket</DialogTitle>
                <DialogDescription>
                    Update the trouble ticket for{' '}
                    {troubleshoot.asset?.name ?? 'this asset'}.
                    {troubleshoot.maintenance_id !== null &&
                        ' This ticket was created from a maintenance record.'}{' '}
                    Closing it (Solved) requires the work dates and turns
                    the linked maintenance result back to Normal.
                </DialogDescription>

                <Form
                    {...TroubleshootController.update.form(troubleshoot.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['troubleshoots'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <TroubleshootFields
                                idPrefix={`edit-${troubleshoot.id}`}
                                troubleshoot={troubleshoot}
                                assetOptions={assetOptions}
                                errors={errors as FormErrors}
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

function DeleteTroubleshootDialog({ troubleshoot }: { troubleshoot: TroubleshootRow }) {
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
                <DialogTitle>Delete trouble ticket</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete this trouble ticket? This
                    action cannot be undone.
                </DialogDescription>

                <Form
                    {...TroubleshootController.destroy.form(troubleshoot.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['troubleshoots'] });
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
    { value: 'ticket_number', label: 'No Tiket' },
    { value: 'asset_name', label: 'Nama Perangkat' },
    { value: 'result', label: 'Hasil' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari nama perangkat, nomor tiket, atau hasil...',
    ticket_number: 'Cari nomor tiket (TKT-00001)...',
    asset_name: 'Cari nama perangkat...',
    result: 'Cari hasil (Solved/Unresolved)...',
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
                only: ['troubleshoots', 'filters'],
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

function TroubleshootSearch({
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
                    only: ['troubleshoots', 'filters'],
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
        <div className="flex w-full flex-wrap items-center gap-2 sm:max-w-170">
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
                    id="troubleshoot-search"
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
                only: ['troubleshoots', 'filters'],
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
    troubleshoots,
    search,
    searchColumn,
    date,
    perPage,
    listUrl,
}: {
    troubleshoots: PaginatedTroubleshoots;
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
                    {troubleshoots.total}
                </span>{' '}
                total trouble tickets
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {troubleshoots.links.map((link, index) =>
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

/**
 * Asset picker for the history panel: any asset can be inspected, even
 * one that never had a trouble ticket.
 */
function HistoryPickerDialog({
    assetOptions,
    onPick,
    onOpenChange,
}: {
    assetOptions: AssetOption[];
    onPick: (assetId: number) => void;
    onOpenChange: (open: boolean) => void;
}) {
    const [assetId, setAssetId] = useState('');

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>Lihat Riwayat Gangguan</DialogTitle>
                <DialogDescription>
                    Pilih perangkat untuk melihat riwayat gangguan dan
                    maintenance-nya.
                </DialogDescription>
                <Select value={assetId} onValueChange={setAssetId}>
                    <SelectTrigger className="h-9 w-full">
                        <SelectValue placeholder="Pilih perangkat..." />
                    </SelectTrigger>
                    <SelectContent>
                        {assetOptions.map((asset) => (
                            <SelectItem
                                key={asset.id}
                                value={String(asset.id)}
                            >
                                {asset.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Batal</Button>
                    </DialogClose>
                    <Button
                        disabled={!assetId}
                        onClick={() => onPick(Number(assetId))}
                    >
                        Lihat Riwayat
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Read-only history panel for one asset: every trouble ticket it ever
 * had (with its origin) plus its maintenance records, newest first.
 */
function HistoryDialog({
    history,
    canManage,
    onClose,
    onBack,
}: {
    history: NonNullable<HistoryPayload>;
    canManage: boolean;
    onClose: () => void;
    onBack: () => void;
}) {
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-6xl">
                <DialogTitle>
                    Riwayat Gangguan — {history.asset.name}
                </DialogTitle>
                <DialogDescription>
                    Semua tiket gangguan dan riwayat maintenance untuk
                    perangkat ini.
                </DialogDescription>

                <section className="space-y-2">
                    <h2 className="text-sm font-medium">Riwayat Gangguan</h2>
                    {history.tickets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada tiket gangguan untuk perangkat ini.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                        <th className="w-12 px-3 py-2 text-center font-medium">
                                            No
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium whitespace-nowrap">
                                            No Tiket
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Tanggal Tiket
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Tanggal Mulai Pengerjaan
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Tanggal Selesai Pengerjaan
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Status Tiket
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Tanggal Maintenance
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Foto
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Dokumen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.tickets.map((ticket, index) => (
                                        <tr
                                            key={ticket.id}
                                            className="border-b border-sidebar-border/50 last:border-b-0"
                                        >
                                            <td className="px-3 py-2 text-center text-muted-foreground">
                                                {index + 1}
                                            </td>
                                            <td className="px-3 py-2 text-center font-medium whitespace-nowrap">
                                                {ticket.ticket_number}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {formatDate(
                                                    ticket.troubleshoot_date,
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {ticket.started_date ? (
                                                    formatDate(
                                                        ticket.started_date,
                                                    )
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {ticket.closed_date ? (
                                                    formatDate(
                                                        ticket.closed_date,
                                                    )
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <Badge
                                                    variant="outline"
                                                    className={resultBadgeClassFor(
                                                        ticket.result,
                                                    )}
                                                >
                                                    {ticketResultLabel(
                                                        ticket.result,
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {ticket.maintenance
                                                    ? `Maintenance ${formatDate(
                                                          ticket.maintenance
                                                              .maintenance_date,
                                                      )}`
                                                    : 'Manual'}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <FileLink
                                                    path={ticket.evidence_photo}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <FileLink
                                                    path={ticket.document}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-medium">Riwayat Maintenance</h2>
                    {history.maintenances.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada record maintenance untuk perangkat ini.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                        <th className="w-12 px-3 py-2 text-center font-medium">
                                            No
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Tanggal Mulai Maintenance
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Tanggal Beres Maintenance
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Hasil
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.maintenances.map(
                                        (maintenance, index) => (
                                            <tr
                                                key={maintenance.id}
                                                className="border-b border-sidebar-border/50 last:border-b-0"
                                            >
                                                <td className="px-3 py-2 text-center text-muted-foreground">
                                                    {index + 1}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    {formatDate(
                                                        maintenance.maintenance_date,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-center">
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
                                                <td className="px-3 py-2 text-center">
                                                    <Badge
                                                        variant="outline"
                                                        className={maintenanceStatusBadgeClassFor(
                                                            maintenance.status,
                                                        )}
                                                    >
                                                        {maintenance.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2 text-center">
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
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <DialogFooter className="sm:justify-between">
                    <Button variant="outline" onClick={onBack}>
                        <ArrowLeft className="size-4" />
                        Back
                    </Button>
                    <div className="flex items-center gap-2">
                        {canManage && (
                            <Button
                                variant="default"
                                className="bg-green-600 text-white hover:bg-green-700"
                                asChild
                            >
                                <a
                                    href={TroubleshootController.exportHistory.url(
                                        {
                                            query: {
                                                history_asset:
                                                    history.asset.id,
                                            },
                                        },
                                    )}
                                >
                                    <Upload className="size-4" />
                                    Export CSV
                                </a>
                            </Button>
                        )}
                        <DialogClose asChild>
                            <Button className="bg-red-600 text-white hover:bg-red-700">
                                Tutup
                            </Button>
                        </DialogClose>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const tableHeaders = [
    'No',
    'No Tiket',
    'Nama Perangkat',
    'Tanggal Tiket Dibuat',
    'Mulai Pengerjaan',
    'Selesai Pengerjaan',
    'Status Tiket',
    'Foto Eviden',
    'Dokumen',
    'Aksi',
] as const;

export default function AssetsTroubleshoot({
    troubleshoots,
    assetOptions,
    filters,
    history,
    canManage,
}: {
    troubleshoots: PaginatedTroubleshoots;
    assetOptions: AssetOption[];
    filters: TroubleshootFilters;
    history: HistoryPayload;
    canManage: boolean;
}) {
    const listUrl = troubleshootUrl.url();
    const [historyPickerOpen, setHistoryPickerOpen] = useState(false);

    useEchoPublic('maintenance', '.maintenance.changed', () => {
        router.reload({ only: ['troubleshoots', 'history'] });
    });

    /**
     * The list query params as currently applied, optionally with the
     * history asset, so partial reloads never drop active filters.
     */
    function historyParams(assetId?: number) {
        return {
            search: filters.search || undefined,
            search_column:
                filters.search_column !== 'all'
                    ? filters.search_column
                    : undefined,
            date: filters.date || undefined,
            per_page: filters.per_page,
            ...(assetId ? { history_asset: assetId } : {}),
        };
    }

    function openHistory(assetId: number) {
        router.get(listUrl, historyParams(assetId), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['history'],
        });
    }

    function closeHistory() {
        router.get(listUrl, historyParams(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['history'],
        });
    }

    function pickHistoryAsset(assetId: number) {
        setHistoryPickerOpen(false);
        openHistory(assetId);
    }

    return (
        <>
            <Head title="Troubleshoot" />

            <h1 className="sr-only">Troubleshoot</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Troubleshoot"
                    description="Track trouble tickets for asset issues"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <TroubleshootSearch
                        search={filters.search}
                        searchColumn={filters.search_column}
                        date={filters.date}
                        perPage={filters.per_page}
                        listUrl={listUrl}
                    />
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setHistoryPickerOpen(true)}
                        >
                            <History className="size-4" />
                            Riwayat Gangguan
                        </Button>
                        {canManage && (
                            <>
                                <Button
                                    variant="default"
                                    className="bg-green-600 text-white hover:bg-green-700"
                                    asChild
                                >
                                    <a
                                        href={TroubleshootController.exportMethod.url(
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
                                                        filters.date ||
                                                        undefined,
                                                },
                                            },
                                        )}
                                    >
                                        <Upload className="size-4" />
                                        Export
                                    </a>
                                </Button>
                            </>
                        )}
                    </div>
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
                            {troubleshoots.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada tiket gangguan ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                troubleshoots.data.map((troubleshoot, index) => (
                                    <tr
                                        key={troubleshoot.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {(troubleshoots.current_page - 1) *
                                                troubleshoots.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium whitespace-nowrap">
                                            {troubleshoot.ticket_number}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {troubleshoot.asset?.name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {formatDate(
                                                troubleshoot.troubleshoot_date,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {troubleshoot.started_date ? (
                                                formatDate(
                                                    troubleshoot.started_date,
                                                )
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {troubleshoot.closed_date ? (
                                                formatDate(
                                                    troubleshoot.closed_date,
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
                                                className={resultBadgeClassFor(
                                                    troubleshoot.result,
                                                )}
                                            >
                                                {ticketResultLabel(
                                                    troubleshoot.result,
                                                )}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <FileLink
                                                path={troubleshoot.evidence_photo}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <FileLink
                                                path={troubleshoot.document}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditTroubleshootDialog
                                                        troubleshoot={
                                                            troubleshoot
                                                        }
                                                        assetOptions={
                                                            assetOptions
                                                        }
                                                    />
                                                    <DeleteTroubleshootDialog
                                                        troubleshoot={
                                                            troubleshoot
                                                        }
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
                    troubleshoots={troubleshoots}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    date={filters.date}
                    perPage={filters.per_page}
                    listUrl={listUrl}
                />
            </div>

            {historyPickerOpen && (
                <HistoryPickerDialog
                    assetOptions={assetOptions}
                    onPick={pickHistoryAsset}
                    onOpenChange={setHistoryPickerOpen}
                />
            )}

            {history && (
                <HistoryDialog
                    history={history}
                    canManage={canManage}
                    onClose={closeHistory}
                    onBack={() => {
                        closeHistory();
                        setHistoryPickerOpen(true);
                    }}
                />
            )}
        </>
    );
}

AssetsTroubleshoot.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, troubleshootUrl.url()) ?? [
            { title: 'Troubleshoot', href: troubleshootUrl.url() },
        ],
});
