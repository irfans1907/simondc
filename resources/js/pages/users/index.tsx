import { Form, Head, Link, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { Pencil, Search, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
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
import { index as usersIndex } from '@/routes/users';
import type { Auth, User } from '@/types';

type PaginatedUsers = {
    data: User[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const perPageOptions = [5, 10, 15, 20, 25, 50] as const;

function RoleBadge({ role }: { role: string }) {
    const variant =
        role === 'superadmin'
            ? 'default'
            : role === 'admin'
              ? 'secondary'
              : 'outline';

    return <Badge variant={variant}>{role}</Badge>;
}

function VerifiedBadge({ verified }: { verified: boolean }) {
    return verified ? (
        <Badge className="border-transparent bg-green-600 text-white">
            Verified
        </Badge>
    ) : (
        <Badge className="border-transparent bg-red-600 text-white">
            Unverified
        </Badge>
    );
}

function UserRoleSelect({
    id,
    defaultValue,
}: {
    id: string;
    defaultValue: string;
}) {
    const [value, setValue] = useState(defaultValue);

    return (
        <div className="grid gap-2">
            <input type="hidden" name="role" value={value} />
            <Label htmlFor={id}>Role</Label>
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger id={id} className="mt-1 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="admin">admin</SelectItem>
                    <SelectItem value="user">user</SelectItem>
                </SelectContent>
            </Select>
        </div>
    );
}

function EditUserDialog({
    user,
    canManageUsers,
}: {
    user: User;
    canManageUsers: boolean;
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
                <DialogTitle>Edit user</DialogTitle>
                <DialogDescription>
                    Update the name and email for {user.name}.
                </DialogDescription>

                <Form
                    {...UserController.update.form(user.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['users'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${user.id}`}>Name</Label>
                                <Input
                                    id={`name-${user.id}`}
                                    name="name"
                                    className="mt-1 block w-full"
                                    defaultValue={user.name}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`email-${user.id}`}>
                                    Email address
                                </Label>
                                <Input
                                    id={`email-${user.id}`}
                                    name="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={user.email}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.email} />
                            </div>

                            {canManageUsers && (
                                <UserRoleSelect
                                    id={`role-${user.id}`}
                                    defaultValue={user.role ?? 'user'}
                                />
                            )}

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

function DeleteUserDialog({ user }: { user: User }) {
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
                <DialogTitle>Delete user</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {user.name}? This action
                    cannot be undone.
                </DialogDescription>

                <Form
                    {...UserController.destroy.form(user.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['users'] });
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

function UserSearch({ search, perPage }: { search: string; perPage: number }) {
    const [value, setValue] = useState(search);

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search) {
                return;
            }

            router.get(
                usersIndex(),
                { search: query || undefined, per_page: perPage },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['users', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, search, perPage]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="user-search"
                name="search"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setValue('');
                    }
                }}
                placeholder="Search name or email..."
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
            usersIndex(),
            {
                search: search || undefined,
                per_page: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users', 'filters'],
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
    users,
    search,
    perPage,
}: {
    users: PaginatedUsers;
    search: string;
    perPage: number;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector search={search} perPage={perPage} />

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {users.links.map((link, index) => {
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

export default function UsersIndex({
    users,
    filters,
}: {
    users: PaginatedUsers;
    filters: { search: string; per_page: number };
}) {
    const { auth } = usePage<{ auth: Auth }>().props;

    useEchoPublic('users', '.users.changed', (payload: unknown) => {
        void payload;
        router.reload({ only: ['users'] });
    });

    useEchoPublic('roles', '.roles.changed', (payload: unknown) => {
        void payload;
        router.reload({ only: ['users'] });
    });

    return (
        <>
            <Head title="User Management" />

            <h1 className="sr-only">User Management</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="User Management"
                    description="Manage the users of the application"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <UserSearch
                        search={filters.search}
                        perPage={filters.per_page}
                    />
                    <span className="text-sm text-muted-foreground">
                        Showing of{' '}
                        <span className="font-medium text-foreground">
                            {users.total}
                        </span>{' '}
                        total users
                    </span>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                <th className="px-4 py-3 text-center font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Email
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Role
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
                            {users.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center font-medium">
                                            <span className="inline-flex items-center gap-2">
                                                {user.name}
                                                {user.id === auth.user.id && (
                                                    <Badge className="border-transparent bg-blue-600 text-white">
                                                        You
                                                    </Badge>
                                                )}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <RoleBadge
                                                role={user.role ?? 'user'}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <VerifiedBadge
                                                verified={
                                                    user.email_verified_at !==
                                                    null
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {user.role === 'superadmin' ? (
                                                <div className="flex items-center justify-center">
                                                    <Badge variant="outline">
                                                        Protected
                                                    </Badge>
                                                </div>
                                            ) : (
                                                <div className="flex items-center justify-center gap-2">
                                                     <EditUserDialog
                                                        user={user}
                                                        canManageUsers={
                                                            auth.is_superadmin ||
                                                            auth.permissions.includes('manage users')
                                                        }
                                                    />
                                                    <DeleteUserDialog
                                                        user={user}
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
                    users={users}
                    search={filters.search}
                    perPage={filters.per_page}
                />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'User Management',
            href: usersIndex(),
        },
    ],
};
