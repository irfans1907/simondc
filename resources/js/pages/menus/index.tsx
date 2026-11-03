import { Form, Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import MenuController from '@/actions/App/Http/Controllers/MenuController';
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
import { index as menusIndex } from '@/routes/menus';

type Menu = {
    id: number;
    name: string;
    group: string;
    parent_id: number | null;
    url: string;
    icon: string;
    order: number;
    is_active: boolean;
    existing_permissions: string[];
    created_at: string;
    updated_at: string;
};

type ParentMenu = {
    id: number;
    name: string;
    group: string;
    url: string | null;
};

type PaginatedMenus = {
    data: Menu[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

function ParentSelect({
    id,
    parentMenus,
    defaultValue,
    excludeId,
    value,
    onValueChange,
}: {
    id: string;
    parentMenus: ParentMenu[];
    defaultValue?: number | null;
    excludeId?: number;
    value?: string;
    onValueChange?: (value: string) => void;
}) {
    const options = parentMenus.filter((menu) => menu.id !== excludeId);

    return (
        <Select
            name="parent_id"
            value={value}
            defaultValue={defaultValue ? String(defaultValue) : ''}
            onValueChange={onValueChange}
        >
            <SelectTrigger id={id} className="mt-1 w-full">
                <SelectValue placeholder="None (top-level menu)" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="">None (top-level menu)</SelectItem>
                {options.map((menu) => (
                    <SelectItem key={menu.id} value={String(menu.id)}>
                        {menu.name} ({menu.group})
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function CreateMenuDialog({
    existingGroups,
    parentMenus,
}: {
    existingGroups: string[];
    parentMenus: ParentMenu[];
}) {
    const [open, setOpen] = useState(false);
    const [parentId, setParentId] = useState('');
    const [url, setUrl] = useState('');

    function handleParentChange(next: string) {
        setParentId(next);

        const parent = parentMenus.find((menu) => String(menu.id) === next);

        setUrl(parent?.url ?? '');
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Create Menu
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Create menu</DialogTitle>
                <DialogDescription>
                    Add a new menu to the sidebar. A permission will be auto-created.
                </DialogDescription>

                <Form
                    {...MenuController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setParentId('');
                        setUrl('');
                        router.reload({ only: ['menus', 'existingGroups'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Menu name</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. Email Manager"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-group">Group (NavMain label)</Label>
                                <ComboboxSelect
                                    name="group"
                                    id="create-group"
                                    options={existingGroups}
                                    defaultValue="Main Menu"
                                    allowCreate
                                    placeholder="Select or type a group..."
                                    searchPlaceholder="Search or type new..."
                                    emptyMessage="No groups yet."
                                    required
                                />
                                <InputError message={errors.group} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-parent">Parent menu</Label>
                                <ParentSelect
                                    id="create-parent"
                                    parentMenus={parentMenus}
                                    value={parentId}
                                    onValueChange={handleParentChange}
                                />
                                <InputError message={errors.parent_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-url">URL</Label>
                                <Input
                                    id="create-url"
                                    name="url"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. /email"
                                    value={url}
                                    onChange={(event) => setUrl(event.target.value)}
                                    autoComplete="off"
                                />
                                <InputError message={errors.url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-icon">Icon (Lucide name)</Label>
                                <Input
                                    id="create-icon"
                                    name="icon"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. Mail"
                                    autoComplete="off"
                                />
                                <InputError message={errors.icon} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-order">Order</Label>
                                <Input
                                    id="create-order"
                                    name="order"
                                    type="number"
                                    min="0"
                                    className="mt-1 block w-full"
                                    defaultValue="0"
                                    autoComplete="off"
                                />
                                <InputError message={errors.order} />
                            </div>

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

function EditMenuDialog({
    menu,
    existingGroups,
    parentMenus,
}: {
    menu: Menu;
    existingGroups: string[];
    parentMenus: ParentMenu[];
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
            <DialogContent>
                <DialogTitle>Edit menu</DialogTitle>
                <DialogDescription>
                    Update the details for {menu.name}.
                </DialogDescription>

                <Form
                    {...MenuController.update.form(menu.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['menus', 'existingGroups'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${menu.id}`}>Menu name</Label>
                                <Input
                                    id={`name-${menu.id}`}
                                    name="name"
                                    className="mt-1 block w-full"
                                    defaultValue={menu.name}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`group-${menu.id}`}>Group (NavMain label)</Label>
                                <ComboboxSelect
                                    name="group"
                                    id={`group-${menu.id}`}
                                    options={existingGroups}
                                    defaultValue={menu.group}
                                    allowCreate
                                    placeholder="Select or type a group..."
                                    searchPlaceholder="Search or type new..."
                                    emptyMessage="No groups yet."
                                    required
                                />
                                <InputError message={errors.group} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`parent-${menu.id}`}>Parent menu</Label>
                                <ParentSelect
                                    id={`parent-${menu.id}`}
                                    parentMenus={parentMenus}
                                    defaultValue={menu.parent_id}
                                    excludeId={menu.id}
                                />
                                <InputError message={errors.parent_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`url-${menu.id}`}>URL</Label>
                                <Input
                                    id={`url-${menu.id}`}
                                    name="url"
                                    className="mt-1 block w-full"
                                    defaultValue={menu.url}
                                    autoComplete="off"
                                />
                                <InputError message={errors.url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`icon-${menu.id}`}>Icon (Lucide name)</Label>
                                <Input
                                    id={`icon-${menu.id}`}
                                    name="icon"
                                    className="mt-1 block w-full"
                                    defaultValue={menu.icon}
                                    autoComplete="off"
                                />
                                <InputError message={errors.icon} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`order-${menu.id}`}>Order</Label>
                                <Input
                                    id={`order-${menu.id}`}
                                    name="order"
                                    type="number"
                                    min="0"
                                    className="mt-1 block w-full"
                                    defaultValue={menu.order}
                                    autoComplete="off"
                                />
                                <InputError message={errors.order} />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={menu.is_active}
                                    className="size-4"
                                />
                                <Label>Active</Label>
                            </div>

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

function DeleteMenuDialog({ menu }: { menu: Menu }) {
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
                <DialogTitle>Delete menu</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {menu.name}? The associated permission will also be deleted. This action cannot be undone.
                </DialogDescription>

                <Form
                    {...MenuController.destroy.form(menu.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['menus', 'existingGroups'] });
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

function MenuSearch({ search, perPage }: { search: string; perPage: number }) {
    const [value, setValue] = useState(search);

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search) {
                return;
            }

            router.get(
                menusIndex(),
                { search: query || undefined, per_page: perPage },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['menus', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, search, perPage]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="menu-search"
                name="search"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setValue('');
                    }
                }}
                placeholder="Search menu..."
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
            menusIndex(),
            {
                search: search || undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['menus', 'filters'],
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
    paginatedMenus,
    search,
    perPage,
}: {
    paginatedMenus: PaginatedMenus;
    search: string;
    perPage: number;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector search={search} perPage={perPage} />
            <span className="text-sm text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {paginatedMenus.total}
                </span>{' '}
                total menus
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {paginatedMenus.links.map((link, index) => {
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

export default function MenusIndex({
    paginatedMenus,
    existingGroups,
    parentMenus,
    filters,
}: {
    paginatedMenus: PaginatedMenus;
    existingGroups: string[];
    parentMenus: ParentMenu[];
    filters: { search: string; per_page: number };
}) {
    useEchoPublic('menus', '.menus.changed', () => {
        router.reload({ only: ['menus'] });
    });

    return (
        <>
            <Head title="Menu Management" />

            <h1 className="sr-only">Menu Management</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Menu Management"
                    description="Manage sidebar menus and their permissions"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <MenuSearch
                        search={filters.search}
                        perPage={filters.per_page}
                    />
                    <div className="flex items-center gap-4">
                        <CreateMenuDialog existingGroups={existingGroups} parentMenus={parentMenus} />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                <th className="px-4 py-3 text-center font-medium">
                                    Menu
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Type
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Group
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    URL
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Permission
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Order
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedMenus.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No menus found.
                                    </td>
                                </tr>
                            ) : (
                                paginatedMenus.data.map((menu) => (
                                    <tr
                                        key={menu.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td
                                            className={`px-4 py-3 text-center ${
                                                menu.parent_id
                                                    ? 'font-normal text-muted-foreground'
                                                    : 'font-semibold'
                                            }`}
                                        >
                                            {menu.parent_id ? (
                                                <span className="inline-block pl-6">
                                                    └─ {menu.name}
                                                </span>
                                            ) : (
                                                menu.name
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {menu.parent_id ? (
                                                <Badge variant="outline" className="text-muted-foreground">
                                                    Child
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">Parent</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant="outline">
                                                {menu.group}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <code className="text-xs bg-muted px-1.5 py-0.5 rounded">
                                                {menu.url || '—'}
                                            </code>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex flex-wrap items-center justify-center gap-1">
                                                {menu.existing_permissions.length > 0 ? (
                                                    menu.existing_permissions.map((perm) => (
                                                        <Badge key={perm} variant="secondary" className="capitalize">
                                                            {perm}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {menu.order}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {menu.is_active ? (
                                                <Badge className="border-transparent bg-green-600 text-white">
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center gap-2">
                                                <EditMenuDialog menu={menu} existingGroups={existingGroups} parentMenus={parentMenus} />
                                                <DeleteMenuDialog menu={menu} />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    paginatedMenus={paginatedMenus}
                    search={filters.search}
                    perPage={filters.per_page}
                />
            </div>
        </>
    );
}

MenusIndex.layout = {
    breadcrumbs: [
        {
            title: 'Menu Management',
            href: menusIndex(),
        },
    ],
};
