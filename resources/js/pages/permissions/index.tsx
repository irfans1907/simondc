import { Form, Head, Link, router as inertiaRouter } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import PermissionController from '@/actions/App/Http/Controllers/PermissionController';
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
import { index as permissionsIndex } from '@/routes/permissions';
import type { Permission } from '@/types';

type PaginatedPermissions = {
    data: Permission[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

function CreatePermissionDialog() {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setError('');

        inertiaRouter.post(
            PermissionController.store.url(),
            { name },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    setName('');
                    router.reload({ only: ['permissions'] });
                },
                onError: (errors) => {
                    setError(errors.name ?? '');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Create Permission
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Create permission</DialogTitle>
                <DialogDescription>
                    Add a new permission to the application.
                </DialogDescription>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="create-permission-name">Permission name</Label>
                        <Input
                            id="create-permission-name"
                            name="name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="mt-1 block w-full"
                            required
                            autoComplete="off"
                            placeholder="e.g. manage reports"
                        />
                        <InputError message={error} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditPermissionDialog({ permission }: { permission: Permission }) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState(permission.name);
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setError('');

        inertiaRouter.patch(
            PermissionController.update.url(permission.id),
            { name },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    router.reload({ only: ['permissions'] });
                },
                onError: (errors) => {
                    setError(errors.name ?? '');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Edit permission</DialogTitle>
                <DialogDescription>
                    Update the name for {permission.name}.
                </DialogDescription>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor={`name-${permission.id}`}>Permission name</Label>
                        <Input
                            id={`name-${permission.id}`}
                            name="name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="mt-1 block w-full"
                            required
                            autoComplete="off"
                        />
                        <InputError message={error} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeletePermissionDialog({ permission }: { permission: Permission }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);

        inertiaRouter.delete(PermissionController.destroy.url(permission.id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                router.reload({ only: ['permissions'] });
            },
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete permission</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {permission.name}? This action
                    cannot be undone. Roles using this permission will lose access.
                </DialogDescription>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={handleDelete}
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PermissionSearch({ search, perPage }: { search: string; perPage: number }) {
    const [value, setValue] = useState(search);

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search) {
                return;
            }

            router.get(
                permissionsIndex(),
                { search: query || undefined, per_page: perPage },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['permissions', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, search, perPage]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="permission-search"
                name="search"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setValue('');
                    }
                }}
                placeholder="Search permission or role..."
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
            permissionsIndex(),
            {
                search: search || undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['permissions', 'filters'],
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
    permissions,
    search,
    perPage,
}: {
    permissions: PaginatedPermissions;
    search: string;
    perPage: number;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector search={search} perPage={perPage} />
            <span className="text-sm text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {permissions.total}
                </span>{' '}
                total permissions
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {permissions.links.map((link, index) => {
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

export default function PermissionsIndex({
    permissions,
    filters,
}: {
    permissions: PaginatedPermissions;
    filters: { search: string; per_page: number };
}) {
    useEchoPublic('roles', '.roles.changed', (payload: unknown) => {
        void payload;
        router.reload({ only: ['permissions'] });
    });

    return (
        <>
            <Head title="Permission Settings" />

            <h1 className="sr-only">Permission Settings</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Permission Settings"
                    description="Manage the permissions of the application"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <PermissionSearch
                        search={filters.search}
                        perPage={filters.per_page}
                    />
                    <div className="flex items-center gap-4">
                        <CreatePermissionDialog />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                <th className="px-4 py-3 text-center font-medium">
                                    Permission
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Roles
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {permissions.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No permissions found.
                                    </td>
                                </tr>
                            ) : (
                                permissions.data.map((permission) => (
                                    <tr
                                        key={permission.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center font-medium">
                                            {permission.name}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <div className="flex flex-wrap items-center justify-center gap-1">
                                                {permission.roles.length > 0 ? (
                                                    permission.roles.map((role) => (
                                                        <Badge key={role} variant="secondary" className="capitalize">
                                                            {role}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center gap-2">
                                                <EditPermissionDialog
                                                    permission={permission}
                                                />
                                                <DeletePermissionDialog
                                                    permission={permission}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    permissions={permissions}
                    search={filters.search}
                    perPage={filters.per_page}
                />
            </div>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Permission Management',
            href: permissionsIndex(),
        },
    ],
};
