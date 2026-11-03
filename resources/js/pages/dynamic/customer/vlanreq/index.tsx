import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import VlanTagRequestController from '@/actions/App/Http/Controllers/VlanTagRequestController';
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
import { vlanreq as vlanreqUrl } from '@/routes/customer';

type CustomerOption = {
    id: number;
    customer_name: string;
    sid_rack: string | null;
};

type AssetOption = {
    id: number;
    name: string;
};

type SwitchRow = {
    id: number;
    asset_id: number;
    port_number: string;
    asset?: { id: number; name: string } | null;
};

type VlanTagRequestRow = {
    id: number;
    customer_id: number;
    order_number: string;
    sid: string;
    service_name: string;
    vlan: string;
    mode: string;
    customer?: { id: number; customer_name: string } | null;
    switches: SwitchRow[];
};

type PaginatedVlanTagRequests = {
    data: VlanTagRequestRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type VlanTagFilters = {
    search: string;
    search_column: string;
    per_page: number;
};

type FormErrors = Partial<Record<string, string | undefined>>;

type SwitchDraft = {
    asset_id: string;
    port_number: string;
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

const modeOptions = ['TRUNK', 'ACCESS', 'QINQ', 'LAG'] as const;

const modeBadgeClass: Record<string, string> = {
    TRUNK: 'border-sky-600/50 bg-sky-600/10 text-sky-600 dark:text-sky-400',
    ACCESS:
        'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400',
    QINQ: 'border-violet-600/50 bg-violet-600/10 text-violet-600 dark:text-violet-400',
    LAG: 'border-amber-600/50 bg-amber-600/10 text-amber-600 dark:text-amber-400',
};

function modeBadgeClassFor(mode: string): string {
    return modeBadgeClass[mode] ?? '';
}

const searchColumnOptions = [
    { value: 'all', label: 'Semua kolom' },
    { value: 'order_number', label: 'Nomor Order' },
    { value: 'sid', label: 'SID' },
    { value: 'customer_name', label: 'Nama Pelanggan' },
    { value: 'vlan', label: 'VLAN' },
    { value: 'mode', label: 'Mode' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari nomor order, SID, pelanggan...',
    order_number: 'Cari nomor order...',
    sid: 'Cari SID...',
    customer_name: 'Cari nama pelanggan...',
    vlan: 'Cari VLAN...',
    mode: 'Cari mode (TRUNK/ACCESS/QINQ/LAG)...',
};

/**
 * Shared form body for the create and update dialogs. Picking a customer
 * prefills the order/SID/service fields (still editable); the switch rows
 * become a LAG only allow more than one switch.
 */
function VlanTagFields({
    idPrefix,
    request,
    customers,
    networkAssets,
    errors,
}: {
    idPrefix: string;
    request?: VlanTagRequestRow;
    customers: CustomerOption[];
    networkAssets: AssetOption[];
    errors: FormErrors;
}) {
    const [customerId, setCustomerId] = useState(
        request ? String(request.customer_id) : '',
    );
    const [orderNumber, setOrderNumber] = useState(
        request?.order_number ?? '',
    );
    const [sid, setSid] = useState(request?.sid ?? '');
    const [serviceName, setServiceName] = useState(
        request?.service_name ?? '',
    );
    const [vlan, setVlan] = useState(request?.vlan ?? '');
    const [mode, setMode] = useState(request?.mode ?? 'ACCESS');
    const [switches, setSwitches] = useState<SwitchDraft[]>(
        request?.switches.length
            ? request.switches.map((row) => ({
                  asset_id: String(row.asset_id),
                  port_number: row.port_number,
              }))
            : [{ asset_id: '', port_number: '' }],
    );

    const isLag = mode === 'LAG';

    function handleCustomerChange(value: string) {
        setCustomerId(value);

        const customer = customers.find((row) => String(row.id) === value);

        if (!customer) {
            return;
        }

        setSid(customer.sid_rack ?? '');
    }

    function handleModeChange(value: string) {
        setMode(value);

        if (value !== 'LAG') {
            setSwitches((rows) => rows.slice(0, 1));
        }
    }

    function updateSwitch(index: number, patch: Partial<SwitchDraft>) {
        setSwitches((rows) =>
            rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    function addSwitch() {
        setSwitches((rows) => [...rows, { asset_id: '', port_number: '' }]);
    }

    function removeSwitch(index: number) {
        setSwitches((rows) => rows.filter((_, i) => i !== index));
    }

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-customer`}>
                        Nama Pelanggan
                    </Label>
                    <Select
                        value={customerId}
                        onValueChange={handleCustomerChange}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-customer`}
                            className="w-full"
                        >
                            <SelectValue placeholder="Pilih pelanggan..." />
                        </SelectTrigger>
                        <SelectContent>
                            {customers.map((customer) => (
                                <SelectItem
                                    key={customer.id}
                                    value={String(customer.id)}
                                >
                                    {customer.customer_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="customer_id" value={customerId} />
                    <InputError message={errors.customer_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-order`}>Nomor Order</Label>
                    <Input
                        id={`${idPrefix}-order`}
                        name="order_number"
                        value={orderNumber}
                        onChange={(e) => setOrderNumber(e.target.value)}
                        placeholder="Masukkan No Order / AO"
                    />
                    <InputError message={errors.order_number} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-sid`}>SID</Label>
                    <Input
                        id={`${idPrefix}-sid`}
                        name="sid"
                        value={sid}
                        onChange={(e) => setSid(e.target.value)}
                        disabled
                        placeholder="Terisi otomatis dari pelanggan"
                    />
                    <input type="hidden" name="sid" value={sid} />
                    <InputError message={errors.sid} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-service`}>Nama Layanan</Label>
                    <Input
                        id={`${idPrefix}-service`}
                        name="service_name"
                        value={serviceName}
                        onChange={(e) => setServiceName(e.target.value)}
                        placeholder="Masukkan nama layanan"
                    />
                    <InputError message={errors.service_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-vlan`}>VLAN</Label>
                    <Input
                        id={`${idPrefix}-vlan`}
                        name="vlan"
                        value={vlan}
                        onChange={(e) => setVlan(e.target.value)}
                        placeholder="Contoh: 100"
                    />
                    <InputError message={errors.vlan} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-mode`}>Mode</Label>
                    <Select value={mode} onValueChange={handleModeChange}>
                        <SelectTrigger id={`${idPrefix}-mode`} className="w-full">
                            <SelectValue placeholder="Pilih mode..." />
                        </SelectTrigger>
                        <SelectContent>
                            {modeOptions.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="mode" value={mode} />
                    <InputError message={errors.mode} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label>Switch &amp; Port</Label>
                <div className="space-y-3">
                    {switches.map((row, index) => (
                        <div key={index} className="space-y-2">
                            <div className="grid gap-2 sm:grid-cols-[1fr_180px_auto]">
                                <Select
                                    value={row.asset_id}
                                    onValueChange={(value) =>
                                        updateSwitch(index, { asset_id: value })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih switch (Network)..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {networkAssets.map((asset) => (
                                            <SelectItem
                                                key={asset.id}
                                                value={String(asset.id)}
                                            >
                                                {asset.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    value={row.port_number}
                                    onChange={(e) =>
                                        updateSwitch(index, {
                                            port_number: e.target.value,
                                        })
                                    }
                                    placeholder="Port number"
                                />
                                {isLag && switches.length > 1 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() => removeSwitch(index)}
                                        aria-label="Hapus baris switch"
                                        title="Hapus baris switch"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                ) : (
                                    <div
                                        className="hidden w-9 sm:block"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                            <div className="flex flex-wrap gap-x-4 gap-y-1">
                                <InputError
                                    message={
                                        errors[`switches.${index}.asset_id`]
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`switches.${index}.port_number`]
                                    }
                                />
                            </div>
                            <input
                                type="hidden"
                                name={`switches[${index}][asset_id]`}
                                value={row.asset_id}
                            />
                            <input
                                type="hidden"
                                name={`switches[${index}][port_number]`}
                                value={row.port_number}
                            />
                        </div>
                    ))}
                </div>
                {isLag && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addSwitch}
                        className="w-fit"
                    >
                        <Plus className="size-4" />
                        Tambah Switch
                    </Button>
                )}
                <InputError message={errors.switches} />
            </div>
        </div>
    );
}

function CreateVlanTagDialog({
    customers,
    networkAssets,
}: {
    customers: CustomerOption[];
    networkAssets: AssetOption[];
}) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Buat Baru
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Buat Permintaan Tag VLAN</DialogTitle>
                <DialogDescription>
                    Create a VLAN tag request for a customer. Order number
                    (AO) and service name are entered manually — they are
                    independent from the customer record — while SID prefills
                    from the customer.
                </DialogDescription>

                <Form
                    {...VlanTagRequestController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                        router.reload({ only: ['vlanTagRequests'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <VlanTagFields
                                key={formKey}
                                idPrefix="create"
                                customers={customers}
                                networkAssets={networkAssets}
                                errors={errors as FormErrors}
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

function EditVlanTagDialog({
    request,
    customers,
    networkAssets,
}: {
    request: VlanTagRequestRow;
    customers: CustomerOption[];
    networkAssets: AssetOption[];
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
                <DialogTitle>Update Permintaan Tag VLAN</DialogTitle>
                <DialogDescription>
                    Update the VLAN tag request for{' '}
                    {request.customer?.customer_name ?? 'this customer'}.
                </DialogDescription>

                <Form
                    {...VlanTagRequestController.update.form(request.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['vlanTagRequests'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <VlanTagFields
                                idPrefix={`edit-${request.id}`}
                                request={request}
                                customers={customers}
                                networkAssets={networkAssets}
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

function DeleteVlanTagDialog({ request }: { request: VlanTagRequestRow }) {
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
                <DialogTitle>Delete permintaan tag VLAN</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete this VLAN tag request? The
                    switch rows are removed with it. This action cannot be
                    undone.
                </DialogDescription>

                <Form
                    {...VlanTagRequestController.destroy.form(request.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['vlanTagRequests'] });
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

function VlanTagSearch({
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
                    only: ['vlanTagRequests', 'filters'],
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
        <div className="flex w-full flex-wrap items-center gap-2 sm:max-w-150">
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
                    id="vlanreq-search"
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
                only: ['vlanTagRequests', 'filters'],
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
    vlanTagRequests,
    search,
    searchColumn,
    perPage,
    listUrl,
}: {
    vlanTagRequests: PaginatedVlanTagRequests;
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
                    {vlanTagRequests.total}
                </span>{' '}
                total VLAN tag requests
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {vlanTagRequests.links.map((link, index) =>
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
    'Nomor Order',
    'SID',
    'Nama Pelanggan',
    'Nama Layanan',
    'VLAN',
    'Mode',
    'Switch',
    'Port Number',
    'Aksi',
] as const;

export default function CustomerVlanreq({
    vlanTagRequests,
    customers,
    networkAssets,
    filters,
    canManage,
}: {
    vlanTagRequests: PaginatedVlanTagRequests;
    customers: CustomerOption[];
    networkAssets: AssetOption[];
    filters: VlanTagFilters;
    canManage: boolean;
}) {
    const listUrl = vlanreqUrl.url();

    useEchoPublic('customers', '.customers.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="VLAN Tag Request" />
            <h1 className="sr-only">VLAN Tag Request</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="VLAN Tag Request"
                    description="Track customer VLAN tag requests"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <VlanTagSearch
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
                                    href={VlanTagRequestController.exportMethod.url(
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
                                            },
                                        },
                                    )}
                                >
                                    <Upload className="size-4" />
                                    Export
                                </a>
                            </Button>
                            <CreateVlanTagDialog
                                customers={customers}
                                networkAssets={networkAssets}
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
                            {vlanTagRequests.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada permintaan tag VLAN ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                vlanTagRequests.data.map((request, index) => (
                                    <tr
                                        key={request.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {(vlanTagRequests.current_page - 1) *
                                                vlanTagRequests.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {request.order_number}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {request.sid}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {request.customer?.customer_name ??
                                                '-'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {request.service_name}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {request.vlan}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={modeBadgeClassFor(
                                                    request.mode,
                                                )}
                                            >
                                                {request.mode}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex flex-col gap-0.5">
                                                {request.switches.length ? (
                                                    request.switches.map(
                                                        (row) => (
                                                            <span key={row.id}>
                                                                {row.asset
                                                                    ?.name ??
                                                                    '-'}
                                                            </span>
                                                        ),
                                                    )
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex flex-col gap-0.5">
                                                {request.switches.length ? (
                                                    request.switches.map(
                                                        (row) => (
                                                            <span key={row.id}>
                                                                {row.port_number}
                                                            </span>
                                                        ),
                                                    )
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditVlanTagDialog
                                                        request={request}
                                                        customers={customers}
                                                        networkAssets={
                                                            networkAssets
                                                        }
                                                    />
                                                    <DeleteVlanTagDialog
                                                        request={request}
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
                    vlanTagRequests={vlanTagRequests}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    perPage={filters.per_page}
                    listUrl={listUrl}
                />
            </div>
        </>
    );
}

CustomerVlanreq.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/customer/vlanreq') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
