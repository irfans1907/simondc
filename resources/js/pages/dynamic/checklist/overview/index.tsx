import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BookOpenCheck,
    CalendarCheck2,
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Gauge,
    ListChecks,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';
import { dailycheck, monthlycheck } from '@/routes/checklist';

type OverviewCategory = {
    id: number;
    slug: string;
    name: string;
    time_slots: string[];
    total_items: number;
    total_slots: number;
    filled_today: number;
    filled_month: number;
    filled_today_items: number;
    filled_month_items: number;
    risk_level: 'LOW' | 'MEDIUM' | 'HIGH';
    note: string | null;
};

type RecentNote = {
    id: number;
    category: string;
    date: string;
    risk_level: 'LOW' | 'MEDIUM' | 'HIGH';
    note: string;
};

type Totals = {
    items: number;
    slots: number;
    filled_today: number;
    filled_month: number;
    high: number;
    medium: number;
    low: number;
};

const riskBadgeClass: Record<string, string> = {
    LOW: 'border-emerald-600/40 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    MEDIUM: 'border-amber-600/40 bg-amber-600/10 text-amber-700 dark:text-amber-400',
    HIGH: 'border-red-600/40 bg-red-600/10 text-red-700 dark:text-red-400',
};

function percent(filled: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((filled / total) * 100));
}

function ProgressBar({ filled, total }: { filled: number; total: number }) {
    const value = percent(filled, total);

    return (
        <div className="flex items-center justify-center gap-2">
            <span aria-hidden className="w-4" />
            <div className="h-2 w-24 overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-full rounded-full ${
                        value === 100
                            ? 'bg-emerald-500'
                            : value > 0
                              ? 'bg-primary'
                              : 'bg-muted-foreground/30'
                    }`}
                    style={{ width: `${value}%` }}
                />
            </div>
            <span className="w-16 text-xs text-muted-foreground">
                {filled}/{total}
            </span>
        </div>
    );
}

function StatCard({
    title,
    value,
    hint,
    icon: Icon,
}: {
    title: string;
    value: string;
    hint: string;
    icon: typeof ListChecks;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <Icon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold">{value}</p>
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

export default function ChecklistOverview({
    date,
    month,
    monthName,
    daysInMonth,
    categories,
    totals,
    recentNotes,
}: {
    date: string;
    month: string;
    monthName: string;
    daysInMonth: number;
    categories: OverviewCategory[];
    totals: Totals;
    recentNotes: RecentNote[];
}) {
    const monthNames = Array.from({ length: 12 }, (_, index) =>
        new Date(2000, index, 1).toLocaleDateString('id-ID', {
            month: 'long',
        }),
    );
    const [open, setOpen] = useState(false);
    const [viewYear, setViewYear] = useState(Number(month.split('-')[0]));
    const now = new Date();
    const currentMonthValue = `${now.getFullYear()}-${String(
        now.getMonth() + 1,
    ).padStart(2, '0')}`;

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            setViewYear(Number(month.split('-')[0]));
        }

        setOpen(nextOpen);
    };

    const handleMonthChange = (value: string) => {
        router.get(
            '/checklist/overview',
            { month: value },
            { preserveScroll: true },
        );
    };

    const todayProgress = percent(totals.filled_today, totals.slots);
    const monthProgress = percent(
        totals.filled_month,
        totals.slots * daysInMonth,
    );

    return (
        <>
            <Head title="Overview" />
            <h1 className="sr-only">Overview</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Checklist Overview"
                        description={`Ringkasan checklist infrastruktur untuk bulan ${monthName}.`}
                    />
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Popover open={open} onOpenChange={handleOpenChange}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="w-[190px] justify-start"
                                >
                                    <CalendarDays className="size-4" />
                                    {monthName}
                                    <ChevronDown className="ml-auto size-4 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-64 p-3" align="end">
                                <div className="mb-2 flex items-center justify-between">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() =>
                                            setViewYear((year) => year - 1)
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
                                            setViewYear((year) => year + 1)
                                        }
                                    >
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                                <div className="grid grid-cols-3 gap-1">
                                    {monthNames.map((name, index) => {
                                        const value = `${viewYear}-${String(
                                            index + 1,
                                        ).padStart(2, '0')}`;
                                        const isSelected = value === month;
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
                                                        handleMonthChange(
                                                            value,
                                                        );
                                                    }
                                                }}
                                            >
                                                {name.slice(0, 3)}
                                                {isCurrent ? (
                                                    <span
                                                        className={`
                                                            ${
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

                                        if (month !== currentMonthValue) {
                                            handleMonthChange(
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
                        <Button asChild>
                            <Link href={dailycheck.url({ query: { date } })}>
                                <BookOpenCheck className="size-4" />
                                Daily Check
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={monthlycheck.url({ query: { month } })}>
                                <CalendarCheck2 className="size-4" />
                                Monthly Check
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Total Item"
                        value={String(totals.items)}
                        hint={`${categories.length} kategori, ${totals.slots} slot per hari`}
                        icon={ListChecks}
                    />
                    <StatCard
                        title="Terisi Hari Ini"
                        value={`${todayProgress}%`}
                        hint={`${totals.filled_today} dari ${totals.slots} slot`}
                        icon={Gauge}
                    />
                    <StatCard
                        title="Terisi Bulan Ini"
                        value={`${monthProgress}%`}
                        hint={`${totals.filled_month} slot tercatat`}
                        icon={ClipboardList}
                    />
                    <StatCard
                        title="Risiko Hari Ini"
                        value={`${totals.high + totals.medium}`}
                        hint={`${totals.high} HIGH, ${totals.medium} MEDIUM, ${totals.low} LOW`}
                        icon={AlertTriangle}
                    />
                </div>

                <div className="mt-4 mb-4 overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                <th className="min-w-48 px-4 py-3 text-center font-medium">
                                    Kategori
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Item
                                </th>
                                <th className="min-w-44 px-4 py-3 text-center font-medium">
                                    Hari Ini
                                </th>
                                <th className="min-w-44 px-4 py-3 text-center font-medium">
                                    Bulan Ini
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Risk
                                </th>
                                <th className="min-w-48 px-4 py-3 text-center font-medium">
                                    Catatan
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        Belum ada kategori checklist. Jalankan
                                        seeder checklist terlebih dahulu.
                                    </td>
                                </tr>
                            ) : (
                                categories.map((category) => (
                                    <tr
                                        key={category.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center font-medium">
                                            {category.name}
                                        </td>
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {category.total_items}
                                        </td>
                                        <td className="px-4 py-3">
                                            <ProgressBar
                                                filled={category.filled_today}
                                                total={category.total_slots}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <ProgressBar
                                                filled={category.filled_month}
                                                total={
                                                    category.total_slots *
                                                    daysInMonth
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant="outline"
                                                className={
                                                    riskBadgeClass[
                                                        category.risk_level
                                                    ]
                                                }
                                            >
                                                {category.risk_level}
                                            </Badge>
                                        </td>
                                        <td className="max-w-64 px-4 py-3 text-center text-muted-foreground">
                                            <span className="line-clamp-2">
                                                {category.note || '-'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={dailycheck.url({
                                                        query: {
                                                            date,
                                                            category:
                                                                category.id,
                                                        },
                                                    })}
                                                >
                                                    Isi
                                                    <ArrowRight className="size-4" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Catatan Terbaru</CardTitle>
                            <CardDescription>
                                Catatan dan risk level yang terakhir diisi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentNotes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada catatan.
                                </p>
                            ) : (
                                <ul className="grid gap-3">
                                    {recentNotes.map((note) => (
                                        <li
                                            key={note.id}
                                            className="flex flex-wrap items-start gap-2 border-b border-sidebar-border/50 pb-3 text-sm last:border-b-0 last:pb-0"
                                        >
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
                                            <span className="font-medium">
                                                {note.category}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {note.date}
                                            </span>
                                            <p className="w-full text-muted-foreground">
                                                {note.note}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Legenda Progress</CardTitle>
                            <CardDescription>
                                Progress dihitung dari jumlah slot waktu yang
                                sudah terisi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm text-muted-foreground">
                            <p>
                                <span className="mr-2 inline-block size-2 rounded-full bg-emerald-500 align-middle" />
                                Hijau: seluruh slot pada periode sudah terisi.
                            </p>
                            <p>
                                <span className="mr-2 inline-block size-2 rounded-full bg-blue-500 align-middle" />
                                Biru: sebagian slot sudah terisi.
                            </p>
                            <p>
                                <span className="mr-2 inline-block size-2 rounded-full bg-muted-foreground/30 align-middle" />
                                Abu-abu: belum ada data.
                            </p>
                            <p>
                                Klik <strong>Isi</strong> pada salah satu
                                kategori untuk membuka Daily Check pada tanggal
                                tersebut.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ChecklistOverview.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs: showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, '/checklist/overview') ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});
