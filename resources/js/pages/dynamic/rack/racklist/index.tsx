import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import RackTypeController from '@/actions/App/Http/Controllers/RackTypeController';
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
import { racklist as rackRacklist } from '@/routes/rack';

type RackTypeRow = {
    id: number;
    name: string;
    capacity: string;
    racks_count: number;
};

type PaginatedRackTypes = {
    data: RackTypeRow[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type RackTypeFilters = {
    search: string;
    search_column: string;
    per_page: number;
};

type RackTypeErrors = Partial<Record<'name' | 'capacity', string>>;

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

const searchColumnOptions = [
    { value: 'all', label: 'Semua kolom' },
    { value: 'name', label: 'Tipe' },
    { value: 'capacity', label: 'Kapasitas' },
] as const;

const searchColumnPlaceholders: Record<string, string> = {
    all: 'Cari tipe atau kapasitas...',
    name: 'Cari tipe...',
    capacity: 'Cari kapasitas...',
};

function RackTypeFields({
    idPrefix,
    rackType,
    errors,
}: {
    idPrefix: string;
    rackType?: RackTypeRow;
    errors: RackTypeErrors;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-name`}>Tipe</Label>
                <Input
                    id={`${idPrefix}-name`}
                    name="name"
                    className="mt-0 block w-full"
                    defaultValue={rackType?.name ?? ''}
                    placeholder="mis. Full Rack Server"
                    required
                    autoComplete="off"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-capacity`}>Kapasitas</Label>
                <Input
                    id={`${idPrefix}-capacity`}
                    name="capacity"
                    className="mt-0 block w-full"
                    defaultValue={rackType?.capacity ?? ''}
                    placeholder="mis. 42U"
                    required
                    autoComplete="off"
                />
                <InputError message={errors.capacity} />
            </div>
        </div>
    );
}

function CreateRackTypeDialog() {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Tambah Tipe
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>Create rack type</DialogTitle>
                <DialogDescription>
                    Add a new rack type. The capacity is applied automatically
                    to racks using this type.
                </DialogDescription>

                <Form
                    {...RackTypeController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setFormKey((key) => key + 1);
                        router.reload({ only: ['rackTypes'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <RackTypeFields
                                key={formKey}
                                idPrefix="create"
                                errors={errors as RackTypeErrors}
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

function EditRackTypeDialog({ rackType }: { rackType: RackTypeRow }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>Edit rack type</DialogTitle>
                <DialogDescription>
                    Update {rackType.name}. Racks using this type follow the new
                    name and capacity.
                </DialogDescription>

                <Form
                    {...RackTypeController.update.form(rackType.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['rackTypes'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <RackTypeFields
                                idPrefix={`edit-${rackType.id}`}
                                rackType={rackType}
                                errors={errors as RackTypeErrors}
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

function DeleteRackTypeDialog({ rackType }: { rackType: RackTypeRow }) {
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
                <DialogTitle>Delete rack type</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {rackType.name}? This action
                    cannot be undone.
                </DialogDescription>

                <Form
                    {...RackTypeController.destroy.form(rackType.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['rackTypes'] });
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

function RackTypeSearch({
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
                rackRacklist.url(),
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
                    only: ['rackTypes', 'filters'],
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
                    id="rack-type-search"
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
            rackRacklist.url(),
            {
                search: search || undefined,
                search_column: searchColumn !== 'all' ? searchColumn : undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['rackTypes', 'filters'],
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
    rackTypes,
    search,
    searchColumn,
    perPage,
}: {
    rackTypes: PaginatedRackTypes;
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
                    {rackTypes.total}
                </span>{' '}
                total rack types
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {rackTypes.links.map((link, index) =>
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
    'Tipe',
    'Kapasitas',
    'Jumlah rack',
    'Aksi',
] as const;

export default function RackRacklist({
    rackTypes,
    filters,
    canManage,
}: {
    rackTypes: PaginatedRackTypes;
    filters: RackTypeFilters;
    canManage: boolean;
}) {
    useEchoPublic('racks', '.racks.changed', () => {
        router.reload();
    });

    return (
        <>
            <Head title="Rack List" />

            <h1 className="sr-only">Rack List</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Rack List"
                    description="Manage the rack types and default capacities"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <RackTypeSearch
                        search={filters.search}
                        searchColumn={filters.search_column}
                        perPage={filters.per_page}
                    />
                    {canManage && (
                        <div className="flex items-center gap-2">
                            <CreateRackTypeDialog />
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
                            {rackTypes.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={tableHeaders.length}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada tipe rak ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                rackTypes.data.map((rackType, index) => (
                                    <tr
                                        key={rackType.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {(rackTypes.current_page - 1) *
                                                rackTypes.per_page +
                                                index +
                                                1}
                                        </td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {rackType.name}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className="border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400"
                                            >
                                                {rackType.capacity}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={
                                                    rackType.racks_count > 0
                                                        ? 'border-green-600/50 bg-green-600/10 text-green-600 dark:text-green-400'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {rackType.racks_count}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            {canManage && (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditRackTypeDialog
                                                        rackType={rackType}
                                                    />
                                                    <DeleteRackTypeDialog
                                                        rackType={rackType}
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
                    rackTypes={rackTypes}
                    search={filters.search}
                    searchColumn={filters.search_column}
                    perPage={filters.per_page}
                />
            </div>
        </>
    );
}

RackRacklist.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, rackRacklist.url()) ?? [
            { title: 'Rack List', href: rackRacklist() },
        ],
});
