import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
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
import { overview as customerOverview } from '@/routes/customer';

type Customer = {
    id: number;
    rack_id: number;
    city: string;
    location: string;
    customer_name: string;
    service_type: string | null;
    sales_channel: string | null;
    business_status: string | null;
    service_status: string | null;
    order_number: string | null;
    sid_rack: string | null;
    activation_date: string | null;
    power_source: string | null;
    connectivity: string | null;
    ip_address: string | null;
    switch_type: string | null;
    port: string | null;
    vlan: string | null;
    service_vlan: string | null;
    account_manager: string | null;
    notes: string | null;
    rack?: {
        id: number;
        rack_code: string;
        type: string;
        capacity: string;
        floor: string;
        room: string;
        pdu_outlet_1: string;
        pdu_outlet_2: string;
    } | null;
    panel_asset?: { id: number; name: string } | null;
    panel_mcb?: { id: number; asset_id: number; mcb_number: string } | null;
    panel_asset2?: { id: number; name: string } | null;
    panel_mcb2?: { id: number; asset_id: number; mcb_number: string } | null;
};

type PaginatedCustomers = {
    data: Customer[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type CustomerFilters = {
    search: string;
    search_column: string;
    per_page: number;
};

type CustomerErrors = Partial<Record<string, string>>;

/**
 * A rack offered in the Nomor Rack dropdown: racks that no customer uses yet,
 * plus (during edit) the rack the customer being edited already holds.
 */
type RackOption = {
    id: number;
    rack_code: string;
    type: string;
    capacity: string;
    pdu_outlet_1: string;
    pdu_outlet_2: string;
};

/**
 * True when the rack's available PDU outlets cannot cover the customer's
 * power source (Single needs 1 outlet, Dual Source needs 2). No power
 * source selected yet or no rack picked → no warning. Advisory only:
 * saving is never blocked.
 */
function outletShortage(
    powerSource: string | null | undefined,
    rack:
        | { pdu_outlet_1: string; pdu_outlet_2: string }
        | null
        | undefined,
): boolean {
    if (!powerSource || !rack) {
        return false;
    }

    const outlets = [rack.pdu_outlet_1, rack.pdu_outlet_2].filter(
        (value) => value === 'Ada',
    ).length;

    return outlets < (powerSource === 'Dual Source' ? 2 : 1);
}

/**
 * Static choice lists served by the server (Customer model constants).
 */
type FilterOptions = {
    cities: string[];
    locations: string[];
    businessStatuses: string[];
    serviceStatuses: string[];
    powerSources: string[];
};

/**
 * A panel asset offered in the Panel PDU dropdown (Panel Power category
 * tree), with its MCB slots. taken_by is the id of the customer already
 * using the slot (null = free); slots owned by the customer being edited
 * stay selectable.
 */
type PanelOption = {
    id: number;
    name: string;
    floor: string;
    room: string;
    mcbs: { id: number; mcb_number: string; taken_by: number | null }[];
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

const serviceStatusBadgeClass: Record<string, string> = {
    'Masih Berjalan':
        'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400',
    Suspend: 'border-amber-600/50 bg-amber-600/10 text-amber-600 dark:text-amber-400',
    'Berhenti Berlangganan':
        'border-red-600/50 bg-red-600/10 text-red-600 dark:text-red-400',
    Booking: 'border-sky-600/50 bg-sky-600/10 text-sky-600 dark:text-sky-400',
};

function TextField({
    idPrefix,
    name,
    label,
    defaultValue,
    errors,
    type = 'text',
    required = false,
}: {
    idPrefix: string;
    name: string;
    label: string;
    defaultValue: string | null;
    errors: CustomerErrors;
    type?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}-${name}`}>{label}</Label>
            <Input
                id={`${idPrefix}-${name}`}
                name={name}
                type={type}
                className="mt-0 block w-full"
                defaultValue={defaultValue ?? ''}
                required={required}
                autoComplete="off"
            />
            <InputError message={errors[name]} />
        </div>
    );
}

function SelectField({
    idPrefix,
    name,
    label,
    value,
    onValueChange,
    options,
    items,
    errors,
    placeholder = 'Pilih...',
    disabled = false,
}: {
    idPrefix: string;
    name: string;
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    options?: string[];
    items?: { value: string; label: string }[];
    errors: CustomerErrors;
    placeholder?: string;
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}-${name}`}>{label}</Label>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger
                    id={`${idPrefix}-${name}`}
                    className="w-full"
                    disabled={disabled}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {items
                        ? items.map((item) => (
                              <SelectItem key={item.value} value={item.value}>
                                  {item.label}
                              </SelectItem>
                          ))
                        : (options ?? []).map((option) => (
                              <SelectItem key={option} value={option}>
                                  {option}
                              </SelectItem>
                          ))}
                </SelectContent>
            </Select>
            <input type="hidden" name={name} value={value} />
            <InputError message={errors[name]} />
        </div>
    );
}

function CustomerFields({
    idPrefix,
    customer,
    errors,
    rackOptions,
    filterOptions,
    switchOptions,
    panelOptions,
}: {
    idPrefix: string;
    customer?: Customer;
    errors: CustomerErrors;
    rackOptions: RackOption[];
    filterOptions: FilterOptions;
    switchOptions: string[];
    panelOptions: PanelOption[];
}) {
    const [rackId, setRackId] = useState(customer ? String(customer.rack_id) : '');
    const [city, setCity] = useState(customer?.city ?? '');
    const [location, setLocation] = useState(customer?.location ?? '');
    const [businessStatus, setBusinessStatus] = useState(
        customer?.business_status ?? '',
    );
    const [serviceStatus, setServiceStatus] = useState(
        customer?.service_status ?? '',
    );
    const [powerSource, setPowerSource] = useState(customer?.power_source ?? '');
    const [switchType, setSwitchType] = useState(customer?.switch_type ?? '');
    const [panelAssetId, setPanelAssetId] = useState(
        customer?.panel_asset ? String(customer.panel_asset.id) : '',
    );
    const [panelMcbId, setPanelMcbId] = useState(
        customer?.panel_mcb ? String(customer.panel_mcb.id) : '',
    );
    const [panelAssetId2, setPanelAssetId2] = useState(
        customer?.panel_asset2 ? String(customer.panel_asset2.id) : '',
    );
    const [panelMcbId2, setPanelMcbId2] = useState(
        customer?.panel_mcb2 ? String(customer.panel_mcb2.id) : '',
    );

    // Tipe Rak is never stored: it always mirrors the selected rack's type.
    const rackType =
        rackOptions.find((option) => String(option.id) === rackId)?.type ?? '';

    const isDual = powerSource === 'Dual Source';

    const panelItems = panelOptions.map((panel) => ({
        value: String(panel.id),
        label: `${panel.name} · ${panel.room}`,
    }));

    // MCB slots of the selected panel, minus the ones other customers
    // already use. The editing customer's own slot stays selectable.
    const mcbItems = (assetId: string) =>
        (
            panelOptions.find((panel) => String(panel.id) === assetId)
                ?.mcbs ?? []
        )
            .filter(
                (mcb) => mcb.taken_by === null || mcb.taken_by === customer?.id,
            )
            .map((mcb) => ({
                value: String(mcb.id),
                label: mcb.mcb_number,
            }));

    const mcbItemsA = mcbItems(panelAssetId);
    const mcbItemsB = mcbItems(panelAssetId2);

    const textFields = [
        { name: 'service_type', label: 'Jenis Layanan' },
        { name: 'sales_channel', label: 'Sales Channel' },
        { name: 'order_number', label: 'No Order' },
        { name: 'sid_rack', label: 'SID' },
        { name: 'connectivity', label: 'Connectivity' },
        { name: 'ip_address', label: 'IP' },
        { name: 'service_vlan', label: 'Layanan VLAN' },
        { name: 'vlan', label: 'VLAN' },
        { name: 'port', label: 'Port Number' },
        { name: 'account_manager', label: 'Nama AM / Sales' },
    ] as const;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-rack_id`}>Nomor Rack</Label>
                <Select value={rackId} onValueChange={setRackId}>
                    <SelectTrigger id={`${idPrefix}-rack_id`} className="w-full">
                        <SelectValue placeholder="Pilih nomor rack" />
                    </SelectTrigger>
                    <SelectContent>
                        {rackOptions.map((option) => (
                            <SelectItem key={option.id} value={String(option.id)}>
                                {option.rack_code}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="rack_id" value={rackId} />
                <InputError message={errors.rack_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-rack_type`}>Tipe Rak</Label>
                <Input
                    id={`${idPrefix}-rack_type`}
                    className="text-muted-foreground mt-0 block w-full"
                    value={rackType}
                    placeholder="Otomatis mengikuti nomor rack"
                    readOnly
                    autoComplete="off"
                />
            </div>

            <SelectField
                idPrefix={idPrefix}
                name="city"
                label="Kota"
                value={city}
                onValueChange={setCity}
                options={filterOptions.cities}
                errors={errors}
                placeholder="Pilih kota"
            />

            <SelectField
                idPrefix={idPrefix}
                name="location"
                label="Lokasi"
                value={location}
                onValueChange={setLocation}
                options={filterOptions.locations}
                errors={errors}
                placeholder="Pilih lokasi"
            />

            <TextField
                idPrefix={idPrefix}
                name="customer_name"
                label="Nama Customer"
                defaultValue={customer?.customer_name ?? ''}
                errors={errors}
                required
            />

            {textFields.map((field) => (
                <TextField
                    key={field.name}
                    idPrefix={idPrefix}
                    name={field.name}
                    label={field.label}
                    defaultValue={customer?.[field.name] ?? ''}
                    errors={errors}
                />
            ))}

            <SelectField
                idPrefix={idPrefix}
                name="business_status"
                label="Status Bisnis"
                value={businessStatus}
                onValueChange={setBusinessStatus}
                options={filterOptions.businessStatuses}
                errors={errors}
                placeholder="Pilih status bisnis"
            />

            <SelectField
                idPrefix={idPrefix}
                name="service_status"
                label="Status Layanan"
                value={serviceStatus}
                onValueChange={setServiceStatus}
                options={filterOptions.serviceStatuses}
                errors={errors}
                placeholder="Pilih status layanan"
            />

            <TextField
                idPrefix={idPrefix}
                name="activation_date"
                label="Tanggal Aktivasi"
                defaultValue={customer?.activation_date ?? ''}
                errors={errors}
                type="date"
            />

            <SelectField
                idPrefix={idPrefix}
                name="power_source"
                label="Power Source"
                value={powerSource}
                onValueChange={(value) => {
                    setPowerSource(value);
                    // Changing the power source invalidates any panel/MCB
                    // pair chosen under the previous value.
                    setPanelAssetId('');
                    setPanelMcbId('');
                    setPanelAssetId2('');
                    setPanelMcbId2('');
                }}
                options={filterOptions.powerSources}
                errors={errors}
                placeholder="Pilih power source"
            />

            <div className="grid gap-1 sm:col-span-2">
                {(() => {
                    const selectedRack = rackOptions.find(
                        (option) => String(option.id) === rackId,
                    );
                    const outlets = selectedRack
                        ? [
                              selectedRack.pdu_outlet_1,
                              selectedRack.pdu_outlet_2,
                          ].filter((value) => value === 'Ada').length
                        : 0;
                    const shortage = outletShortage(powerSource, selectedRack);

                    return (
                        <>
                            <p className="text-muted-foreground text-xs">
                                {selectedRack
                                    ? outlets === 2
                                        ? 'PDU Outlet rak: 1 & 2'
                                        : outlets === 1
                                          ? 'PDU Outlet rak: 1 saja'
                                          : 'PDU Outlet rak: tidak ada'
                                    : 'PDU Outlet rak: pilih nomor rack dulu'}
                            </p>
                            {shortage && selectedRack && (
                                <p className="text-amber-600 text-xs dark:text-amber-400">
                                    Rack ini hanya punya {outlets} PDU Outlet —
                                    jalur {powerSource} tidak lengkap di rak
                                    ini.
                                </p>
                            )}
                        </>
                    );
                })()}
            </div>

            <SelectField
                idPrefix={idPrefix}
                name="panel_asset_id"
                label="Panel PDU 1"
                value={panelAssetId}
                onValueChange={(value) => {
                    setPanelAssetId(value);
                    setPanelMcbId('');
                }}
                items={panelItems}
                errors={errors}
                placeholder={
                    !powerSource
                        ? 'Pilih power source terlebih dahulu'
                        : panelItems.length === 0
                          ? 'Belum ada data panel power'
                          : 'Pilih panel PDU 1'
                }
                disabled={!powerSource || panelItems.length === 0}
            />

            <SelectField
                idPrefix={idPrefix}
                name="panel_mcb_id"
                label="Nomor MCB 1"
                value={panelMcbId}
                onValueChange={setPanelMcbId}
                items={mcbItemsA}
                errors={errors}
                placeholder={
                    !panelAssetId
                        ? 'Pilih panel PDU terlebih dahulu'
                        : mcbItemsA.length === 0
                          ? 'Belum ada MCB tersedia'
                          : 'Pilih nomor MCB'
                }
                disabled={!panelAssetId || mcbItemsA.length === 0}
            />

            {isDual && (
                <>
                    <SelectField
                        idPrefix={idPrefix}
                        name="panel_asset_id_2"
                        label="Panel PDU 2"
                        value={panelAssetId2}
                        onValueChange={(value) => {
                            setPanelAssetId2(value);
                            setPanelMcbId2('');
                        }}
                        items={panelItems}
                        errors={errors}
                        placeholder={
                            panelItems.length === 0
                                ? 'Belum ada data panel power'
                                : 'Pilih panel PDU 2'
                        }
                        disabled={panelItems.length === 0}
                    />

                    <SelectField
                        idPrefix={idPrefix}
                        name="panel_mcb_id_2"
                        label="Nomor MCB 2"
                        value={panelMcbId2}
                        onValueChange={setPanelMcbId2}
                        items={mcbItemsB}
                        errors={errors}
                        placeholder={
                            !panelAssetId2
                                ? 'Pilih panel PDU terlebih dahulu'
                                : mcbItemsB.length === 0
                                  ? 'Belum ada MCB tersedia'
                                  : 'Pilih nomor MCB'
                        }
                        disabled={!panelAssetId2 || mcbItemsB.length === 0}
                    />
                </>
            )}

            <SelectField
                idPrefix={idPrefix}
                name="switch_type"
                label="Switch"
                value={switchType}
                onValueChange={setSwitchType}
                options={switchOptions}
                errors={errors}
                placeholder={
                    switchOptions.length === 0
                        ? 'Belum ada data aset switch'
                        : 'Pilih perangkat switch'
                }
                disabled={switchOptions.length === 0}
            />

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${idPrefix}-notes`}>Catatan</Label>
                <textarea
                    id={`${idPrefix}-notes`}
                    name="notes"
                    className="border-input bg-transparent text-muted-foreground mt-1 flex h-24 w-full min-w-0 resize-none rounded-md border px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    defaultValue={customer?.notes ?? ''}
                    autoComplete="off"
                />
                <InputError message={errors.notes} />
            </div>
        </div>
    );
}

function CreateCustomerDialog({
    rackOptions,
    filterOptions,
    switchOptions,
    panelOptions,
}: {
    rackOptions: RackOption[];
    filterOptions: FilterOptions;
    switchOptions: string[];
    panelOptions: PanelOption[];
}) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Tambah Pelanggan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogTitle>Create customer</DialogTitle>
                <DialogDescription>
                    Assign a rack to a customer. Each rack can only be assigned
                    to one customer.
                </DialogDescription>

                <Form
                    {...CustomerController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                        router.reload({ only: ['customers'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <CustomerFields
                                key={formKey}
                                idPrefix="create"
                                errors={errors as CustomerErrors}
                                rackOptions={rackOptions}
                                filterOptions={filterOptions}
                                switchOptions={switchOptions}
                                panelOptions={panelOptions}
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

function EditCustomerDialog({
    customer,
    availableRacks,
    filterOptions,
    switchOptions,
    panelOptions,
}: {
    customer: Customer;
    availableRacks: RackOption[];
    filterOptions: FilterOptions;
    switchOptions: string[];
    panelOptions: PanelOption[];
}) {
    const [open, setOpen] = useState(false);

    // The customer's own rack is "taken" by itself, so append it when the
    // server no longer lists it among the free racks.
    const rackOptions = useMemo(() => {
        const options = [...availableRacks];

        if (!options.some((option) => option.id === customer.rack_id)) {
            options.push({
                id: customer.rack_id,
                rack_code: customer.rack?.rack_code ?? '',
                type: customer.rack?.type ?? '',
                capacity: customer.rack?.capacity ?? '',
                pdu_outlet_1: customer.rack?.pdu_outlet_1 ?? '-',
                pdu_outlet_2: customer.rack?.pdu_outlet_2 ?? '-',
            });
        }

        return options;
    }, [availableRacks, customer]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogTitle>Edit customer</DialogTitle>
                <DialogDescription>
                    Update the details for {customer.customer_name}.
                </DialogDescription>

                <Form
                    {...CustomerController.update.form(customer.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['customers'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <CustomerFields
                                idPrefix={`edit-${customer.id}`}
                                customer={customer}
                                errors={errors as CustomerErrors}
                                rackOptions={rackOptions}
                                filterOptions={filterOptions}
                                switchOptions={switchOptions}
                                panelOptions={panelOptions}
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

function DeleteCustomerDialog({ customer }: { customer: Customer }) {
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
                <DialogTitle>Delete customer</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {customer.customer_name}?
                    This action cannot be undone.
                </DialogDescription>

                <Form
                    {...CustomerController.destroy.form(customer.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['customers'] });
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
    { value: 'customer_name', label: 'Nama Customer' },
    { value: 'rack_code', label: 'Nomor Rack' },
    { value: 'city', label: 'Kota' },
    { value: 'location', label: 'Lokasi' },
    { value: 'service_status', label: 'Status Layanan' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari nama customer, nomor rack, atau kota...',
    customer_name: 'Cari nama customer...',
    rack_code: 'Cari nomor rack...',
    city: 'Cari kota...',
    location: 'Cari lokasi...',
    service_status: 'Cari status layanan...',
};

function CustomerSearch({
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
                    only: ['customers', 'filters'],
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
                    id="customer-search"
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
                only: ['customers', 'filters'],
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
    customers,
    search,
    searchColumn,
    perPage,
    listUrl,
}: {
    customers: PaginatedCustomers;
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
                    {customers.total}
                </span>{' '}
                total customers
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {customers.links.map((link, index) =>
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
    'Nomor Rack',
    'Kota',
    'Lokasi',
    'Nama Customer',
    'Tipe Rak',
    'Jenis Layanan',
    'Sales Channel',
    'Status Bisnis',
    'Status Layanan',
    'No Order',
    'SID',
    'Tanggal Aktivasi',
    'Power Source',
    'Panel PDU',
    'Nomor MCB',
    'Connectivity',
    'IP',
    'Switch',
    'Layanan VLAN',
    'VLAN',
    'Port Number',
    'Nama AM / Sales',
    'Catatan',
    'Aksi',
] as const;

function Cell({ children }: { children: React.ReactNode }) {
    return (
        <td className="px-4 py-3 text-center whitespace-nowrap">
            {children}
        </td>
    );
}

export default function CustomerIndex({
    customers,
    filters,
    canManage,
    availableRacks,
    switchOptions,
    panelOptions,
    filterOptions,
}: {
    customers: PaginatedCustomers;
    filters: CustomerFilters;
    canManage: boolean;
    availableRacks: RackOption[];
    switchOptions: string[];
    panelOptions: PanelOption[];
    filterOptions: FilterOptions;
}) {
    const listUrl = customerOverview.url();

    useEchoPublic('customers', '.customers.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Customer List" />

            <h1 className="sr-only">Customer List</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Customer List"
                    description="Manage customer rack assignments and services"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <CustomerSearch
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
                                    href={CustomerController.exportMethod.url(
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
                            <CreateCustomerDialog
                                rackOptions={availableRacks}
                                filterOptions={filterOptions}
                                switchOptions={switchOptions}
                                panelOptions={panelOptions}
                            />
                        </div>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full min-w-[2400px] text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {tableHeaders.map((header) => (
                                    <th
                                        key={header}
                                        className={`px-4 py-3 text-center font-medium whitespace-nowrap ${
                                            header === 'No' ? 'w-14' : ''
                                        }`}
                                    >
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada pelanggan ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                customers.data.map((customer, index) => (
                                    <tr
                                        key={customer.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center whitespace-nowrap text-muted-foreground">
                                            {(customers.current_page - 1) *
                                                customers.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium whitespace-nowrap">
                                            {customer.rack?.rack_code ?? '—'}
                                        </td>
                                        <Cell>{customer.city}</Cell>
                                        <Cell>{customer.location}</Cell>
                                        <Cell>{customer.customer_name}</Cell>
                                        <Cell>{customer.rack?.type ?? '—'}</Cell>
                                        <Cell>
                                            {customer.service_type ?? '—'}
                                        </Cell>
                                        <Cell>
                                            {customer.sales_channel ?? '—'}
                                        </Cell>
                                        <Cell>
                                            {customer.business_status ?? '—'}
                                        </Cell>
                                        <Cell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    customer.service_status
                                                        ? (serviceStatusBadgeClass[
                                                              customer
                                                                  .service_status
                                                          ] ??
                                                          'text-muted-foreground')
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {customer.service_status ?? '—'}
                                            </Badge>
                                        </Cell>
                                        <Cell>
                                            {customer.order_number ?? '—'}
                                        </Cell>
                                        <Cell>{customer.sid_rack ?? '—'}</Cell>
                                        <Cell>
                                            {customer.activation_date ?? '—'}
                                        </Cell>
                                        <Cell>
                                            <div className="grid gap-1 justify-items-center">
                                                <span>
                                                    {customer.power_source ??
                                                        '—'}
                                                </span>
                                                {outletShortage(
                                                    customer.power_source,
                                                    customer.rack,
                                                ) && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-600/50 bg-amber-600/10 text-amber-600 dark:text-amber-400"
                                                    >
                                                        PDU Outlet kurang
                                                    </Badge>
                                                )}
                                            </div>
                                        </Cell>
                                        <Cell>
                                            {[
                                                customer.panel_asset?.name,
                                                customer.panel_asset2?.name,
                                            ]
                                                .filter(Boolean)
                                                .join(' + ') || '—'}
                                        </Cell>
                                        <Cell>
                                            {[
                                                customer.panel_mcb?.mcb_number,
                                                customer.panel_mcb2?.mcb_number,
                                            ]
                                                .filter(Boolean)
                                                .join(' + ') || '—'}
                                        </Cell>
                                        <Cell>
                                            {customer.connectivity ?? '—'}
                                        </Cell>
                                        <Cell>{customer.ip_address ?? '—'}</Cell>
                                        <Cell>
                                            {customer.switch_type ?? '—'}
                                        </Cell>
                                        <Cell>
                                            {customer.service_vlan ?? '—'}
                                        </Cell>
                                        <Cell>{customer.vlan ?? '—'}</Cell>
                                        <Cell>{customer.port ?? '—'}</Cell>
                                        <Cell>
                                            {customer.account_manager ?? '—'}
                                        </Cell>
                                        <Cell>{customer.notes ?? '—'}</Cell>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditCustomerDialog
                                                        customer={customer}
                                                        availableRacks={
                                                            availableRacks
                                                        }
                                                        filterOptions={
                                                            filterOptions
                                                        }
                                                        switchOptions={
                                                            switchOptions
                                                        }
                                                        panelOptions={
                                                            panelOptions
                                                        }
                                                    />
                                                    <DeleteCustomerDialog
                                                        customer={customer}
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
                    customers={customers}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    perPage={filters.per_page}
                    listUrl={listUrl}
                />
            </div>
        </>
    );
}

CustomerIndex.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, customerOverview.url()) ?? [
            { title: 'Customer List', href: customerOverview() },
        ],
});
