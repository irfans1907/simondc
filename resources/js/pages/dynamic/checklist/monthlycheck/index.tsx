import { Head, router } from '@inertiajs/react';
import { Download, RotateCcw, Save } from 'lucide-react';
import { useState } from 'react';
import { exportMethod, storePanel } from '@/actions/App/Http/Controllers/MonthlyCheckController';
import Heading from '@/components/heading';
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
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import { monthlycheck } from '@/routes/checklist';
import PowerLoadTab from './power-load-tab';
import PueTab from './pue-tab';
import type { PowerPanel, PowerPanelReading, PowerPdu } from './types';

type TabId = 'lantai2' | 'lantai4' | 'pue';

type PageProps = {
    tab: string;
    year: number;
    month: number;
    period: string;
    pdusByFloor: Record<number, PowerPdu[]>;
    panels: PowerPanel[];
    panelReadings: Record<number, PowerPanelReading>;
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

const TAB_LABELS: Record<TabId, string> = {
    lantai2: 'Lantai 2',
    lantai4: 'Lantai 4',
    pue: 'PUE & Beban DC',
};

const YEAR_MIN = 2024;
const YEAR_MAX = 2030;

function monthOptions(): { label: string; value: string }[] {
    return Array.from({ length: 12 }, (_, index) => ({
        label: MONTH_LABELS[index],
        value: String(index + 1).padStart(2, '0'),
    }));
}

export default function ChecklistMonthlycheck({
    tab: rawTab,
    year,
    month,
    period,
    pdusByFloor,
    panels,
    panelReadings,
    canManage,
}: PageProps) {
    const tab = (rawTab ?? 'lantai2') as TabId;
    const [selectedTab, setSelectedTab] = useState<TabId>(tab);
    const [selectedYear, setSelectedYear] = useState(year);
    const [selectedMonth, setSelectedMonth] = useState(
        String(month).padStart(2, '0'),
    );
    const [dirty, setDirty] = useState(false);

    const navigateTo = (nextTab: TabId, nextYear: number, nextMonth: string) => {
        router.get(
            monthlycheck.url({
                query: {
                    tab: nextTab,
                    year: nextYear,
                    month: nextMonth,
                },
            }),
            {},
            { preserveScroll: true },
        );
    };

    const handleTabChange = (next: string) => {
        const nextTab = (next ?? 'lantai2') as TabId;
        setSelectedTab(nextTab);
        navigateTo(nextTab, selectedYear, selectedMonth);
    };

    const handleYearChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const value = Number(event.target.value);

        if (Number.isNaN(value) || value < YEAR_MIN || value > YEAR_MAX) {
            return;
        }

        setSelectedYear(value);
        navigateTo(selectedTab, value, selectedMonth);
    };

    const handleMonthChange = (value: string) => {
        setSelectedMonth(value);
        navigateTo(selectedTab, selectedYear, value);
    };

    const handleSave = async () => {
        if (! canManage) {
            return;
        }

        if (selectedTab === 'pue') {
            await Promise.all(
                panels.map((panel) => {
                    const reading = panelReadings[panel.id];
                    const r = reading?.amp_r ?? null;
                    const s = reading?.amp_s ?? null;
                    const t = reading?.amp_t ?? null;
                    const note = reading?.note ?? null;

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

            setDirty(false);

            const [nextYear, nextMonth] = period.split('-').map(Number);

            router.get(
                monthlycheck.url({
                    query: {
                        tab: 'pue',
                        year: nextYear,
                        month: nextMonth,
                    },
                }),
                {},
                { preserveScroll: true },
            );
        }
    };

    const handleExport = () => {
        const url = exportMethod.url({
            query: {
                tab: selectedTab,
                year: selectedYear,
                month: selectedMonth,
            },
        });

        window.location.href = url;
    };

    const floor = selectedTab === 'lantai2' ? 2 : 4;
    const pdus = pdusByFloor[floor] ?? [];

    return (
        <>
            <Head title="Monthly Check" />
            <h1 className="sr-only">Monthly Check</h1>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Monthly Check"
                        description={
                            selectedTab === 'pue'
                                ? 'Laporan konsumsi daya dan PUE data center per bulan.'
                                : 'Beban daya per PDU (ampere) per bulan.'
                        }
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            size="sm"
                            value={selectedTab}
                            onValueChange={handleTabChange}
                            className="hidden md:flex"
                        >
                            {(['lantai2', 'lantai4', 'pue'] as TabId[]).map(
                                (id) => (
                                    <ToggleGroupItem
                                        key={id}
                                        value={id}
                                        className="data-[state=on]:shadow-sm"
                                    >
                                        {TAB_LABELS[id]}
                                    </ToggleGroupItem>
                                ),
                            )}
                        </ToggleGroup>
                        {selectedTab !== 'pue' && (
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="monthly-year"
                                    className="sr-only"
                                >
                                    Tahun
                                </Label>
                                <Input
                                    id="monthly-year"
                                    type="number"
                                    min={YEAR_MIN}
                                    max={YEAR_MAX}
                                    value={selectedYear}
                                    onChange={handleYearChange}
                                    className="w-20"
                                />
                                <Button
                                    variant="outline"
                                    onClick={handleExport}
                                    disabled={!canManage}
                                >
                                    <Download className="size-4" />
                                    Export CSV
                                </Button>
                            </div>
                        )}
                        {selectedTab === 'pue' && (
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="monthly-pue-month"
                                    className="sr-only"
                                >
                                    Bulan
                                </Label>
                                <Select
                                    onValueChange={handleMonthChange}
                                    defaultValue={selectedMonth}
                                >
                                    <SelectTrigger id="monthly-pue-month">
                                        <SelectValue placeholder="Bulan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monthOptions().map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="outline"
                                    onClick={handleExport}
                                    disabled={!canManage}
                                >
                                    <Download className="size-4" />
                                    Export CSV
                                </Button>
                                {canManage && dirty && (
                                    <Button onClick={handleSave}>
                                        <Save className="size-4" />
                                        Simpan
                                    </Button>
                                )}
                                <Button variant="outline" asChild>
                                    <a href={monthlycheck.url()}>
                                        <RotateCcw className="size-4" />
                                        Muat Ulang
                                    </a>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
                <Separator />
                {selectedTab === 'pue' ? (
                    <PueTab
                        panels={panels}
                        panelReadings={panelReadings}
                        period={period}
                        canManage={canManage}
                        onChange={() => setDirty(true)}
                    />
                ) : (
                    <PowerLoadTab
                        floor={floor}
                        year={selectedYear}
                        pdus={pdus}
                        canManage={canManage}
                    />
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
