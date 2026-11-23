import { router } from '@inertiajs/react';
import { forwardRef, useImperativeHandle, useState } from 'react';
import { storePanel } from '@/actions/App/Http/Controllers/MonthlyCheckController';
import { Input } from '@/components/ui/input';
import { monthlycheck } from '@/routes/checklist';
import type { PowerPanel, PowerPanelReading } from './types';

type PanelEdit = {
    amp_r: string;
    amp_s: string;
    amp_t: string;
    note: string;
};

type Props = {
    panels: PowerPanel[];
    panelReadings: Record<number, PowerPanelReading>;
    period: string;
    periodLabel: string;
    canManage: boolean;
    onChange: () => void;
    onSaved: () => void;
};

export type PueTabHandle = {
    save: () => Promise<void>;
};

const PANEL_GROUPS = [
    'MDP',
    'SDP',
    'IO',
    'PAC',
    'UPS',
    'PDU',
    'UTILITY',
] as const;

const GROUP_LABELS: Record<string, string> = {
    MDP: 'MDP (Main Distribution Panel)',
    SDP: 'SDP (Sub Distribution Panel)',
    IO: 'IO (UPS Input/Output)',
    PAC: 'PAC (Precision Air Conditioning)',
    UPS: 'UPS (Uninterruptible Power Supply)',
    PDU: 'PDU (Power Distribution Unit)',
    UTILITY: 'Utility Gedung',
};

function formatNumber(value: number | null): string {
    if (value === null) {
        return '';
    }

    const numeric = Number(value);

    return Number.isNaN(numeric) ? '' : numeric.toFixed(2);
}

function panelFloor(panel: PowerPanel): number | null {
    const match = panel.name.match(/\b(L[234])\b/);

    if (!match) {
        return null;
    }

    return Number(match[1].slice(1));
}

function isPdu(panel: PowerPanel): boolean {
    return panel.panel_group === 'PDU';
}

function isPac(panel: PowerPanel): boolean {
    return panel.panel_group === 'PAC';
}

function isUtility(panel: PowerPanel): boolean {
    return panel.panel_group === 'UTILITY';
}

function computePanel(
    panel: PowerPanel,
    reading: PowerPanelReading | null,
    edit: PanelEdit | null,
) {
    const r =
        edit !== null && edit.amp_r !== ''
            ? Number(edit.amp_r) || null
            : (reading?.amp_r ?? null);
    const s =
        edit !== null && edit.amp_s !== ''
            ? Number(edit.amp_s) || null
            : (reading?.amp_s ?? null);
    const t =
        edit !== null && edit.amp_t !== ''
            ? Number(edit.amp_t) || null
            : (reading?.amp_t ?? null);

    let kw = null;
    let kva = null;
    let capacity80 = null;
    let idle = null;
    let used = null;

    if (r !== null || s !== null || t !== null) {
        kw = ((r ?? 0) + (s ?? 0) + (t ?? 0)) * 0.176; // 220 / 1000 * 0.8
        kva = kw / 0.8;
    }

    if (panel.capacity !== null) {
        capacity80 = panel.capacity * 0.8;

        if (panel.unit === 'KVA') {
            idle = capacity80 - (kva ?? 0);
        } else {
            idle = capacity80 - Math.max(r ?? 0, s ?? 0, t ?? 0);
        }

        used = capacity80 > 0 ? ((capacity80 - idle) / capacity80) * 100 : 0;
    }

    return { r, s, t, kw, kva, capacity80, idle, used };
}

function computePanelMap(
    panels: PowerPanel[],
    panelReadings: Record<number, PowerPanelReading>,
    edits: Record<number, PanelEdit>,
): Record<number, ReturnType<typeof computePanel>> {
    const map: Record<number, ReturnType<typeof computePanel>> = {};

    panels.forEach((panel) => {
        map[panel.id] = computePanel(
            panel,
            panelReadings[panel.id] ?? null,
            edits[panel.id] ?? null,
        );
    });

    return map;
}

function computeFloorLoad(
    panels: PowerPanel[],
    computed: Record<number, ReturnType<typeof computePanel>>,
): Record<number, { it: number; nonIt: number }> {
    const loads: Record<number, { it: number; nonIt: number }> = {
        2: { it: 0, nonIt: 0 },
        3: { it: 0, nonIt: 0 },
        4: { it: 0, nonIt: 0 },
    };

    panels.forEach((panel) => {
        const floor = panelFloor(panel);
        const kva = computed[panel.id].kva ?? 0;

        if (!floor) {
            return;
        }

        if (isPdu(panel)) {
            loads[floor].it += kva;
        } else if (isPac(panel)) {
            loads[floor].nonIt += kva;
        }
    });

    panels.forEach((panel) => {
        if (isUtility(panel)) {
            const kva = computed[panel.id].kva ?? 0;

            [2, 3, 4].forEach((floor) => {
                loads[floor].nonIt += kva;
            });
        }
    });

    return loads;
}

function computePueByFloor(
    floorItLoad: Record<number, { it: number; nonIt: number }>,
): Record<number, number | null> {
    const map: Record<number, number | null> = {};

    [2, 3, 4].forEach((floor) => {
        const load = floorItLoad[floor];

        map[floor] = load.it > 0 ? (load.it + load.nonIt) / load.it : null;
    });

    return map;
}

const PueTab = forwardRef<PueTabHandle, Props>(function PueTab(
    {
        panels,
        panelReadings,
        period,
        periodLabel,
        canManage,
        onChange,
        onSaved,
    },
    ref,
) {
    const [edits, setEdits] = useState<Record<number, PanelEdit>>({});
    const [dirty, setDirty] = useState<Set<number>>(new Set());

    const computed = computePanelMap(panels, panelReadings, edits);
    const floorItLoad = computeFloorLoad(panels, computed);
    const pueByFloor = computePueByFloor(floorItLoad);
    const pueDc =
        pueByFloor[2] !== null &&
        pueByFloor[3] !== null &&
        pueByFloor[4] !== null
            ? (pueByFloor[2] + pueByFloor[3] + pueByFloor[4]) / 3
            : null;
    const pueNeu =
        pueByFloor[2] !== null && pueByFloor[4] !== null
            ? (pueByFloor[2] + pueByFloor[4]) / 2
            : null;

    const handleCellChange = (
        panelId: number,
        field: keyof PanelEdit,
        value: string,
    ) => {
        setEdits((prev) => ({
            ...prev,
            [panelId]: { ...prev[panelId], [field]: value },
        }));
        setDirty((prev) => {
            const next = new Set(prev);
            next.add(panelId);

            return next;
        });
        onChange();
    };

    const handleSave = async () => {
        if (!canManage) {
            return;
        }

        await Promise.all(
            panels.map((panel) => {
                if (!dirty.has(panel.id)) {
                    return Promise.resolve();
                }

                const edit = edits[panel.id] ?? {
                    amp_r: '',
                    amp_s: '',
                    amp_t: '',
                    note: '',
                };
                const r = edit.amp_r === '' ? null : Number(edit.amp_r);
                const s = edit.amp_s === '' ? null : Number(edit.amp_s);
                const t = edit.amp_t === '' ? null : Number(edit.amp_t);
                const note = edit.note === '' ? null : edit.note;

                if (
                    Number.isNaN(r ?? 0) ||
                    Number.isNaN(s ?? 0) ||
                    Number.isNaN(t ?? 0)
                ) {
                    return Promise.resolve();
                }

                if (r === null && s === null && t === null && !note) {
                    return Promise.resolve();
                }

                return router.post(storePanel.url(), {
                    panel_id: panel.id,
                    period,
                    amp_r: r,
                    amp_s: s,
                    amp_t: t,
                    note,
                });
            }),
        );

        setDirty(new Set());
        onSaved();

        const [nextYear, nextMonth] = period.split('-').map(Number);

        router.get(
            monthlycheck.url({
                query: { tab: 'pue', year: nextYear, month: nextMonth },
            }),
            {},
            { preserveScroll: true },
        );
    };

    useImperativeHandle(ref, () => ({ save: handleSave }));

    return (
        <div className="flex min-w-[900px] flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                <span>Periode:</span>
                <span className="font-medium">{periodLabel}</span>
            </div>

            <div className="rounded-xl border border-sidebar-border/70 bg-muted/50 p-4">
                <h3 className="mb-3 text-sm font-semibold">Ringkasan PUE</h3>
                <div className="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                    <div>
                        <div className="text-xs text-muted-foreground">
                            PUE Lantai 2
                        </div>
                        <div className="font-mono font-medium">
                            {pueByFloor[2] !== null
                                ? pueByFloor[2].toFixed(3)
                                : '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-muted-foreground">
                            PUE Lantai 3
                        </div>
                        <div className="font-mono font-medium">
                            {pueByFloor[3] !== null
                                ? pueByFloor[3].toFixed(3)
                                : '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-muted-foreground">
                            PUE Lantai 4
                        </div>
                        <div className="font-mono font-medium">
                            {pueByFloor[4] !== null
                                ? pueByFloor[4].toFixed(3)
                                : '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-muted-foreground">
                            PUE DC
                        </div>
                        <div className="font-mono font-medium">
                            {pueDc !== null ? pueDc.toFixed(3) : '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-muted-foreground">
                            PUE Neucentrix
                        </div>
                        <div className="font-mono font-medium">
                            {pueNeu !== null ? pueNeu.toFixed(3) : '-'}
                        </div>
                    </div>
                </div>
            </div>

            {PANEL_GROUPS.map((group) => {
                const groupPanels = panels.filter(
                    (panel) => panel.panel_group === group,
                );

                if (groupPanels.length === 0) {
                    return null;
                }

                return (
                    <section key={group} className="flex flex-col gap-2">
                        <div className="flex items-center gap-2 border-b border-sidebar-border/50 bg-background/50 px-2 py-1.5">
                            <span className="text-xs font-semibold tracking-wider uppercase">
                                {GROUP_LABELS[group]}
                            </span>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                            <table className="w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                        <th className="px-4 py-3 font-medium">
                                            No
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Nama Perangkat
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            Kapasitas
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            Satuan
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            R
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            S
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            T
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            kW
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            kVA
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            Kapas 80%
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            Idle
                                        </th>
                                        <th className="px-2 py-3 text-center font-medium">
                                            Used %
                                        </th>
                                        <th className="px-4 py-3 text-center font-medium">
                                            Keterangan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {groupPanels.map((panel, index) => {
                                        const c = computed[panel.id];
                                        const edit = edits[panel.id] ?? {
                                            amp_r: '',
                                            amp_s: '',
                                            amp_t: '',
                                            note: '',
                                        };

                                        return (
                                            <tr
                                                key={panel.id}
                                                className="border-b border-sidebar-border/50 last:border-b-0"
                                            >
                                                <td className="px-4 py-2 text-center">
                                                    {index + 1}
                                                </td>
                                                <td className="px-4 py-2 font-medium">
                                                    {panel.name}
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {panel.capacity !== null
                                                        ? panel.capacity.toString()
                                                        : '-'}
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {panel.unit ?? '-'}
                                                </td>
                                                <td className="item-center px-2 py-2 text-center">
                                                    <Input
                                                        value={edit.amp_r}
                                                        onChange={(event) =>
                                                            handleCellChange(
                                                                panel.id,
                                                                'amp_r',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="mx-auto h-8 w-16 px-0 text-center"
                                                        inputMode="decimal"
                                                    />
                                                </td>
                                                <td className="px-2 py-2 text-center">
                                                    <Input
                                                        value={edit.amp_s}
                                                        onChange={(event) =>
                                                            handleCellChange(
                                                                panel.id,
                                                                'amp_s',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="mx-auto h-8 w-16 px-0 text-center"
                                                        inputMode="decimal"
                                                    />
                                                </td>
                                                <td className="px-2 py-2 text-center">
                                                    <Input
                                                        value={edit.amp_t}
                                                        onChange={(event) =>
                                                            handleCellChange(
                                                                panel.id,
                                                                'amp_t',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="mx-auto h-8 w-16 px-0 text-center"
                                                        inputMode="decimal"
                                                    />
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {formatNumber(c.kw)}
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {formatNumber(c.kva)}
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {formatNumber(c.capacity80)}
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {formatNumber(c.idle)}
                                                </td>
                                                <td className="px-2 py-2 text-center text-muted-foreground">
                                                    {c.used !== null
                                                        ? c.used.toFixed(2)
                                                        : '-'}
                                                </td>
                                                <td className="px-4 py-2 text-center text-muted-foreground">
                                                    {panel.note ?? '-'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </section>
                );
            })}
        </div>
    );
});

export default PueTab;
