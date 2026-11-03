import { Form, Head, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { ArrowDown, ArrowUp, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AssetCategoryController from '@/actions/App/Http/Controllers/AssetCategoryController';
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
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { overview as assetsOverview } from '@/routes/assets';

type CategoryRow = {
    id: number;
    name: string;
    parent_id: number | null;
    depth: number;
    assets_count: number;
};

type Props = {
    categories: CategoryRow[];
    canManage: boolean;
};

export default function AssetCategorylistIndex({ categories, canManage }: Props) {
    useEchoPublic('assets', '.assets.changed', () => {
        router.reload();
    });

    const [search, setSearch] = useState('');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
    const [selected, setSelected] = useState<number[]>([]);

    const sorted = useMemo(() => {
        const childrenByParent = new Map<number | null, CategoryRow[]>();

        for (const category of categories) {
            const siblings = childrenByParent.get(category.parent_id) ?? [];
            siblings.push(category);
            childrenByParent.set(category.parent_id, siblings);
        }

        const direction = sortDir === 'asc' ? 1 : -1;
        const rows: CategoryRow[] = [];

        const walk = (parentId: number | null) => {
            const siblings = (childrenByParent.get(parentId) ?? [])
                .slice()
                .sort((a, b) => direction * (a.assets_count - b.assets_count));

            for (const category of siblings) {
                rows.push(category);
                walk(category.id);
            }
        };

        walk(null);

        return rows;
    }, [categories, sortDir]);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (query === '') {
            return sorted;
        }

        const rows: CategoryRow[] = [];
        const ancestorMatched: boolean[] = [];

        for (const category of sorted) {
            ancestorMatched.length = category.depth;

            const parentMatched = category.depth > 0 ? ancestorMatched[category.depth - 1] : false;
            const isShown = parentMatched || category.name.toLowerCase().includes(query);

            ancestorMatched[category.depth] = isShown;

            if (isShown) {
                rows.push(category);
            }
        }

        return rows;
    }, [sorted, search]);

    const filteredIds = filtered.map((category) => category.id);
    const allSelected = filteredIds.length > 0 && filteredIds.every((id) => selected.includes(id));

    function toggleSort() {
        setSortDir((current) => (current === 'asc' ? 'desc' : 'asc'));
    }

    function toggleAll() {
        setSelected(allSelected ? [] : filteredIds);
    }

    function toggleOne(id: number) {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((selectedId) => selectedId !== id)
                : [...current, id],
        );
    }

    return (
        <>
            <Head title="Kategori Aset" />

            <h1 className="sr-only">Kategori Aset</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Kategori Aset"
                    description="Kelola kategori dan sub-kategori aset."
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <CategorySearch value={search} onChange={setSearch} />
                    <div className="flex items-center gap-4">
                        {canManage && selected.length > 0 ? (
                            <>
                                <span className="text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {selected.length}
                                    </span>{' '}
                                    selected
                                </span>
                                <BulkDeleteDialog
                                    categories={categories}
                                    selected={selected}
                                    onDone={() => setSelected([])}
                                />
                            </>
                        ) : (
                            <>
                                <span className="text-sm text-muted-foreground">
                                    Showing{' '}
                                    <span className="font-medium text-foreground">
                                        {filtered.length}
                                    </span>{' '}
                                    of {categories.length} categories
                                </span>
                                {canManage && (
                                    <CreateCategoryDialog categories={categories} />
                                )}
                            </>
                        )}
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                {canManage && (
                                    <th className="w-45 py-3 align-middle">
                                        <div className="flex h-full items-center justify-center">
                                            <Checkbox
                                                checked={allSelected}
                                                onCheckedChange={toggleAll}
                                                aria-label="Select all categories"
                                                className="border-muted-foreground/60"
                                            />
                                        </div>
                                    </th>
                                )}
                                <th className="px-4 py-3 text-center font-medium">Kategori</th>
                                <th
                                    className="cursor-pointer px-4 py-3 text-center font-medium select-none hover:text-foreground"
                                    onClick={toggleSort}
                                    title={sortDir === 'asc' ? 'Urutkan dari terbanyak' : 'Urutkan dari tersedikit'}
                                >
                                    <span className="inline-flex items-center gap-1">
                                        Jumlah Aset
                                        {sortDir === 'asc' ? (
                                            <ArrowUp className="size-3" />
                                        ) : (
                                            <ArrowDown className="size-3" />
                                        )}
                                    </span>
                                </th>
                                {canManage && (
                                    <th className="px-4 py-3 text-center font-medium">Aksi</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={canManage ? 4 : 3}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        {search.trim() !== ''
                                            ? 'Tidak ada kategori yang cocok.'
                                            : 'Belum ada kategori.'}
                                    </td>
                                </tr>
                            ) : (
                                filtered.map((category) => (
                                    <tr
                                        key={category.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        {canManage && (
                                            <td className="px-2 py-3 align-middle">
                                                <div className="flex h-full items-center justify-center">
                                                    <Checkbox
                                                        checked={selected.includes(category.id)}
                                                        onCheckedChange={() => toggleOne(category.id)}
                                                        aria-label={`Select ${category.name}`}
                                                        className="border-muted-foreground/60"
                                                    />
                                                </div>
                                            </td>
                                        )}
                                        <td
                                            className={`px-4 py-3 text-center ${
                                                category.depth === 0
                                                    ? 'font-semibold'
                                                    : 'font-normal text-muted-foreground'
                                            }`}
                                        >
                                            <span
                                                className="inline-flex items-center gap-2"
                                                style={{
                                                    paddingLeft: `${category.depth * 1.5}rem`,
                                                }}
                                            >
                                                {category.depth > 0 && (
                                                    <span>└─</span>
                                                )}
                                                {category.name}
                                                {category.depth === 0 && (
                                                    <Badge variant="outline">
                                                        Root
                                                    </Badge>
                                                )}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant="secondary">
                                                {category.assets_count} aset
                                            </Badge>
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-center">
                                                <div className="flex items-center justify-center gap-2">
                                                    <CreateCategoryDialog
                                                        categories={categories}
                                                        parent={category}
                                                    />
                                                    <EditCategoryDialog category={category} />
                                                    <DeleteCategoryDialog category={category} />
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function CategorySearch({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="category-search"
                name="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        onChange('');
                    }
                }}
                placeholder="Cari kategori..."
                className="pr-9 pl-9"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Clear search"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

function ParentField({
    categories,
    parentId,
    excludeId,
}: {
    categories: CategoryRow[];
    parentId: number | null;
    excludeId?: number;
}) {
    return (
        <Select name="parent_id" defaultValue={parentId === null ? '' : String(parentId)}>
            <SelectTrigger id="parent_id" className="mt-1 w-full">
                <SelectValue placeholder="None (kategori utama)" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="">None (kategori utama)</SelectItem>
                {categories
                    .filter((category) => category.id !== excludeId)
                    .map((category) => (
                        <SelectItem key={category.id} value={String(category.id)}>
                            {'— '.repeat(category.depth)}
                            {category.name}
                        </SelectItem>
                    ))}
            </SelectContent>
        </Select>
    );
}

function CreateCategoryDialog({
    categories,
    parent,
}: {
    categories: CategoryRow[];
    parent?: CategoryRow;
}) {
    const [open, setOpen] = useState(false);
    const isChild = parent !== undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {isChild ? (
                    <Button
                        size="sm"
                        className="bg-green-600 text-white hover:bg-green-700"
                    >
                        <Plus className="size-4" />
                        Sub Kategori
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" />
                        Tambah Kategori
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{isChild ? `Tambah sub-kategori "${parent.name}"` : 'Tambah kategori'}</DialogTitle>
                <DialogDescription>
                    {isChild
                        ? 'Sub-kategori baru akan ditempatkan di bawah kategori ini.'
                        : 'Kategori baru akan menjadi kategori utama (root).'}
                </DialogDescription>

                <Form
                    {...AssetCategoryController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['categories'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="parent_id">Parent kategori</Label>
                                <ParentField
                                    categories={categories}
                                    parentId={isChild ? parent.id : null}
                                />
                                <InputError message={errors.parent_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Nama kategori</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    className="mt-1 block w-full"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
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

function EditCategoryDialog({ category }: { category: CategoryRow }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Rename
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Edit kategori</DialogTitle>
                <DialogDescription>Update the name for {category.name}.</DialogDescription>

                <Form
                    {...AssetCategoryController.update.form(category.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['categories'] });
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${category.id}`}>Nama kategori</Label>
                                <Input
                                    id={`name-${category.id}`}
                                    name="name"
                                    className="mt-1 block w-full"
                                    defaultValue={category.name}
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
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

function BulkDeleteDialog({
    categories,
    selected,
    onDone,
}: {
    categories: CategoryRow[];
    selected: number[];
    onDone: () => void;
}) {
    const [open, setOpen] = useState(false);

    const names = categories
        .filter((category) => selected.includes(category.id))
        .map((category) => category.name);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 className="size-4" />
                    Hapus Terpilih
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete kategori terpilih</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {selected.length} selected categories? Categories with sub-categories or assets will be skipped.
                </DialogDescription>

                <ul className="max-h-48 overflow-y-auto rounded-md border border-sidebar-border/70 text-sm">
                    {names.map((name) => (
                        <li
                            key={name}
                            className="border-b border-sidebar-border/50 px-3 py-2 last:border-b-0"
                        >
                            {name}
                        </li>
                    ))}
                </ul>

                <Form
                    {...AssetCategoryController.bulkDestroy.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        onDone();
                        router.reload({ only: ['categories'] });
                    }}
                >
                    {({ processing }) => (
                        <>
                            {selected.map((id) => (
                                <input key={id} type="hidden" name="ids[]" value={id} />
                            ))}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" variant="destructive" disabled={processing}>
                                    Delete
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteCategoryDialog({ category }: { category: CategoryRow }) {
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
                <DialogTitle>Delete kategori</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {category.name}? Categories with sub-categories or assets cannot be deleted.
                </DialogDescription>

                <Form
                    {...AssetCategoryController.destroy.form(category.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        router.reload({ only: ['categories'] });
                    }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button type="submit" variant="destructive" disabled={processing}>
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AssetCategorylistIndex.layout = (props: {
    menus?: MenuRow[];
    menu?: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, AssetCategoryController.index.url()) ?? [
            { title: 'Category List', href: assetsOverview() },
        ],
});
