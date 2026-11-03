import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { ChevronDown, Pencil, Plus, Search, Trash2, Upload, X } from 'lucide-react';
import { Fragment, useCallback, useEffect, useState } from 'react';
import AssetController from '@/actions/App/Http/Controllers/AssetController';
import ComboboxSelect from '@/components/combobox-select';
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
import { overview as assetsOverview } from '@/routes/assets';

type Asset = {
    id: number;
    name: string;
    category_path: string[];
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
    notes: string | null;
};

type CategoryNode = {
    id: number;
    name: string;
    children: CategoryNode[];
};

type PaginatedAssets = {
    data: Asset[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type AssetErrors = Partial<Record<keyof Asset | 'category_path', string>>;

type AssetStringKey = {
    [K in keyof Asset]: Asset[K] extends string | null ? K : never;
}[keyof Asset];

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

function AssetFields({
    idPrefix,
    asset,
    categories,
    errors,
}: {
    idPrefix: string;
    asset?: Asset;
    categories: CategoryNode[];
    errors: AssetErrors;
}) {
    const [path, setPath] = useState<string[]>(asset?.category_path ?? ['']);

    const levelLabels = ['Kategori', 'Sub Kategori', 'Sub Kategori 1', 'Sub Kategori 2', 'Sub Kategori 3', 'Sub Kategori 4'];

    const optionsAt = (index: number): string[] => {
        let nodes = categories;

        for (let i = 0; i < index; i++) {
            const node = nodes.find((candidate) => candidate.name === path[i]);

            if (!node) {
                return [];
            }

            nodes = node.children;
        }

        return nodes.map((node) => node.name);
    };

    const updateLevel = (index: number, value: string) => {
        setPath((current) => [...current.slice(0, index), value]);
    };

    const removeLevel = (index: number) => {
        setPath((current) => current.slice(0, index));
    };

    const textFields: { name: AssetStringKey; label: string }[] = [
        { name: 'brand', label: 'Merek' },
        { name: 'type', label: 'Type' },
        { name: 'model', label: 'Model' },
        { name: 'serial_number', label: 'Serial Number' },
        { name: 'capacity', label: 'Kapasitas' },
        { name: 'manufacture_year', label: 'Tahun Manufaktur' },
        { name: 'operation_year', label: 'Tahun Operasi' },
        { name: 'quantity', label: 'Jumlah' },
        { name: 'floor', label: 'Lantai' },
        { name: 'room', label: 'Ruang' },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-name`}>Nama Perangkat</Label>
                <Input
                    id={`${idPrefix}-name`}
                    name="name"
                    className="mt-1 block w-full"
                    defaultValue={asset?.name ?? ''}
                    required
                    autoComplete="off"
                />
                <InputError message={errors.name} />
            </div>

            {path.map((levelName, index) => (
                <div key={index} className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor={`${idPrefix}-category-${index}`}>
                            {levelLabels[index] ?? `Level ${index + 1}`}
                        </Label>
                        <div className="flex items-center gap-1">
                            {index === path.length - 1 &&
                                levelName !== '' && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setPath((current) => [
                                                ...current,
                                                '',
                                            ])
                                        }
                                        className="inline-flex size-6 items-center justify-center rounded-md border border-transparent bg-green-600 text-white hover:bg-green-700"
                                        aria-label="Tambah sub kategori"
                                    >
                                        <Plus className="size-3.5" />
                                    </button>
                                )}
                            {index > 0 && (
                                <button
                                    type="button"
                                    onClick={() => removeLevel(index)}
                                    className="inline-flex size-6 items-center justify-center rounded-md border border-transparent bg-red-600 text-white hover:bg-red-700"
                                    aria-label={`Hapus level ${index + 1}`}
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>
                    </div>
                    <ComboboxSelect
                        name="category_path[]"
                        id={`${idPrefix}-category-${index}`}
                        options={optionsAt(index)}
                        value={levelName}
                        onValueChange={(value) => updateLevel(index, value)}
                        allowCreate
                        placeholder={
                            index === 0 || path[index - 1]
                                ? 'Pilih/ketik kategori...'
                                : 'Pilih level sebelumnya dulu...'
                        }
                        searchPlaceholder="Cari atau ketik baru..."
                        emptyMessage={
                            index === 0 || path[index - 1]
                                ? 'Belum ada opsi.'
                                : 'Pilih level sebelumnya dulu...'
                        }
                        required={index === 0}
                    />
                </div>
            ))}

            <InputError message={errors.category_path} />

            {textFields.map((field) => (
                <div key={field.name} className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-${field.name}`}>
                        {field.label}
                    </Label>
                    <Input
                        id={`${idPrefix}-${field.name}`}
                        name={field.name}
                        className="mt-1 block w-full"
                        defaultValue={asset?.[field.name] ?? ''}
                        required
                        autoComplete="off"
                    />
                    <InputError message={errors[field.name]} />
                </div>
            ))}

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${idPrefix}-notes`}>Catatan</Label>
                <textarea
                    id={`${idPrefix}-notes`}
                    name="notes"
                    className="border-input bg-transparent text-muted-foreground mt-1 flex h-24 w-full min-w-0 resize-none rounded-md border px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    defaultValue={asset?.notes ?? ''}
                    autoComplete="off"
                />
                <InputError message={errors.notes} />
            </div>
        </div>
    );
}

function CreateAssetDialog({ categories }: { categories: CategoryNode[] }) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Tambah Aset
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Create asset</DialogTitle>
                <DialogDescription>
                    Add a new asset to the inventory.
                </DialogDescription>

                <Form
                    {...AssetController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                        router.reload({ only: ['assets'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <AssetFields
                                key={formKey}
                                idPrefix="create"
                                categories={categories}
                                errors={errors as AssetErrors}
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

function EditAssetDialog({
    asset,
    categories,
}: {
    asset: Asset;
    categories: CategoryNode[];
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
                <DialogTitle>Edit asset</DialogTitle>
                <DialogDescription>
                    Update the details for {asset.name}.
                </DialogDescription>

                <Form
                    {...AssetController.update.form(asset.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['assets'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <AssetFields
                                idPrefix={`edit-${asset.id}`}
                                asset={asset}
                                categories={categories}
                                errors={errors as AssetErrors}
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

function DeleteAssetDialog({ asset }: { asset: Asset }) {
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
                <DialogTitle>Delete asset</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {asset.name}? This action
                    cannot be undone.
                </DialogDescription>

                <Form
                    {...AssetController.destroy.form(asset.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['assets'] });
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
    { value: 'name', label: 'Nama' },
    { value: 'brand', label: 'Merek' },
    { value: 'serial_number', label: 'Serial Number' },
    { value: 'category', label: 'Kategori' },
    { value: 'description', label: 'Deskripsi' },
    { value: 'type', label: 'Type' },
    { value: 'model', label: 'Model' },
    { value: 'capacity', label: 'Kapasitas' },
    { value: 'room', label: 'Ruang' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari nama, merek, atau serial number...',
    name: 'Cari nama perangkat...',
    brand: 'Cari merek...',
    serial_number: 'Cari serial number...',
    category: 'Cari kategori...',
    description: 'Cari deskripsi (nama + kategori)...',
    type: 'Cari type...',
    model: 'Cari model...',
    capacity: 'Cari kapasitas...',
    room: 'Cari ruang...',
};

function AssetSearch({
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
                assetsOverview(),
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
        <div className="flex w-full flex-wrap items-center gap-2 sm:max-w-[475px]">
            <Select
                value={column}
                onValueChange={(next) => {
                    setColumn(next);
                    applyFilter(value.trim(), next);
                }}
            >
                <SelectTrigger className="h-9 w-[145px] shrink-0">
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
                    id="asset-search"
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
    perPage,
}: {
    search: string;
    perPage: number;
}) {
    function changePerPage(value: string) {
        router.get(
            assetsOverview(),
            {
                search: search || undefined,
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
    perPage,
}: {
    assets: PaginatedAssets;
    search: string;
    perPage: number;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector search={search} perPage={perPage} />

            <span className="text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {assets.total}
                </span>{' '}
                total assets
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {assets.links.map((link, index) => {
                        if (link.url === null) {
                            return (
                                <span
                                    key={index}
                                    className="px-3 py-1.5 text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            );
                        }

                        const isActive = link.active;

                        return (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 ${
                                    isActive
                                        ? 'bg-primary font-medium text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground'
                                }`}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

const tableHeaders = [
    'No',
    'Nama Perangkat',
    'Kategori',
    'Sub Kategori',
    'Deskripsi',
    'Merek',
    'Type',
    'Model',
    'Serial Number',
    'Kapasitas',
    'Tahun Manufaktur',
    'Tahun Operasi',
    'Jumlah',
    'Lantai',
    'Ruang',
    'Catatan',
    'Aksi',
] as const;

export default function AssetsIndex({
    assets,
    filters,
    categories,
    canManage,
}: {
    assets: PaginatedAssets;
    filters: { search: string; search_column: string; per_page: number };
    categories: CategoryNode[];
    canManage: boolean;
}) {
    useEchoPublic('assets', '.assets.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Asset Management" />

            <h1 className="sr-only">Asset Management</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Asset Management"
                    description="Manage the data center asset inventory"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <AssetSearch
                        search={filters.search}
                        searchColumn={filters.search_column}
                        perPage={filters.per_page}
                    />
                    {canManage && (
                        <div className="flex items-center gap-2">
                            <Button
                                variant="default"
                                className="bg-green-600 text-white hover:bg-green-700"
                                asChild
                            >
                                <a
                                    href={AssetController.exportMethod.url({
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
                            <CreateAssetDialog categories={categories} />
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
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant="outline">
                                                {asset.category_path[0]}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.category_path.length > 1 ? (
                                                <div className="flex flex-wrap items-center justify-center gap-1.5">
                                                    {asset.category_path
                                                        .slice(1)
                                                        .map(
                                                            (level, index) => (
                                                                <Fragment
                                                                    key={index}
                                                                >
                                                                    {index >
                                                                        0 && (
                                                                        <ChevronDown className="size-3.5 text-muted-foreground" />
                                                                    )}
                                                                    <Badge variant="outline">
                                                                        {level}
                                                                    </Badge>
                                                                </Fragment>
                                                            ),
                                                        )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {[
                                                asset.name,
                                                ...asset.category_path,
                                            ].join(' ')}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.brand}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.type}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.model}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.serial_number}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.capacity}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.manufacture_year}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.operation_year}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.quantity}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.floor}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.room}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {asset.notes ?? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditAssetDialog
                                                        asset={asset}
                                                        categories={categories}
                                                    />
                                                    <DeleteAssetDialog
                                                        asset={asset}
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
                    assets={assets}
                    search={filters.search}
                    perPage={filters.per_page}
                />
            </div>
        </>
    );
}

AssetsIndex.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, assetsOverview.url()) ?? [
            { title: 'Asset Management', href: assetsOverview() },
        ],
});
