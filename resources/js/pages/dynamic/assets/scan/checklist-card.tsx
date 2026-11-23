import { Link, router } from '@inertiajs/react';
import { ExternalLink, Save } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type ChecklistEntry = {
    item_id: number;
    time_slot: string;
    value: number | null;
    status: string | null;
};

type RiskLevel = 'LOW' | 'MEDIUM' | 'HIGH';

type SlotNote = {
    time_slot: string;
    risk_level: RiskLevel;
    note: string | null;
};

export type ScanChecklist = {
    date: string;
    category: {
        id: number | null;
        name: string | null;
        time_slots: string[];
    };
    items: ChecklistItem[];
    entries: ChecklistEntry[];
    slotNotes: SlotNote[];
    dayNote: {
        risk_level: string;
        note: string | null;
    };
};

type Cell = {
    value: string;
    status: string;
};

const STATUS_OPTIONS = ['OK', 'NG', 'IDLE'] as const;

const RISK_LEVELS: RiskLevel[] = ['LOW', 'MEDIUM', 'HIGH'];

/** Radix Select cannot use an empty string value for the "no status" item. */
const EMPTY_STATUS = 'NONE';

function highestRisk(levels: RiskLevel[]): RiskLevel {
    if (levels.includes('HIGH')) {
        return 'HIGH';
    }

    if (levels.includes('MEDIUM')) {
        return 'MEDIUM';
    }

    return 'LOW';
}

const cellKey = (itemId: number, slot: string) => `${itemId}:${slot}`;

function displayValue(value: number | null): string {
    return value === null ? '' : String(value);
}

function cellsFromEntries(entries: ChecklistEntry[]): Record<string, Cell> {
    const cells: Record<string, Cell> = {};

    for (const entry of entries) {
        cells[cellKey(entry.item_id, entry.time_slot)] = {
            value: displayValue(entry.value),
            status: entry.status ?? '',
        };
    }

    return cells;
}

/**
 * The real checklist configured for the scanned asset, laid out like the
 * Daily Check grid. Only the cells the user edits are posted, so untouched
 * entries of the other time slots stay as they are.
 */
export default function ChecklistCard({
    checklist,
    canManage,
}: {
    checklist: ScanChecklist | null;
    canManage: boolean;
}) {
    const [cells, setCells] = useState<Record<string, Cell>>(() =>
        checklist ? cellsFromEntries(checklist.entries) : {},
    );
    const [risks, setRisks] = useState<
        Record<string, { risk_level: RiskLevel; note: string }>
    >(() => {
        const mapped: Record<string, { risk_level: RiskLevel; note: string }> =
            {};

        for (const note of checklist?.slotNotes ?? []) {
            mapped[note.time_slot] = {
                risk_level: note.risk_level,
                note: note.note ?? '',
            };
        }

        return mapped;
    });
    const [dirty, setDirty] = useState<Set<string>>(() => new Set());
    const [saving, setSaving] = useState(false);

    const sections = useMemo(() => {
        const grouped: {
            section: string | null;
            items: ChecklistItem[];
        }[] = [];

        for (const item of checklist?.items ?? []) {
            let section = grouped.find(
                (entry) => entry.section === item.section,
            );

            if (!section) {
                section = { section: item.section, items: [] };
                grouped.push(section);
            }

            section.items.push(item);
        }

        return grouped;
    }, [checklist]);

    function updateCell(
        itemId: number,
        slot: string,
        field: keyof Cell,
        value: string,
    ): void {
        const key = cellKey(itemId, slot);

        setCells((current) => ({
            ...current,
            [key]: {
                ...(current[key] ?? { value: '', status: '' }),
                [field]: value,
            },
        }));
        setDirty((current) => new Set(current).add(key));
    }

    function updateSlotRisk(slot: string, level: RiskLevel): void {
        setRisks((current) => ({
            ...current,
            [slot]: {
                ...(current[slot] ?? { risk_level: 'LOW', note: '' }),
                risk_level: level,
            },
        }));
    }

    function save(): void {
        if (!checklist?.category.id) {
            return;
        }

        const payloadEntries = [...dirty].map((key) => {
            const separator = key.indexOf(':');
            const itemId = Number(key.slice(0, separator));
            const timeSlot = key.slice(separator + 1);
            const cell = cells[key] ?? { value: '', status: '' };

            return {
                item_id: itemId,
                time_slot: timeSlot,
                value: cell.value === '' ? null : cell.value,
                status: cell.status === '' ? null : cell.status,
                note: null,
            };
        });

        const returnUrl = `${window.location.pathname}${window.location.search}`;

        const payloadSlotNotes = checklist.category.time_slots.map((slot) => {
            const note = risks[slot] ?? {
                risk_level: 'LOW' as RiskLevel,
                note: '',
            };

            return {
                time_slot: slot,
                risk_level: note.risk_level,
                note: note.note.trim() === '' ? null : note.note,
            };
        });

        const dayRisk =
            payloadSlotNotes.length > 0
                ? highestRisk(payloadSlotNotes.map((note) => note.risk_level))
                : (checklist.dayNote.risk_level as RiskLevel);

        setSaving(true);

        router.post(
            storeEntries.url(),
            {
                category_id: checklist.category.id,
                entry_date: checklist.date,
                risk_level: dayRisk,
                note: checklist.dayNote.note,
                entries: payloadEntries,
                slot_notes: payloadSlotNotes,
                redirect: returnUrl,
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(new Set()),
                onFinish: () => setSaving(false),
            },
        );
    }

    const itemCount = checklist?.items.length ?? 0;
    const slotCount = checklist?.category.time_slots.length ?? 0;

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="grid gap-1">
                    <CardTitle>Checklist</CardTitle>
                    <CardDescription>
                        {checklist
                            ? `${checklist.category.name ?? 'Checklist'} - ${itemCount} item - ${slotCount} slot waktu`
                            : 'Belum ada item checklist untuk aset ini.'}
                    </CardDescription>
                </div>

                {checklist?.category.id && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={dailycheck.url({
                                    query: {
                                        date: checklist.date,
                                        category: checklist.category.id,
                                    },
                                })}
                            >
                                <ExternalLink className="size-4" />
                                Buka Checklist
                            </Link>
                        </Button>
                        {canManage && (
                            <Button
                                size="sm"
                                onClick={save}
                                disabled={saving || dirty.size === 0}
                            >
                                <Save className="size-4" />
                                {saving ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        )}
                    </div>
                )}
            </CardHeader>

            <CardContent>
                {!checklist ? (
                    <p className="text-sm text-muted-foreground">
                        Item checklist akan muncul setelah dikonfigurasi di
                        halaman Checklist.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                    <th className="min-w-56 px-4 py-3 text-center font-medium">
                                        Item
                                    </th>
                                    <th className="w-20 px-3 py-3 text-center font-medium">
                                        Satuan
                                    </th>
                                    {checklist.category.time_slots.map(
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
                                                    className="px-4 py-2 text-left font-semibold tracking-wide text-muted-foreground uppercase"
                                                    colSpan={
                                                        checklist.category
                                                            .time_slots.length +
                                                        2
                                                    }
                                                >
                                                    {section.section}
                                                </td>
                                            </tr>
                                        )}
                                        {section.items.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b border-sidebar-border/50 last:border-b-0"
                                            >
                                                <td className="px-4 py-2 text-center">
                                                    {item.label}
                                                </td>
                                                <td className="px-3 py-2 text-center text-muted-foreground">
                                                    {item.unit ?? '-'}
                                                </td>
                                                {checklist.category.time_slots.map(
                                                    (slot) => {
                                                        const cell = cells[
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
                                                                key={slot}
                                                                className="px-2 py-2 text-center"
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

                                <tr className="border-t border-sidebar-border/70 bg-muted/30">
                                    <td className="px-4 py-2 text-center text-sm font-medium">
                                        Risk Level
                                    </td>
                                    <td className="px-3 py-2 text-center text-muted-foreground">
                                        -
                                    </td>
                                    {checklist.category.time_slots.map(
                                        (slot) => {
                                            const note = risks[slot] ?? {
                                                risk_level: 'LOW' as RiskLevel,
                                                note: '',
                                            };

                                            return (
                                                <td
                                                    key={`risk-${slot}`}
                                                    className="px-2 py-2 text-center"
                                                >
                                                    <Select
                                                        value={note.risk_level}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateSlotRisk(
                                                                slot,
                                                                value as RiskLevel,
                                                            )
                                                        }
                                                        disabled={!canManage}
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
                                                                        {level}
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
                )}
            </CardContent>
        </Card>
    );
}
