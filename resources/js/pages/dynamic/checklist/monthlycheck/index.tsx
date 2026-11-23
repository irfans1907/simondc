import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    Save,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { exportMethod } from '@/actions/App/Http/Controllers/MonthlyCheckController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
import { Separator } from '@/components/ui/separator';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import { monthlycheck } from '@/routes/checklist';
import PowerLoadTab from './power-load-tab';
import PueTab from './pue-tab';
import type { PueTabHandle } from './pue-tab';
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

const MONTH_FULL_LABELS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const TAB_LABELS: Record<TabId, string> = {
    lantai2: 'Lantai 2',
    lantai4: 'Lantai 4',
    pue: 'PUE',
};

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
    const [open, setOpen] = useState(false);
    const [viewYear, setViewYear] = useState(year);
    const pueTabRef = useRef<PueTabHandle>(null);

    const selectedMonthValue = `${selectedYear}-${selectedMonth}`;
    const currentMonthValue = (() => {
        const now = new Date();

        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    })();

    const navigateTo = (
        nextTab: TabId,
        nextYear: number,
        nextMonth: string,
    ) => {
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

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            setViewYear(selectedYear);
        }

        setOpen(nextOpen);
    };

    const handleMonthSelect = (value: string) => {
        const [nextYear, nextMonth] = value.split('-');
        const yearNumber = Number(nextYear);

        setSelectedYear(yearNumber);
        setSelectedMonth(nextMonth);
        navigateTo(selectedTab, yearNumber, nextMonth);
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
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Monthly Check"
                        description={
                            selectedTab === 'pue'
                                ? 'Laporan konsumsi daya dan PUE data center per bulan.'
                                : 'Beban daya per PDU (ampere) per bulan.'
                        }
                    />
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Select
                            value={selectedTab}
                            onValueChange={handleTabChange}
                        >
                            <SelectTrigger
                                className="w-[110px]"
                                aria-label="Tab laporan"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(['lantai2', 'lantai4', 'pue'] as TabId[]).map(
                                    (id) => (
                                        <SelectItem key={id} value={id}>
                                            {TAB_LABELS[id]}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <Popover open={open} onOpenChange={handleOpenChange}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="w-[190px] justify-start"
                                >
                                    <CalendarDays className="size-4" />
                                    {
                                        MONTH_FULL_LABELS[
                                            Number(selectedMonth) - 1
                                        ]
                                    }{' '}
                                    {selectedYear}
                                    <ChevronDown className="ml-auto size-4 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-64 p-3" align="start">
                                <div className="mb-2 flex items-center justify-between">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() =>
                                            setViewYear((value) => value - 1)
                                        }
                                    >
                                        <ChevronLeft className="size-4" />
                                    </Button>
                                    <span className="text-sm font-medium">
                                        {viewYear}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() =>
                                            setViewYear((value) => value + 1)
                                        }
                                    >
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                                <div className="grid grid-cols-3 gap-1">
                                    {MONTH_LABELS.map((label, index) => {
                                        const value = `${viewYear}-${String(
                                            index + 1,
                                        ).padStart(2, '0')}`;
                                        const isSelected =
                                            value === selectedMonthValue;
                                        const isCurrent =
                                            value === currentMonthValue;

                                        return (
                                            <Button
                                                key={value}
                                                type="button"
                                                variant={
                                                    isSelected
                                                        ? 'default'
                                                        : 'ghost'
                                                }
                                                size="sm"
                                                className="relative h-9"
                                                onClick={() => {
                                                    setOpen(false);

                                                    if (!isSelected) {
                                                        handleMonthSelect(
                                                            value,
                                                        );
                                                    }
                                                }}
                                            >
                                                {label}
                                                {isCurrent ? (
                                                    <span
                                                        className={`${
                                                            isSelected
                                                                ? 'bg-primary-foreground'
                                                                : 'bg-primary'
                                                        }`}
                                                    />
                                                ) : null}
                                            </Button>
                                        );
                                    })}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-2 w-full"
                                    onClick={() => {
                                        setOpen(false);

                                        if (
                                            selectedMonthValue !==
                                            currentMonthValue
                                        ) {
                                            handleMonthSelect(
                                                currentMonthValue,
                                            );
                                        }
                                    }}
                                >
                                    <CalendarDays className="size-4" />
                                    Bulan Ini
                                </Button>
                            </PopoverContent>
                        </Popover>
                        <Button
                            variant="default"
                            className="bg-green-600 text-white hover:bg-green-700"
                            onClick={handleExport}
                            disabled={!canManage}
                        >
                            <Download className="size-4" />
                            Export
                        </Button>
                        {selectedTab === 'pue' && canManage && dirty && (
                            <Button
                                onClick={() => void pueTabRef.current?.save()}
                            >
                                <Save className="size-4" />
                                Simpan
                            </Button>
                        )}
                    </div>
                </div>
                <Separator />
                {selectedTab === 'pue' ? (
                    <PueTab
                        ref={pueTabRef}
                        panels={panels}
                        panelReadings={panelReadings}
                        period={period}
                        periodLabel={`${MONTH_FULL_LABELS[Number(selectedMonth) - 1]} ${selectedYear}`}
                        canManage={canManage}
                        onChange={() => setDirty(true)}
                        onSaved={() => setDirty(false)}
                    />
                ) : (
                    <PowerLoadTab
                        floor={floor}
                        year={selectedYear}
                        pdus={pdus}
                        periodLabel={`${MONTH_FULL_LABELS[Number(selectedMonth) - 1]} ${selectedYear}`}
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
