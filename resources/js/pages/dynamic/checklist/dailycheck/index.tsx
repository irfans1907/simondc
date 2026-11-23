import { Head, Link, router, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import {
    CalendarDays,
    ChevronDown,
    RotateCcw,
    Save,
    Upload,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { exportMethod } from '@/actions/App/Http/Controllers/ChecklistController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { dailycheck } from '@/routes/checklist';
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

type SlotNote = {
    category_id: number;
    time_slot: string;
    risk_level: RiskLevel;
    note: string | null;
};

type RiskLevel = 'LOW' | 'MEDIUM' | 'HIGH';

type Cell = {
    value: string;
    status: string;
};

const STATUS_OPTIONS = ['OK', 'NG', 'IDLE'] as const;

const RISK_LEVELS: RiskLevel[] = ['LOW', 'MEDIUM', 'HIGH'];

/** Radix Select cannot use an empty string value for the "no status" item. */
const EMPTY_STATUS = 'NONE';

const cellKey = (itemId: number, slot: string) => `${itemId}:${slot}`;

const slotNoteKey = (categoryId: number, slot: string) =>
    `${categoryId}:${slot}`;

function slotNotesFromPayload(
    notes: SlotNote[],
): Record<string, { risk_level: RiskLevel; note: string }> {
    const mapped: Record<string, { risk_level: RiskLevel; note: string }> = {};

    for (const note of notes) {
        mapped[slotNoteKey(note.category_id, note.time_slot)] = {
            risk_level: note.risk_level,
            note: note.note ?? '',
        };
    }

    return mapped;
}

function highestRisk(levels: RiskLevel[]): RiskLevel {
    if (levels.includes('HIGH')) {
        return 'HIGH';
    }

    if (levels.includes('MEDIUM')) {
        return 'MEDIUM';
    }

    return 'LOW';
}

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
    slotNotes,
    canManage,
}: {
    date: string;
    today: string;
    selectedCategoryId: number;
    categories: ChecklistCategory[];
    entries: ChecklistEntryRow[];
    dayNotes: DayNote[];
    slotNotes: SlotNote[];
    canManage: boolean;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const [activeId, setActiveId] = useState(selectedCategoryId);
    const [cells, setCells] = useState<Record<string, Cell>>(() =>
        cellsFromEntries(entries),
    );
    const [notes, setNotes] = useState(() => notesFromDayNotes(dayNotes));
    const [risks, setRisks] = useState(() => slotNotesFromPayload(slotNotes));
    const [saving, setSaving] = useState(false);
    const [dateOpen, setDateOpen] = useState(false);

    const activeCategory =
        categories.find((category) => category.id === activeId) ??
        categories[0];
    const activeNote = notes[activeCategory?.id ?? 0] ?? {
        risk_level: 'LOW',
        note: '',
    };

    const updateSlotRisk = (slot: string, riskLevel: RiskLevel) => {
        if (!activeCategory) {
            return;
        }

        setRisks((current) => ({
            ...current,
            [slotNoteKey(activeCategory.id, slot)]: {
                ...(current[slotNoteKey(activeCategory.id, slot)] ?? {
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

    const handleExport = () => {
        window.location.href = exportMethod.url({
            query: { month: date.slice(0, 7) },
        });
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

        const payloadSlotNotes = activeCategory.time_slots.map((slot) => {
            const note = risks[slotNoteKey(activeCategory.id, slot)] ?? {
                risk_level: 'LOW' as RiskLevel,
                note: '',
            };

            return {
                time_slot: slot,
                risk_level: note.risk_level,
                note: note.note.trim() === '' ? null : note.note,
            };
        });

        // Keep the category/day risk in sync with the worst slot so the
        // overview and export still have a summary value.
        const dayRisk =
            payloadSlotNotes.length > 0
                ? highestRisk(payloadSlotNotes.map((note) => note.risk_level))
                : activeNote.risk_level;

        setSaving(true);

        router.post(
            storeEntries.url(),
            {
                category_id: activeCategory.id,
                entry_date: date,
                risk_level: dayRisk,
                note: activeNote.note.trim() === '' ? null : activeNote.note,
                entries: payloadEntries,
                slot_notes: payloadSlotNotes,
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
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Daily Check"
                        description="Catat pembacaan infrastruktur per jam untuk tanggal yang dipilih."
                    />
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-2">
                            <Label htmlFor="checklist-date" className="sr-only">
                                Tanggal
                            </Label>
                            <Popover open={dateOpen} onOpenChange={setDateOpen}>
                                <PopoverTrigger asChild>
                                    <Button
                                        id="checklist-date"
                                        variant="outline"
                                        className="w-[190px] justify-start"
                                    >
                                        <CalendarDays className="size-4" />
                                        {format(parseISO(date), 'dd/MM/yyyy')}
                                        <ChevronDown className="ml-auto size-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto overflow-hidden p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={parseISO(date)}
                                        defaultMonth={parseISO(date)}
                                        onSelect={(selected) => {
                                            if (selected) {
                                                changeDate(
                                                    format(
                                                        selected,
                                                        'yyyy-MM-dd',
                                                    ),
                                                );
                                                setDateOpen(false);
                                            }
                                        }}
                                    />
                                    <div className="px-3 pb-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            onClick={() => {
                                                changeDate(today);
                                                setDateOpen(false);
                                            }}
                                        >
                                            <CalendarDays className="size-4" />
                                            Hari Ini
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                        <Button
                            type="button"
                            variant="default"
                            className="bg-green-600 text-white hover:bg-green-700"
                            onClick={handleExport}
                        >
                            <Upload className="size-4" />
                            Export
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
                                        <th className="item-center min-w-56 px-4 py-3 text-center font-medium">
                                            Item
                                        </th>
                                        <th className="item-center w-20 px-3 py-3 text-center font-medium">
                                            Satuan
                                        </th>
                                        {activeCategory.time_slots.map(
                                            (slot) => (
                                                <th
                                                    key={slot}
                                                    className="item-center min-w-24 px-3 py-3 text-center font-medium"
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
                                                    <td className="px-4 py-2 text-left font-semibold tracking-wide text-muted-foreground uppercase">
                                                        {section.section}
                                                    </td>
                                                    <td
                                                        colSpan={
                                                            activeCategory
                                                                .time_slots
                                                                .length + 2
                                                        }
                                                    />
                                                </tr>
                                            )}
                                            {section.groups.map((group) => (
                                                <Fragment
                                                    key={`group-${section.section ?? 'root'}-${group.group ?? 'root'}`}
                                                >
                                                    {group.group && (
                                                        <tr className="border-b border-sidebar-border/50 bg-muted/20">
                                                            <td className="px-4 py-2 text-left text-xs font-medium tracking-wide text-muted-foreground">
                                                                {group.group}
                                                            </td>
                                                            <td
                                                                colSpan={
                                                                    activeCategory
                                                                        .time_slots
                                                                        .length +
                                                                    1
                                                                }
                                                            />
                                                        </tr>
                                                    )}
                                                    {group.items.map((item) => (
                                                        <tr
                                                            key={item.id}
                                                            className="border-b border-sidebar-border/50 last:border-b-0"
                                                        >
                                                            <td className="item-center px-4 py-2 text-center">
                                                                {item.label}
                                                            </td>
                                                            <td className="item-center px-3 py-2 text-center text-muted-foreground">
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
                                                                            className="item-center px-2 py-2 text-center"
                                                                        >
                                                                            {item.input_type ===
                                                                            'status' ? (
                                                                                <Select
                                                                                    value={
                                                                                        cell.status ===
                                                                                        ''
                                                                                            ? EMPTY_STATUS
                                                                                            : cell.status
                                                                                    }
                                                                                    onValueChange={(
                                                                                        value,
                                                                                    ) =>
                                                                                        updateCell(
                                                                                            item.id,
                                                                                            slot,
                                                                                            'status',
                                                                                            value ===
                                                                                                EMPTY_STATUS
                                                                                                ? ''
                                                                                                : value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        !canManage
                                                                                    }
                                                                                >
                                                                                    <SelectTrigger
                                                                                        aria-label={`${item.label} ${slot}`}
                                                                                        size="sm"
                                                                                        className="mx-auto w-24"
                                                                                    >
                                                                                        <SelectValue />
                                                                                    </SelectTrigger>
                                                                                    <SelectContent>
                                                                                        <SelectItem
                                                                                            value={
                                                                                                EMPTY_STATUS
                                                                                            }
                                                                                        >
                                                                                            -
                                                                                        </SelectItem>
                                                                                        {STATUS_OPTIONS.map(
                                                                                            (
                                                                                                status,
                                                                                            ) => (
                                                                                                <SelectItem
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
                                                                                                </SelectItem>
                                                                                            ),
                                                                                        )}
                                                                                    </SelectContent>
                                                                                </Select>
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
                                                                                    className="mx-auto h-8 w-24 text-center"
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

                                    <tr className="border-t border-sidebar-border/70 bg-muted/30">
                                        <td className="px-4 py-2 text-center text-sm font-medium">
                                            Risk Level
                                        </td>
                                        <td className="px-3 py-2 text-center text-muted-foreground">
                                            -
                                        </td>
                                        {activeCategory.time_slots.map(
                                            (slot) => {
                                                const note = risks[
                                                    slotNoteKey(
                                                        activeCategory.id,
                                                        slot,
                                                    )
                                                ] ?? {
                                                    risk_level:
                                                        'LOW' as RiskLevel,
                                                    note: '',
                                                };

                                                return (
                                                    <td
                                                        key={`risk-${slot}`}
                                                        className="px-2 py-2 text-center"
                                                    >
                                                        <Select
                                                            value={
                                                                note.risk_level
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateSlotRisk(
                                                                    slot,
                                                                    value as RiskLevel,
                                                                )
                                                            }
                                                            disabled={
                                                                !canManage
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                aria-label={`Risk Level ${slot}`}
                                                                size="sm"
                                                                className="mx-auto w-24"
                                                            >
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {RISK_LEVELS.map(
                                                                    (level) => (
                                                                        <SelectItem
                                                                            key={
                                                                                level
                                                                            }
                                                                            value={
                                                                                level
                                                                            }
                                                                        >
                                                                            {
                                                                                level
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </td>
                                                );
                                            },
                                        )}
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
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
