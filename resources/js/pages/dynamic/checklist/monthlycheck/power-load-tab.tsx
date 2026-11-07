import { router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import { storeLoad } from '@/actions/App/Http/Controllers/MonthlyCheckController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PowerPdu } from './types';

type Props = {
    floor: number;
    year: number;
    pdus: PowerPdu[];
    canManage: boolean;
};

const MONTH_LABELS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

function formatAmp(value: number | null): string {
    if (value === null) {
        return '';
    }

    return value.toString();
}

export default function PowerLoadTab({
    floor,
    year,
    pdus,
    canManage,
}: Props) {
    const [selectedPduId, setSelectedPduId] = useState<string | null>(
        pdus.length > 0 ? String(pdus[0].id) : null,
    );
    // Local edits keyed by `${rowId}-${month}` (month is 1-based).
    const [edits, setEdits] = useState<Record<string, string>>({});

    const pdu = pdus.find((item) => String(item.id) === selectedPduId);

    const cellValue = (rowId: number, month: number): string => {
        const key = `${rowId}-${month}`;
        const edit = edits[key];

        if (edit !== undefined) {
            return edit;
        }

        return formatAmp(
            pdu?.rows.find((row) => row.id === rowId)?.readings_by_month?.[
                month
            ] ?? null,
        );
    };

    const handleCellChange = (rowId: number, month: number, value: string) => {
        setEdits((prev) => ({ ...prev, [`${rowId}-${month}`]: value }));
    };

    const handleSavePdu = async () => {
        if (! pdu || ! canManage) {
            return;
        }

        const readings: Array<{
            row_id: number;
            period: string;
            ampere: number | null;
        }> = pdu.rows.flatMap((row) =>
            Array.from({ length: 12 }, (_, index) => {
                const month = index + 1;
                const raw = cellValue(row.id, month);
                const value = raw === '' ? null : Number(raw);

                return {
                    row_id: row.id,
                    period: `${year}-${String(month).padStart(2, '0')}`,
                    ampere: Number.isNaN(value ?? 0) ? null : value,
                };
            }),
        );

        await router.post(storeLoad.url(), {
            pdu_id: pdu.id,
            readings,
        });
    };

    if (! pdu) {
        return (
            <div className="rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center text-muted-foreground">
                Tidak ada PDU untuk Lantai {floor}.
            </div>
        );
    }

    const monthlyTotals = Array(12).fill(0);

    pdu.rows.forEach((row) => {
        for (let month = 1; month <= 12; month++) {
            const raw = cellValue(row.id, month);
            const value = raw === '' ? 0 : Number(raw);

            if (! Number.isNaN(value)) {
                monthlyTotals[month - 1] += value;
            }
        }
    });

    return (
        <div className="flex flex-col gap-4 min-w-[700px]">
            <div className="flex flex-wrap items-center gap-2 overflow-x-auto">
                {pdus.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setSelectedPduId(String(item.id))}
                        className={`whitespace-nowrap rounded-lg border px-3 py-2 text-sm transition-colors ${
                            String(item.id) === selectedPduId
                                ? 'border-primary bg-primary/10 font-medium text-foreground'
                                : 'border-sidebar-border/70 text-muted-foreground hover:bg-accent'
                        }`}
                    >
                        {item.code}
                    </button>
                ))}
            </div>

            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table className="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                            <th className="sticky left-0 z-10 min-w-56 border-r border-sidebar-border/50 bg-background px-4 py-3 font-medium">
                                Rack
                            </th>
                            <th className="w-20 border-r border-sidebar-border/50 bg-background px-2 py-3 text-center font-medium">
                                MCB
                            </th>
                            <th className="w-16 border-r border-sidebar-border/50 bg-background px-2 py-3 text-center font-medium">
                                Phase
                            </th>
                            <th className="w-20 border-r border-sidebar-border/50 bg-background px-2 py-3 text-center font-medium">
                                Amp
                            </th>
                            <th className="w-24 border-r border-sidebar-border/50 bg-background px-2 py-3 text-center font-medium">
                                Layanan
                            </th>
                            {MONTH_LABELS.map((label) => (
                                <th
                                    key={label}
                                    className="min-w-10 px-1 py-3 text-center font-medium"
                                >
                                    {label}
                                </th>
                            ))}
                            <th className="min-w-10 border-l border-sidebar-border/50 px-2 py-3 text-center font-medium">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {pdu.rows.map((row) => (
                            <tr
                                key={row.id}
                                className="border-b border-sidebar-border/50 last:border-b-0"
                            >
                                <td className="sticky left-0 z-10 border-r border-sidebar-border/50 bg-background px-4 py-2">
                                    <div className="font-medium">
                                        {row.rack ?? '-'}
                                    </div>
                                    {row.customer && (
                                        <div className="text-xs text-muted-foreground">
                                            {row.customer}
                                        </div>
                                    )}
                                </td>
                                <td className="border-r border-sidebar-border/50 px-2 py-2 text-center text-muted-foreground">
                                    {row.mcb ?? '-'}
                                </td>
                                <td className="border-r border-sidebar-border/50 px-2 py-2 text-center text-muted-foreground">
                                    {row.phase ?? '-'}
                                </td>
                                <td className="border-r border-sidebar-border/50 px-2 py-2 text-center text-muted-foreground">
                                    {row.capacity_amp !== null
                                        ? row.capacity_amp.toString()
                                        : '-'}
                                </td>
                                <td className="border-r border-sidebar-border/50 px-2 py-2 text-center text-muted-foreground">
                                    {row.service !== null
                                        ? String(row.service)
                                        : '-'}
                                </td>
                                {Array.from({ length: 12 }).map((_, index) => {
                                    const month = index + 1;
                                    const value = cellValue(row.id, month);

                                    return (
                                        <td
                                            key={month}
                                            className="px-1 py-2 text-center"
                                        >
                                            <Input
                                                type="text"
                                                value={value}
                                                onChange={(event) =>
                                                    handleCellChange(
                                                        row.id,
                                                        month,
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-8 w-14 text-center px-0"
                                                inputMode="decimal"
                                            />
                                        </td>
                                    );
                                })}
                                <td className="border-l border-sidebar-border/50 px-2 py-2 text-center font-medium">
                                    {monthlyTotals.reduce(
                                        (total, current) => total + current,
                                        0,
                                    ).toFixed(2)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table className="w-full border-collapse text-left text-sm">
                    <tbody>
                        <tr className="border-t border-sidebar-border/50 bg-muted/50 font-medium">
                            <td className="px-4 py-3 text-right">Total per bulan</td>
                            {MONTH_LABELS.map((_, index) => (
                                <td
                                    key={index}
                                    className="px-1 py-3 text-center"
                                >
                                    {monthlyTotals[index].toFixed(2)}
                                </td>
                            ))}
                            <td className="border-l border-sidebar-border/50 px-2 py-3 text-center">
                                {monthlyTotals.reduce(
                                    (total, current) => total + current,
                                    0,
                                ).toFixed(2)}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {canManage && (
                <div className="flex justify-end gap-2">
                    <Button onClick={handleSavePdu}>
                        <Save className="size-4" />
                        Simpan {pdu.code}
                    </Button>
                </div>
            )}
        </div>
    );
}
