import { Head, Link, router } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { ArrowDown, ArrowUp, Search, X, CalendarSearch } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as activityLogIndex } from '@/routes/activity-log';
import type { ActivityLogEntry } from '@/types';

type PaginatedActivities = {
    data: ActivityLogEntry[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const perPageOptions = [10, 15, 20, 25, 50] as const;

const eventVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    created: 'default',
    updated: 'secondary',
    deleted: 'destructive',
    renamed: 'secondary',
    moved: 'secondary',
    downloaded: 'outline',
    login: 'outline',
    logout: 'outline',
};

function EventBadge({ event }: { event: string | null }) {
    if (!event) {
        return <Badge variant="outline">—</Badge>;
    }

    return (
        <Badge variant={eventVariants[event] ?? 'outline'}>{event}</Badge>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function ActivitySearch({
    search,
    perPage,
    date,
    sort,
}: {
    search: string;
    perPage: number;
    date: string;
    sort: string;
}) {
    const [value, setValue] = useState(search);

    useEffect(() => {
        const timeout = setTimeout(() => {
            const query = value.trim();

            if (query === search) {
                return;
            }

            router.get(
                activityLogIndex(),
                { search: query || undefined, per_page: perPage, date: date || undefined, sort: sort || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['activities', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, search, perPage, date, sort]);

    return (
        <div className="relative w-full sm:w-80">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                id="activity-search"
                name="search"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') {
                        setValue('');
                    }
                }}
                placeholder="Search user, description, log or event..."
                className="pr-9 pl-9"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => setValue('')}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Clear search"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

function DateFilter({
    date,
    search,
    perPage,
    sort,
}: {
    date: string;
    search: string;
    perPage: number;
    sort: string;
}) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<Date | undefined>(
        date ? new Date(`${date}T00:00:00`) : undefined,
    );

    useEffect(() => {
        setSelected(date ? new Date(`${date}T00:00:00`) : undefined);
    }, [date]);

    function applyDate(value: string) {
        router.get(
            activityLogIndex(),
            {
                search: search || undefined,
                per_page: perPage,
                sort: sort || undefined,
                date: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['activities', 'filters'],
            },
        );
    }

    function onSelect(day: Date | undefined) {
        setSelected(day);
        setOpen(false);

        if (!day) {
            applyDate('');

            return;
        }

        const y = day.getFullYear();
        const m = String(day.getMonth() + 1).padStart(2, '0');
        const d = String(day.getDate()).padStart(2, '0');

        applyDate(`${y}-${m}-${d}`);
    }

    function clearDate() {
        setSelected(undefined);
        setOpen(false);
        applyDate('');
    }

    return (
        <div className="flex items-center gap-2">
            <Field className="w-45">
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="outline"
                            id="date"
                            className="justify-start gap-2 font-normal"
                        >
                            <CalendarSearch className="size-4 text-muted-foreground" />
                            {selected ? (
                                selected.toLocaleDateString('en-GB', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                })
                            ) : (
                                <span className="text-muted-foreground">Search by date...</span>
                            )}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                        <Calendar
                            mode="single"
                            selected={selected}
                            defaultMonth={selected}
                            captionLayout="dropdown"
                            onSelect={onSelect}
                        />
                    </PopoverContent>
                </Popover>
            </Field>
            {date && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={clearDate}
                    className="size-9"
                    aria-label="Clear date filter"
                    title="Clear date filter"
                >
                    <X className="size-4" />
                </Button>
            )}
        </div>
    );
}

function PerPageSelector({
    search,
    perPage,
    date,
    sort,
}: {
    search: string;
    perPage: number;
    date: string;
    sort: string;
}) {
    function changePerPage(value: string) {
        router.get(
            activityLogIndex(),
            {
                search: search || undefined,
                per_page: value || undefined,
                date: date || undefined,
                sort: sort || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['activities', 'filters'],
            },
        );
    }

    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Show</span>
            <Select value={String(perPage)} onValueChange={changePerPage}>
                <SelectTrigger className="h-9 w-[72px]">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent side="top">
                    {perPageOptions.map((option) => (
                        <SelectItem key={option} value={String(option)}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <span className="text-muted-foreground">per page</span>
        </div>
    );
}

function Pagination({
    activities,
    search,
    perPage,
    date,
    sort,
}: {
    activities: PaginatedActivities;
    search: string;
    perPage: number;
    date: string;
    sort: string;
}) {
    return (
        <div className="mt-4 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
            <PerPageSelector search={search} perPage={perPage} date={date} sort={sort} />

            <div className="flex flex-wrap items-center justify-end gap-4">
                <div className="flex items-center gap-1">
                    {activities.links.map((link, index) => {
                        if (link.url === null) {
                            return (
                                <span
                                    key={index}
                                    className="px-3 py-1.5 text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            );
                        }

                        const isActive = link.active;

                        return (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded-md px-3 py-1.5 ${
                                    isActive
                                        ? 'bg-primary font-medium text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground'
                                }`}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

export default function ActivityLogIndex({
    activities,
    filters,
}: {
    activities: PaginatedActivities;
    filters: { search: string; per_page: number; sort: string; date: string };
}) {
    useEchoPublic('activity-log', '.activity-log.changed', (payload: unknown) => {
        void payload;
        router.reload({ only: ['activities'] });
    });

    function toggleSort() {
        router.get(
            activityLogIndex(),
            {
                search: filters.search || undefined,
                per_page: filters.per_page,
                date: filters.date || undefined,
                sort: filters.sort === 'asc' ? 'desc' : 'asc',
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['activities', 'filters'],
            },
        );
    }

    return (
        <>
            <Head title="Activity Log" />

            <h1 className="sr-only">Activity Log</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Activity Log"
                    description="Monitor the activity of the application"
                />

                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <ActivitySearch
                            search={filters.search}
                            perPage={filters.per_page}
                            date={filters.date}
                            sort={filters.sort}
                        />
                        <DateFilter
                            date={filters.date}
                            search={filters.search}
                            perPage={filters.per_page}
                            sort={filters.sort}
                        />
                    </div>
                    <span className="text-sm text-muted-foreground">
                        Showing of{' '}
                        <span className="font-medium text-foreground">
                            {activities.total}
                        </span>{' '}
                        Total Log
                    </span>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                                <th className="px-4 py-3 text-center font-medium">
                                    User
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Log
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    Event
                                </th>
                                <th className="w-72 px-4 py-3 text-center font-medium">
                                    Description
                                </th>
                                <th className="px-4 py-3 text-center font-medium">
                                    <button
                                        type="button"
                                        onClick={toggleSort}
                                        className="inline-flex items-center gap-1 hover:text-foreground"
                                        title={
                                            filters.sort === 'asc'
                                                ? 'Sort newest first'
                                                : 'Sort oldest first'
                                        }
                                    >
                                        Time
                                        {filters.sort === 'asc' ? (
                                            <ArrowUp className="size-3.5" />
                                        ) : (
                                            <ArrowDown className="size-3.5" />
                                        )}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {activities.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No activity found.
                                    </td>
                                </tr>
                            ) : (
                                activities.data.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="border-b border-sidebar-border/50 last:border-b-0"
                                    >
                                        <td className="px-4 py-3 text-center font-medium">
                                            {entry.causer?.name ?? 'System'}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant="secondary">
                                                {entry.log_name ?? 'default'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <EventBadge event={entry.event} />
                                        </td>
                                        <td className="w-150 whitespace-normal wrap-break-words px-4 py-3 text-center">
                                            {entry.description}
                                        </td>
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {formatDate(entry.created_at)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    activities={activities}
                    search={filters.search}
                    perPage={filters.per_page}
                    date={filters.date}
                    sort={filters.sort}
                />
            </div>
        </>
    );
}

ActivityLogIndex.layout = {
    breadcrumbs: [
        {
            title: 'Activity Log',
            href: activityLogIndex(),
        },
    ],
};