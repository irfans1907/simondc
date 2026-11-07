import { Head, Link, router } from '@inertiajs/react';
import { CalendarRange, Download, RotateCcw } from 'lucide-react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { dailycheck, exportMethod, monthlycheck } from '@/routes/checklist';

type ChecklistItem = {
    id: number;
    section: string | null;
    group: string | null;
    label: string;
    unit: string | null;
    input_type: 'number' | 'status';
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

type CategoryOption = {
    id: number;
    slug: string;
    name: string;
};

type MonthlyEntry = {
    item_id: number;
    date: string;
    time_slot: string;
    value: string | null;
    status: string | null;
    note: string | null;
};

type DayNote = {
    date: string;
    risk_level: 'LOW' | 'MEDIUM' | 'HIGH';
    note: string | null;
};

const riskClass: Record<string, string> = {
    LOW: 'bg-emerald-500',
    MEDIUM: 'bg-amber-500',
    HIGH: 'bg-red-500',
};

const riskBadgeClass: Record<string, string> = {
    LOW: 'border-emerald-600/40 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    MEDIUM: 'border-amber-600/40 bg-amber-600/10 text-amber-700 dark:text-amber-400',
    HIGH: 'border-red-600/40 bg-red-600/10 text-red-700 dark:text-red-400',
};

const pad = (value: number) => String(value).padStart(2, '0');

function formatNumber(value: number): string {
    return Number.isInteger(value)
        ? String(value)
        : value.toFixed(2).replace(/\.?0+$/, '');
}

function monthLabel(month: string): string {
    const parsed = new Date(`${month}-01T00:00:00`);

    return parsed.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
}

function statusSummary(rows: MonthlyEntry[]): string {
    const counts: Record<string, number> = {};

    for (const row of rows) {
        if (row.status) {
            counts[row.status] = (counts[row.status] ?? 0) + 1;
        }
    }

    return ['OK', 'NG', 'IDLE']
        .filter((status) => (counts[status] ?? 0) > 0)
        .map((status) => `${status} ${counts[status]}`)
        .join(' / ');
}

function cellTitle(rows: MonthlyEntry[]): string {
    return rows
        .map((row) => `${row.time_slot}: ${row.value ?? row.status ?? '-'}`)
        .join('\n');
}

export default function ChecklistMonthlycheck({
    month,
    daysInMonth,
    categories,
    category,
    entries,
    dayNotes,
    canManage,
}: {
    month: string;
    daysInMonth: number;
    categories: CategoryOption[];
    category: ChecklistCategory | null;
    entries: MonthlyEntry[];
    dayNotes: DayNote[];
    canManage: boolean;
}) {
    const days = useMemo(
        () => Array.from({ length: daysInMonth }, (_, index) => index + 1),
        [daysInMonth],
    );

    const entriesByItemDate = useMemo(() => {
        const map = new Map<string, MonthlyEntry[]>();

        for (const entry of entries) {
            const key = `${entry.item_id}:${entry.date}`;
            const rows = map.get(key) ?? [];
            rows.push(entry);
            map.set(key, rows);
        }

        return map;
    }, [entries]);

    const entriesByItem = useMemo(() => {
        const map = new Map<number, MonthlyEntry[]>();

        for (const entry of entries) {
            const rows = map.get(entry.item_id) ?? [];
            rows.push(entry);
            map.set(entry.item_id, rows);
        }

        return map;
    }, [entries]);

    const notesByDate = useMemo(() => {
        const map = new Map<string, DayNote>();

        for (const note of dayNotes) {
            map.set(note.date, note);
        }

        return map;
    }, [dayNotes]);

    const changeMonth = (nextMonth: string) => {
        if (!nextMonth) {
            return;
        }

        router.get(
            monthlycheck.url({
                query: { month: nextMonth, category: category?.id },
            }),
            {},
            { preserveScroll: true },
        );
    };

    const selectCategory = (categoryId: number) => {
        router.get(
            monthlycheck.url({ query: { month, category: categoryId } }),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Monthly Check" />
            <h1 className="sr-only">Monthly Check</h1>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Monthly Check"
                        description="Rekap pembacaan infrastruktur per hari dalam satu bulan."
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-2">
                            <Label
                                htmlFor="checklist-month"
                                className="sr-only"
                            >
                                Bulan
                            </Label>
                            <Input
                                id="checklist-month"
                                type="month"
                                value={month}
                                onChange={(event) =>
                                    changeMonth(event.target.value)
                                }
                                className="w-44"
                            />
                        </div>
                        <Button variant="outline" asChild>
                            <Link
                                href={monthlycheck.url({
                                    query: { month, category: category?.id },
                                })}
                            >
                                <RotateCcw className="size-4" />
                                Muat Ulang
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={dailycheck.url()}>
                                <CalendarRange className="size-4" />
                                Daily Check
                            </Link>
                        </Button>
                        {canManage && category && (
                            <Button asChild>
                                <a
                                    href={exportMethod.url({
                                        query: {
                                            month,
                                            category: category.id,
                                        },
                                    })}
                                >
                                    <Download className="size-4" />
                                    Export CSV
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex gap-2 overflow-x-auto pb-1">
                    {categories.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            onClick={() => selectCategory(option.id)}
                            className={`min-w-fit rounded-lg border px-3 py-2 text-sm transition-colors ${
                                option.id === category?.id
                                    ? 'border-primary bg-primary/10 font-medium text-foreground'
                                    : 'border-sidebar-border/70 text-muted-foreground hover:bg-accent'
                            }`}
                        >
                            {option.name}
                        </button>
                    ))}
                </div>

                {category ? (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
                            <span>
                                {category.name} &middot; {monthLabel(month)}{' '}
                                &middot; {category.items.length} item
                            </span>
                            <span>
                                Klik sel untuk membuka Daily Check tanggal
                                tersebut.
                            </span>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                        <th className="sticky left-0 z-10 min-w-56 border-r border-sidebar-border/50 bg-background px-4 py-3 font-medium">
                                            Item
                                        </th>
                                        <th className="w-16 border-r border-sidebar-border/50 bg-background px-2 py-3 text-center font-medium">
                                            Satuan
                                        </th>
                                        {days.map((day) => {
                                            const date = `${month}-${pad(day)}`;
                                            const note = notesByDate.get(date);

                                            return (
                                                <th
                                                    key={day}
                                                    title={
                                                        note?.note ??
                                                        note?.risk_level ??
                                                        date
                                                    }
                                                    className="min-w-14 px-1 py-3 text-center font-medium"
                                                >
                                                    <span className="flex flex-col items-center gap-1">
                                                        <span>{day}</span>
                                                        {note && (
                                                            <span
                                                                className={`size-1.5 rounded-full ${riskClass[note.risk_level] ?? 'bg-muted'}`}
                                                            />
                                                        )}
                                                    </span>
                                                </th>
                                            );
                                        })}
                                        <th className="min-w-24 border-l border-sidebar-border/50 px-2 py-3 text-center font-medium">
                                            Rata / Rekap
                                        </th>
                                        <th className="min-w-16 px-2 py-3 text-center font-medium">
                                            Min
                                        </th>
                                        <th className="min-w-16 px-2 py-3 text-center font-medium">
                                            Max
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {category.items.map((item) => {
                                        const itemEntries =
                                            entriesByItem.get(item.id) ?? [];

                                        const monthValues = itemEntries
                                            .filter(
                                                (entry) => entry.value !== null,
                                            )
                                            .map((entry) => Number(entry.value))
                                            .filter(
                                                (value) => !Number.isNaN(value),
                                            );

                                        const monthStatuses = itemEntries;

                                        return (
                                            <tr
                                                key={item.id}
                                                className="border-b border-sidebar-border/50 last:border-b-0"
                                            >
                                                <td className="sticky left-0 z-10 border-r border-sidebar-border/50 bg-background px-4 py-2">
                                                    <div className="font-medium">
                                                        {item.label}
                                                    </div>
                                                    {(item.section ||
                                                        item.group) && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {[
                                                                item.section,
                                                                item.group,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' / ')}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="border-r border-sidebar-border/50 px-2 py-2 text-center text-muted-foreground">
                                                    {item.unit ?? '-'}
                                                </td>
                                                {days.map((day) => {
                                                    const date = `${month}-${pad(day)}`;
                                                    const rows =
                                                        entriesByItemDate.get(
                                                            `${item.id}:${date}`,
                                                        ) ?? [];

                                                    if (rows.length === 0) {
                                                        return (
                                                            <td
                                                                key={day}
                                                                className="px-1 py-2 text-center text-muted-foreground"
                                                            >
                                                                -
                                                            </td>
                                                        );
                                                    }

                                                    const numericValues = rows
                                                        .filter(
                                                            (row) =>
                                                                row.value !==
                                                                null,
                                                        )
                                                        .map((row) =>
                                                            Number(row.value),
                                                        )
                                                        .filter(
                                                            (value) =>
                                                                !Number.isNaN(
                                                                    value,
                                                                ),
                                                        );

                                                    const summary =
                                                        item.input_type ===
                                                        'status'
                                                            ? statusSummary(
                                                                  rows,
                                                              )
                                                            : numericValues.length >
                                                                0
                                                              ? formatNumber(
                                                                    numericValues.reduce(
                                                                        (
                                                                            total,
                                                                            value,
                                                                        ) =>
                                                                            total +
                                                                            value,
                                                                        0,
                                                                    ) /
                                                                        numericValues.length,
                                                                )
                                                              : '-';

                                                    const hasNg = rows.some(
                                                        (row) =>
                                                            row.status === 'NG',
                                                    );

                                                    return (
                                                        <td
                                                            key={day}
                                                            title={cellTitle(
                                                                rows,
                                                            )}
                                                            className={`px-1 py-2 text-center ${
                                                                hasNg
                                                                    ? 'bg-red-600/10 text-red-700 dark:text-red-400'
                                                                    : ''
                                                            }`}
                                                        >
                                                            <Link
                                                                href={dailycheck.url(
                                                                    {
                                                                        query: {
                                                                            date,
                                                                            category:
                                                                                category.id,
                                                                        },
                                                                    },
                                                                )}
                                                                className="block rounded px-1 py-0.5 hover:bg-accent"
                                                            >
                                                                {summary}
                                                            </Link>
                                                        </td>
                                                    );
                                                })}
                                                {item.input_type ===
                                                'status' ? (
                                                    <>
                                                        <td className="border-l border-sidebar-border/50 px-2 py-2 text-center text-xs">
                                                            {monthStatuses.length >
                                                            0
                                                                ? statusSummary(
                                                                      monthStatuses,
                                                                  )
                                                                : '-'}
                                                        </td>
                                                        <td className="px-2 py-2 text-center text-muted-foreground">
                                                            -
                                                        </td>
                                                        <td className="px-2 py-2 text-center text-muted-foreground">
                                                            -
                                                        </td>
                                                    </>
                                                ) : (
                                                    <>
                                                        <td className="border-l border-sidebar-border/50 px-2 py-2 text-center">
                                                            {monthValues.length >
                                                            0
                                                                ? formatNumber(
                                                                      monthValues.reduce(
                                                                          (
                                                                              total,
                                                                              value,
                                                                          ) =>
                                                                              total +
                                                                              value,
                                                                          0,
                                                                      ) /
                                                                          monthValues.length,
                                                                  )
                                                                : '-'}
                                                        </td>
                                                        <td className="px-2 py-2 text-center">
                                                            {monthValues.length >
                                                            0
                                                                ? formatNumber(
                                                                      Math.min(
                                                                          ...monthValues,
                                                                      ),
                                                                  )
                                                                : '-'}
                                                        </td>
                                                        <td className="px-2 py-2 text-center">
                                                            {monthValues.length >
                                                            0
                                                                ? formatNumber(
                                                                      Math.max(
                                                                          ...monthValues,
                                                                      ),
                                                                  )
                                                                : '-'}
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {entries.length === 0 && (
                            <div className="rounded-xl border border-dashed border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground">
                                Belum ada data untuk {category.name} pada{' '}
                                {monthLabel(month)}.
                            </div>
                        )}

                        {dayNotes.length > 0 && (
                            <div className="rounded-xl border border-sidebar-border/70 p-4">
                                <h3 className="mb-3 text-sm font-semibold">
                                    Catatan Harian
                                </h3>
                                <ul className="grid gap-2 text-sm">
                                    {dayNotes.map((note) => (
                                        <li
                                            key={note.date}
                                            className="flex flex-wrap items-start gap-2"
                                        >
                                            <span className="font-medium">
                                                {note.date}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    riskBadgeClass[
                                                        note.risk_level
                                                    ]
                                                }
                                            >
                                                {note.risk_level}
                                            </Badge>
                                            <span className="text-muted-foreground">
                                                {note.note || '-'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center text-muted-foreground">
                        Belum ada kategori checklist. Jalankan seeder checklist
                        terlebih dahulu.
                    </div>
                )}
            </div>
        </>
    );
}

ChecklistMonthlycheck.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs: showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/checklist/monthlycheck') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
