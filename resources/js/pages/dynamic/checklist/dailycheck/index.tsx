import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ClipboardCheck, RotateCcw, Save } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { dailycheck, monthlycheck } from '@/routes/checklist';
import { store as storeEntries } from '@/routes/checklist/entries';

type InputType = 'number' | 'status';

type ChecklistItem = {
    id: number;
    section: string | null;
    group: string | null;
    label: string;
    unit: string | null;
    input_type: InputType;
    min_value: number | null;
    max_value: number | null;
};

type ChecklistCategory = {
    id: number;
    slug: string;
    name: string;
    time_slots: string[];
    items: ChecklistItem[];
};

type ChecklistEntryRow = {
    id: number;
    item_id: number;
    time_slot: string;
    value: string | null;
    status: string | null;
    note: string | null;
    source: string;
    user_name: string | null;
};

type DayNote = {
    category_id: number;
    risk_level: 'LOW' | 'MEDIUM' | 'HIGH';
    note: string | null;
};

type Cell = {
    value: string;
    status: string;
};

const STATUS_OPTIONS = ['OK', 'NG', 'IDLE'] as const;

const cellKey = (itemId: number, slot: string) => `${itemId}:${slot}`;

function cellsFromEntries(entries: ChecklistEntryRow[]): Record<string, Cell> {
    const cells: Record<string, Cell> = {};

    for (const entry of entries) {
        cells[cellKey(entry.item_id, entry.time_slot)] = {
            value: displayValue(entry.value),
            status: entry.status ?? '',
        };
    }

    return cells;
}

function notesFromDayNotes(
    dayNotes: DayNote[],
): Record<number, { risk_level: string; note: string }> {
    const notes: Record<number, { risk_level: string; note: string }> = {};

    for (const note of dayNotes) {
        notes[note.category_id] = {
            risk_level: note.risk_level,
            note: note.note ?? '',
        };
    }

    return notes;
}

function displayValue(value: string | null): string {
    if (value === null) {
        return '';
    }

    const numeric = Number(value);

    return Number.isNaN(numeric) ? value : String(numeric);
}

function groupItems(items: ChecklistItem[]) {
    const sections: {
        section: string | null;
        groups: { group: string | null; items: ChecklistItem[] }[];
    }[] = [];

    for (const item of items) {
        let section = sections.find((entry) => entry.section === item.section);

        if (!section) {
            section = { section: item.section, groups: [] };
            sections.push(section);
        }

        let group = section.groups.find((entry) => entry.group === item.group);

        if (!group) {
            group = { group: item.group, items: [] };
            section.groups.push(group);
        }

        group.items.push(item);
    }

    return sections;
}

function CategoryTabs({
    categories,
    activeId,
    progress,
    onSelect,
}: {
    categories: ChecklistCategory[];
    activeId: number;
    progress: Record<number, { filled: number; total: number }>;
    onSelect: (id: number) => void;
}) {
    return (
        <div className="flex gap-2 overflow-x-auto pb-1">
            {categories.map((category) => {
                const stats = progress[category.id] ?? { filled: 0, total: 0 };
                const active = category.id === activeId;

                return (
                    <button
                        key={category.id}
                        type="button"
                        onClick={() => onSelect(category.id)}
                        className={`flex min-w-fit items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors ${
                            active
                                ? 'border-primary bg-primary/10 font-medium text-foreground'
                                : 'border-sidebar-border/70 text-muted-foreground hover:bg-accent'
                        }`}
                    >
                        <span className="whitespace-nowrap">
                            {category.name}
                        </span>
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs ${
                                stats.filled === stats.total && stats.total > 0
                                    ? 'bg-emerald-600/15 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {stats.filled}/{stats.total}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

export default function ChecklistDailycheck({
    date,
    today,
    selectedCategoryId,
    categories,
    entries,
    dayNotes,
    canManage,
}: {
    date: string;
    today: string;
    selectedCategoryId: number;
    categories: ChecklistCategory[];
    entries: ChecklistEntryRow[];
    dayNotes: DayNote[];
    canManage: boolean;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const [activeId, setActiveId] = useState(selectedCategoryId);
    const [cells, setCells] = useState<Record<string, Cell>>(() =>
        cellsFromEntries(entries),
    );
    const [notes, setNotes] = useState(() => notesFromDayNotes(dayNotes));
    const [saving, setSaving] = useState(false);

    const activeCategory =
        categories.find((category) => category.id === activeId) ??
        categories[0];
    const activeNote = notes[activeCategory?.id ?? 0] ?? {
        risk_level: 'LOW',
        note: '',
    };

    const updateRiskLevel = (riskLevel: string) => {
        if (!activeCategory) {
            return;
        }

        setNotes((current) => ({
            ...current,
            [activeCategory.id]: {
                ...(current[activeCategory.id] ?? {
                    risk_level: 'LOW',
                    note: '',
                }),
                risk_level: riskLevel,
            },
        }));
    };

    const updateNote = (note: string) => {
        if (!activeCategory) {
            return;
        }

        setNotes((current) => ({
            ...current,
            [activeCategory.id]: {
                ...(current[activeCategory.id] ?? {
                    risk_level: 'LOW',
                    note: '',
                }),
                note,
            },
        }));
    };

    const progress = useMemo(() => {
        const itemCategory = new Map<number, number>();

        for (const category of categories) {
            for (const item of category.items) {
                itemCategory.set(item.id, category.id);
            }
        }

        const counts: Record<number, { filled: number; total: number }> = {};

        for (const category of categories) {
            counts[category.id] = {
                filled: 0,
                total: category.items.length * category.time_slots.length,
            };
        }

        for (const entry of entries) {
            const categoryId = itemCategory.get(entry.item_id);

            if (categoryId !== undefined) {
                counts[categoryId].filled += 1;
            }
        }

        return counts;
    }, [categories, entries]);

    const sections = useMemo(
        () => (activeCategory ? groupItems(activeCategory.items) : []),
        [activeCategory],
    );

    const lastFilledBy = useMemo(() => {
        const itemIds = new Set(
            activeCategory?.items.map((item) => item.id) ?? [],
        );

        return [...entries]
            .reverse()
            .find((entry) => itemIds.has(entry.item_id) && entry.user_name)
            ?.user_name;
    }, [activeCategory, entries]);

    const changeDate = (nextDate: string) => {
        if (!nextDate) {
            return;
        }

        router.get(
            dailycheck.url({ query: { date: nextDate, category: activeId } }),
            {},
            { preserveScroll: true },
        );
    };

    const updateCell = (
        itemId: number,
        slot: string,
        field: keyof Cell,
        value: string,
    ) => {
        setCells((current) => {
            const key = cellKey(itemId, slot);
            const previous = current[key] ?? { value: '', status: '' };

            return {
                ...current,
                [key]: { ...previous, [field]: value },
            };
        });
    };

    const save = () => {
        if (!activeCategory) {
            return;
        }

        const payloadEntries = activeCategory.items.flatMap((item) =>
            activeCategory.time_slots.map((slot) => {
                const cell = cells[cellKey(item.id, slot)] ?? {
                    value: '',
                    status: '',
                };

                return {
                    item_id: item.id,
                    time_slot: slot,
                    value: cell.value === '' ? null : cell.value,
                    status: cell.status === '' ? null : cell.status,
                    note: null,
                };
            }),
        );

        setSaving(true);

        router.post(
            storeEntries.url(),
            {
                category_id: activeCategory.id,
                entry_date: date,
                risk_level: activeNote.risk_level,
                note: activeNote.note.trim() === '' ? null : activeNote.note,
                entries: payloadEntries,
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const errorMessages = Object.values(errors ?? {});

    return (
        <>
            <Head title="Daily Check" />
            <h1 className="sr-only">Daily Check</h1>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Daily Check"
                        description="Catat pembacaan infrastruktur per jam untuk tanggal yang dipilih."
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-2">
                            <Label htmlFor="checklist-date" className="sr-only">
                                Tanggal
                            </Label>
                            <Input
                                id="checklist-date"
                                type="date"
                                value={date}
                                onChange={(event) =>
                                    changeDate(event.target.value)
                                }
                                className="w-40"
                            />
                        </div>
                        <Button variant="outline" asChild>
                            <Link
                                href={dailycheck.url({
                                    query: { date: today },
                                })}
                            >
                                <CalendarDays className="size-4" />
                                Hari Ini
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={monthlycheck.url()}>
                                <ClipboardCheck className="size-4" />
                                Rekap Bulanan
                            </Link>
                        </Button>
                    </div>
                </div>

                {errorMessages.length > 0 && (
                    <div className="rounded-lg border border-red-600/40 bg-red-600/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                        <p className="font-medium">Data belum tersimpan:</p>
                        <ul className="mt-1 list-inside list-disc">
                            {errorMessages.slice(0, 5).map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <CategoryTabs
                    categories={categories}
                    activeId={activeCategory?.id ?? 0}
                    progress={progress}
                    onSelect={setActiveId}
                />

                {activeCategory && (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
                            <span>
                                {activeCategory.items.length} item &middot;{' '}
                                {activeCategory.time_slots.length} slot waktu
                            </span>
                            <span>
                                {lastFilledBy
                                    ? `Terakhir diisi oleh ${lastFilledBy}`
                                    : 'Belum ada data pada tanggal ini'}
                            </span>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                        <th className="min-w-56 px-4 py-3 font-medium">
                                            Item
                                        </th>
                                        <th className="w-20 px-3 py-3 text-center font-medium">
                                            Satuan
                                        </th>
                                        {activeCategory.time_slots.map(
                                            (slot) => (
                                                <th
                                                    key={slot}
                                                    className="min-w-24 px-3 py-3 text-center font-medium"
                                                >
                                                    {slot}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {sections.map((section) => (
                                        <Fragment
                                            key={`section-${section.section ?? 'root'}`}
                                        >
                                            {section.section && (
                                                <tr className="border-b border-sidebar-border/70 bg-muted/50">
                                                    <td
                                                        colSpan={
                                                            activeCategory
                                                                .time_slots
                                                                .length + 2
                                                        }
                                                        className="px-4 py-2 font-semibold"
                                                    >
                                                        {section.section}
                                                    </td>
                                                </tr>
                                            )}
                                            {section.groups.map((group) => (
                                                <Fragment
                                                    key={`group-${section.section ?? 'root'}-${group.group ?? 'root'}`}
                                                >
                                                    {group.group && (
                                                        <tr className="border-b border-sidebar-border/50 bg-muted/20">
                                                            <td
                                                                colSpan={
                                                                    activeCategory
                                                                        .time_slots
                                                                        .length +
                                                                    2
                                                                }
                                                                className="px-4 py-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                                            >
                                                                {group.group}
                                                            </td>
                                                        </tr>
                                                    )}
                                                    {group.items.map((item) => (
                                                        <tr
                                                            key={item.id}
                                                            className="border-b border-sidebar-border/50 last:border-b-0"
                                                        >
                                                            <td className="px-4 py-2">
                                                                {item.label}
                                                            </td>
                                                            <td className="px-3 py-2 text-center text-muted-foreground">
                                                                {item.unit ??
                                                                    '-'}
                                                            </td>
                                                            {activeCategory.time_slots.map(
                                                                (slot) => {
                                                                    const cell =
                                                                        cells[
                                                                            cellKey(
                                                                                item.id,
                                                                                slot,
                                                                            )
                                                                        ] ?? {
                                                                            value: '',
                                                                            status: '',
                                                                        };

                                                                    return (
                                                                        <td
                                                                            key={
                                                                                slot
                                                                            }
                                                                            className="px-2 py-2 text-center"
                                                                        >
                                                                            {item.input_type ===
                                                                            'status' ? (
                                                                                <select
                                                                                    aria-label={`${item.label} ${slot}`}
                                                                                    value={
                                                                                        cell.status
                                                                                    }
                                                                                    onChange={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateCell(
                                                                                            item.id,
                                                                                            slot,
                                                                                            'status',
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        !canManage
                                                                                    }
                                                                                    className="h-8 w-24 rounded-md border border-input bg-transparent px-1 text-center text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                                                                >
                                                                                    <option value="">
                                                                                        -
                                                                                    </option>
                                                                                    {STATUS_OPTIONS.map(
                                                                                        (
                                                                                            status,
                                                                                        ) => (
                                                                                            <option
                                                                                                key={
                                                                                                    status
                                                                                                }
                                                                                                value={
                                                                                                    status
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    status
                                                                                                }
                                                                                            </option>
                                                                                        ),
                                                                                    )}
                                                                                </select>
                                                                            ) : (
                                                                                <Input
                                                                                    type="number"
                                                                                    step="any"
                                                                                    aria-label={`${item.label} ${slot}`}
                                                                                    value={
                                                                                        cell.value
                                                                                    }
                                                                                    onChange={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateCell(
                                                                                            item.id,
                                                                                            slot,
                                                                                            'value',
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        !canManage
                                                                                    }
                                                                                    className="h-8 w-24 text-center"
                                                                                />
                                                                            )}
                                                                        </td>
                                                                    );
                                                                },
                                                            )}
                                                        </tr>
                                                    ))}
                                                </Fragment>
                                            ))}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 lg:grid-cols-[minmax(0,1fr)_240px_auto] lg:items-end">
                            <div className="grid gap-2">
                                <Label htmlFor="checklist-note">
                                    Catatan {activeCategory.name}
                                </Label>
                                <textarea
                                    id="checklist-note"
                                    value={activeNote.note}
                                    onChange={(event) =>
                                        updateNote(event.target.value)
                                    }
                                    disabled={!canManage}
                                    placeholder="Kondisi khusus, tindak lanjut, atau kendala pada tanggal ini"
                                    className="mt-1 flex h-24 w-full min-w-0 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError message={errors?.note} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="checklist-risk">
                                    Risk Level
                                </Label>
                                <Select
                                    value={activeNote.risk_level}
                                    onValueChange={updateRiskLevel}
                                    disabled={!canManage}
                                >
                                    <SelectTrigger id="checklist-risk">
                                        <SelectValue placeholder="Pilih risk level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="LOW">LOW</SelectItem>
                                        <SelectItem value="MEDIUM">
                                            MEDIUM
                                        </SelectItem>
                                        <SelectItem value="HIGH">
                                            HIGH
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors?.risk_level} />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link
                                        href={dailycheck.url({
                                            query: {
                                                date,
                                                category: activeCategory.id,
                                            },
                                        })}
                                    >
                                        <RotateCcw className="size-4" />
                                        Muat Ulang
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    onClick={save}
                                    disabled={!canManage || saving}
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan'}
                                </Button>
                            </div>
                        </div>
                    </>
                )}

                {!activeCategory && (
                    <div className="rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center text-muted-foreground">
                        Belum ada kategori checklist. Jalankan seeder checklist
                        terlebih dahulu.
                    </div>
                )}
            </div>
        </>
    );
}

ChecklistDailycheck.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs: showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/checklist/dailycheck') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
