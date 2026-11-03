import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import RackController from '@/actions/App/Http/Controllers/RackController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { overview as rackOverview } from '@/routes/rack';

type Rack = {
    id: number;
    rack_code: string;
    floor: string;
    room: string;
    status: string;
    brand: string;
    type: string;
    capacity: string;
    dimension: string;
    pdu_outlet_1: string;
    pdu_outlet_2: string;
    manufacture_year: string;
    operation_year: string;
    notes: string | null;
    customers?: { id: number; customer_name: string }[];
};

type PaginatedRacks = {
    data: Rack[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type RackFilters = {
    search: string;
    search_column: string;
    per_page: number;
};

type RackErrors = Partial<Record<keyof Rack, string>>;

/**
 * Rack type option served by the server (name + default capacity), sourced
 * from the rack_types table.
 */
type RackTypeOption = {
    name: string;
    capacity: string;
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

const pduOptions = ['Ada', 'Tidak Ada'] as const;

/**
 * Badge colours by rack status: green marks available stock, rose marks a
 * rack already assigned to a customer, and slate is the fallback for any
 * other status — add a branch here when a new status is introduced.
 */
const statusBadgeClassFor = (status: string): string => {
    if (status === 'Occupied') {
        return 'border-rose-600/50 bg-rose-600/10 text-rose-600 dark:text-rose-400';
    }

    if (status === 'Ready For Sale') {
        return 'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400';
    }

    return 'border-slate-600/50 bg-slate-600/10 text-slate-600 dark:text-slate-400';
};

function RackFields({
    idPrefix,
    rack,
    errors,
    rackTypes,
}: {
    idPrefix: string;
    rack?: Rack;
    errors: RackErrors;
    rackTypes: RackTypeOption[];
}) {
    const [type, setType] = useState(rack?.type ?? '');
    const [capacity, setCapacity] = useState(rack?.capacity ?? '');
    const [pdu1, setPdu1] = useState(rack?.pdu_outlet_1 ?? 'Tidak Ada');
    const [pdu2, setPdu2] = useState(rack?.pdu_outlet_2 ?? 'Tidak Ada');

    const selectType = (value: string) => {
        setType(value);
        setCapacity(
            rackTypes.find((option) => option.name === value)?.capacity ?? '',
        );
    };

    const textFields = [
        { name: 'floor', label: 'Lantai' },
        { name: 'room', label: 'Ruang' },
        { name: 'brand', label: 'Merek' },
        { name: 'dimension', label: 'Dimensi' },
        { name: 'manufacture_year', label: 'Tahun Manufaktur' },
        { name: 'operation_year', label: 'Tahun Operasi' },
    ] as const;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-rack_code`}>Rack ID</Label>
                <Input
                    id={`${idPrefix}-rack_code`}
                    name="rack_code"
                    className="mt-0 block w-full"
                    defaultValue={rack?.rack_code ?? ''}
                    required
                    autoComplete="off"
                />
                <InputError message={errors.rack_code} />
            </div>

            {textFields.map((field) => (
                <div key={field.name} className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-${field.name}`}>
                        {field.label}
                    </Label>
                    <Input
                        id={`${idPrefix}-${field.name}`}
                        name={field.name}
                        className="mt-0 block w-full"
                        defaultValue={rack?.[field.name] ?? ''}
                        required
                        autoComplete="off"
                    />
                    <InputError message={errors[field.name]} />
                </div>
            ))}

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-type`}>Tipe</Label>
                <Select value={type} onValueChange={selectType}>
                    <SelectTrigger id={`${idPrefix}-type`} className="w-full">
                        <SelectValue placeholder="Pilih tipe rak" />
                    </SelectTrigger>
                    <SelectContent>
                        {rackTypes.map((option) => (
                            <SelectItem key={option.name} value={option.name}>
                                {option.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="type" value={type} />
                <InputError message={errors.type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-capacity`}>Kapasitas</Label>
                <Input
                    id={`${idPrefix}-capacity`}
                    name="capacity"
                    className="text-muted-foreground mt-0 block w-full"
                    value={capacity}
                    placeholder="Otomatis mengikuti tipe"
                    readOnly
                    autoComplete="off"
                />
                <InputError message={errors.capacity} />
            </div>

            {(
                [
                    { name: 'pdu_outlet_1', label: 'PDU Outlet 1', value: pdu1, setValue: setPdu1 },
                    { name: 'pdu_outlet_2', label: 'PDU Outlet 2', value: pdu2, setValue: setPdu2 },
                ] as const
            ).map((field) => (
                <div key={field.name} className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-${field.name}`}>
                        {field.label}
                    </Label>
                    <Select
                        value={field.value}
                        onValueChange={field.setValue}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-${field.name}`}
                            className="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {pduOptions.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input type="hidden" name={field.name} value={field.value} />
                    <InputError message={errors[field.name]} />
                </div>
            ))}

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${idPrefix}-notes`}>Catatan</Label>
                <textarea
                    id={`${idPrefix}-notes`}
                    name="notes"
                    className="border-input bg-transparent text-muted-foreground mt-1 flex h-24 w-full min-w-0 resize-none rounded-md border px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    defaultValue={rack?.notes ?? ''}
                    autoComplete="off"
                />
                <InputError message={errors.notes} />
            </div>
        </div>
    );
}

function CreateRackDialog({ rackTypes }: { rackTypes: RackTypeOption[] }) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Tambah Rak
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Create rack</DialogTitle>
                <DialogDescription>
                    Add a new rack to the inventory. Rack ID must be unique.
                </DialogDescription>

                <Form
                    {...RackController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                        router.reload({ only: ['racks'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <RackFields
                                key={formKey}
                                idPrefix="create"
                                errors={errors as RackErrors}
                                rackTypes={rackTypes}
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Create
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditRackDialog({
    rack,
    rackTypes,
}: {
    rack: Rack;
    rackTypes: RackTypeOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Edit rack</DialogTitle>
                <DialogDescription>
                    Update the details for {rack.rack_code}.
                </DialogDescription>

                <Form
                    {...RackController.update.form(rack.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['racks'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <RackFields
                                idPrefix={`edit-${rack.id}`}
                                rack={rack}
                                errors={errors as RackErrors}
                                rackTypes={rackTypes}
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

function DeleteRackDialog({ rack }: { rack: Rack }) {
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
                <DialogTitle>Delete rack</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {rack.rack_code}? This
                    action cannot be undone.
                </DialogDescription>

                <Form
                    {...RackController.destroy.form(rack.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['racks'] });
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
    { value: 'rack_code', label: 'Rack ID' },
    { value: 'floor', label: 'Lantai' },
    { value: 'room', label: 'Ruang' },
    { value: 'status', label: 'Status' },
    { value: 'customer_name', label: 'Nama Customer' },
    { value: 'brand', label: 'Merek' },
    { value: 'type', label: 'Tipe' },
    { value: 'capacity', label: 'Kapasitas' },
    { value: 'dimension', label: 'Dimensi' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari rack id, merek, atau ruang...',
    rack_code: 'Cari rack id...',
    floor: 'Cari lantai...',
    room: 'Cari ruang...',
    status: 'Cari status...',
    customer_name: 'Cari nama customer...',
    brand: 'Cari merek...',
    type: 'Cari tipe...',
    capacity: 'Cari kapasitas...',
    dimension: 'Cari dimensi...',
};

function RackSearch({
    search,
    searchColumn,
    perPage,
    listUrl,
}: {
    search: string;
    searchColumn: string;
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
                    per_page: perPage,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['racks', 'filters'],
                },
            );
        },
        [perPage, listUrl],
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
                    id="rack-search"
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
    listUrl,
}: {
    search: string;
    searchColumn: string;
    perPage: number;
    listUrl: string;
}) {
    function changePerPage(value: string) {
        router.get(
            listUrl,
            {
                search: search || undefined,
                search_column: searchColumn !== 'all' ? searchColumn : undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['racks', 'filters'],
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
    racks,
    search,
    searchColumn,
    perPage,
    listUrl,
}: {
    racks: PaginatedRacks;
    search: string;
    searchColumn: string;
    perPage: number;
    listUrl: string;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector
                search={search}
                searchColumn={searchColumn}
                perPage={perPage}
                listUrl={listUrl}
            />

            <span className="text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {racks.total}
                </span>{' '}
                total racks
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {racks.links.map((link, index) =>
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
    'Rack ID',
    'Lantai',
    'Ruang',
    'Status',
    'Nama Customer',
    'Merek',
    'Tipe',
    'Kapasitas',
    'Dimensi',
    'PDU Outlet 1',
    'PDU Outlet 2',
    'Tahun Manufaktur',
    'Tahun Operasi',
    'Catatan',
    'Aksi',
] as const;

export default function RackIndex({
    racks,
    filters,
    canManage,
    rackTypes,
}: {
    racks: PaginatedRacks;
    filters: RackFilters;
    canManage: boolean;
    rackTypes: RackTypeOption[];
}) {
    const listUrl = rackOverview.url();

    useEchoPublic('racks', '.racks.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Rack Management" />

            <h1 className="sr-only">Rack Management</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Rack Management"
                    description="Manage the data center rack inventory"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <RackSearch
                        search={filters.search}
                        searchColumn={filters.search_column}
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
                                    href={RackController.exportMethod.url({
                                        query: {
                                            search: filters.search || undefined,
                                            search_column:
                                                filters.search_column !== 'all'
                                                    ? filters.search_column
                                                    : undefined,
                                        },
                                    })}
                                >
                                    <Upload className="size-4" />
                                    Export
                                </a>
                            </Button>
                            <CreateRackDialog rackTypes={rackTypes} />
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
                            {racks.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada rak ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                racks.data.map((rack, index) => (
                                    <tr
                                        key={rack.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {(racks.current_page - 1) *
                                                racks.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {rack.rack_code}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.floor}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.room}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClassFor(
                                                    rack.status,
                                                )}
                                            >
                                                {rack.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.customers?.[0]
                                                ?.customer_name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.brand}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.type}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.capacity}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.dimension}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={
                                                    rack.pdu_outlet_1 === 'Ada'
                                                        ? 'border-green-600/50 text-green-600 dark:text-green-400'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {rack.pdu_outlet_1}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={
                                                    rack.pdu_outlet_2 === 'Ada'
                                                        ? 'border-green-600/50 text-green-600 dark:text-green-400'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {rack.pdu_outlet_2}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.manufacture_year}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.operation_year}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {rack.notes ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditRackDialog
                                                        rack={rack}
                                                        rackTypes={rackTypes}
                                                    />
                                                    <DeleteRackDialog
                                                        rack={rack}
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
                    racks={racks}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    perPage={filters.per_page}
                    listUrl={listUrl}
                />
            </div>
        </>
    );
}

RackIndex.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, rackOverview.url()) ?? [
            { title: 'Rack Management', href: rackOverview() },
        ],
});
