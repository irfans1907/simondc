import { Form, Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { index as rolesIndex } from '@/routes/roles';
import type { Role } from '@/types';

type PaginatedRoles = {
    data: Role[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

function PermissionBadge({ granted }: { granted: boolean }) {
    return granted ? (
        <Badge className="border-transparent bg-green-600 text-white">
            Yes
        </Badge>
    ) : (
        <Badge variant="outline">No</Badge>
    );
}

function PermissionsField({
    availablePermissions,
    defaultPermissions,
}: {
    availablePermissions: string[];
    defaultPermissions?: string[];
}) {
    const [selected, setSelected] = useState<string[]>(defaultPermissions ?? []);

    function toggle(permission: string, checked: boolean) {
        setSelected((prev) =>
            checked ? [...prev, permission] : prev.filter((p) => p !== permission),
        );
    }

    const grouped = availablePermissions.reduce(
        (acc, perm) => {
            const [type, ...rest] = perm.split(' ');
            const label = rest.join(' ');
            const group = type === 'manage' ? 'Manage' : 'View';

            if (!acc[group]) {
                acc[group] = [];
            }

            acc[group].push({ permission: perm, label });

            return acc;
        },
        {} as Record<string, { permission: string; label: string }[]>,
    );

    const allSelected = availablePermissions.every((p) => selected.includes(p));

    function toggleAll(checked: boolean) {
        setSelected(checked ? [...availablePermissions] : []);
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <Label>Permissions</Label>
                <button
                    type="button"
                    onClick={() => toggleAll(!allSelected)}
                    className="text-xs text-muted-foreground hover:text-foreground"
                >
                    {allSelected ? 'Deselect all' : 'Select all'}
                </button>
            </div>
            {Object.entries(grouped).map(([group, perms]) => (
                <div key={group} className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                        {group}
                    </p>
                    <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                        {perms.map(({ permission, label }) => (
                            <div key={permission} className="flex items-center gap-2 rounded-md border px-3 py-1.5">
                                <Checkbox
                                    id={`perm-${permission}`}
                                    checked={selected.includes(permission)}
                                    onCheckedChange={(value) => toggle(permission, value === true)}
                                />
                                <Label htmlFor={`perm-${permission}`} className="cursor-pointer text-sm">
                                    {label}
                                </Label>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
            {selected.map((permission) => (
                <input key={permission} type="hidden" name="permissions[]" value={permission} />
            ))}
        </div>
    );
}

function CreateRoleDialog({ availablePermissions }: { availablePermissions: string[] }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Create Role
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Create role</DialogTitle>
                <DialogDescription>
                    Add a new role to the application.
                </DialogDescription>

                <Form
                    {...RoleController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['roles'] });
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Role name</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    className="mt-1 block w-full"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                                <InputError message={errors.permissions} />
                            </div>

                            <PermissionsField availablePermissions={availablePermissions} />

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

function EditRoleDialog({
    role,
    availablePermissions,
}: {
    role: Role;
    availablePermissions: string[];
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
                <DialogTitle>Edit role</DialogTitle>
                <DialogDescription>
                    Update the name and permissions for {role.name}.
                </DialogDescription>

                <Form
                    {...RoleController.update.form(role.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['roles'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${role.id}`}>
                                    Role name
                                </Label>
                                <Input
                                    id={`name-${role.id}`}
                                    name="name"
                                    className="mt-1 block w-full"
                                    defaultValue={role.name}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                                <InputError message={errors.permissions} />
                            </div>

                            <PermissionsField
                                availablePermissions={availablePermissions}
                                defaultPermissions={role.permissions as string[]}
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

function DeleteRoleDialog({ role }: { role: Role }) {
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
                <DialogTitle>Delete role</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {role.name}? This action
                    cannot be undone.
                </DialogDescription>

                <Form
                    {...RoleController.destroy.form(role.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['roles'] });
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

function RoleSearch({ search, perPage }: { search: string; perPage: number }) {
    const [value, setValue] = useState(search);

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search) {
                return;
            }

            router.get(
                rolesIndex(),
                { search: query || undefined, per_page: perPage },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['roles', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, search, perPage]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="role-search"
                name="search"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setValue('');
                    }
                }}
                placeholder="Search role..."
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
            rolesIndex(),
            {
                search: search || undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['roles', 'filters'],
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
    roles,
    search,
    perPage,
}: {
    roles: PaginatedRoles;
    search: string;
    perPage: number;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector search={search} perPage={perPage} />
            <span className="text-sm text-muted-foreground">
                Showing of{' '}
                <span className="font-medium text-foreground">
                    {roles.total}
                </span>{' '}
                total roles
            </span>

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {roles.links.map((link, index) => {
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

export default function RolesIndex({
    roles,
    filters,
    availablePermissions = [],
}: {
    roles: PaginatedRoles;
    filters: { search: string; per_page: number };
    availablePermissions?: string[];
}) {
    useEchoPublic('roles', '.roles.changed', (payload: unknown) => {
        void payload;
        router.reload({ only: ['roles'] });
    });

    useEchoPublic('users', '.users.changed', (payload: unknown) => {
        void payload;
        router.reload({ only: ['roles'] });
    });

    return (
        <>
            <Head title="Role Settings" />

            <h1 className="sr-only">Role Settings</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Role Settings"
                    description="Manage the roles of the application"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <RoleSearch
                        search={filters.search}
                        perPage={filters.per_page}
                    />
                    <div className="flex items-center gap-4">
                        <CreateRoleDialog availablePermissions={availablePermissions} />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                <th className="px-4 py-3 text-center font-medium">
                                    Role
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Permissions
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Users
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No roles found.
                                    </td>
                                </tr>
                            ) : (
                                roles.data.map((role) => (
                                    <tr
                                        key={role.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center font-medium">
                                            {role.name}
                                        </td>
                                        <td className="px-4 py-3 w-180 text-center">
                                            <div className="flex flex-wrap items-center justify-center gap-1">
                                                {(role.permissions as string[])?.length ? (
                                                    (role.permissions as string[]).map((perm) => (
                                                        <Badge key={perm} variant="secondary" className="capitalize">
                                                            {perm}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {role.users_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            {role.name === 'superadmin' ? (
                                                <div className="flex items-center justify-center">
                                                    <Badge variant="outline">
                                                        Protected
                                                    </Badge>
                                                </div>
                                            ) : (
                                                <div className="flex items-center justify-center gap-2">
                                                    <EditRoleDialog
                                                        role={role}
                                                        availablePermissions={availablePermissions}
                                                    />
                                                    <DeleteRoleDialog
                                                        role={role}
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
                    roles={roles}
                    search={filters.search}
                    perPage={filters.per_page}
                />
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Role Management',
            href: rolesIndex(),
        },
    ],
};
