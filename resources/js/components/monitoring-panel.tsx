import { router, usePage } from '@inertiajs/react';
import { Activity, Droplets, Gauge, Thermometer, Video } from 'lucide-react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cameras, onlineCameras, recordingCameras } from '@/pages/dynamic/monitoring/cctv';
import {
    alarmSourceLabels,
    alarms,
    readings,
    sensorSourceLabels,
    sensors,
    summaryCards,
} from '@/pages/dynamic/monitoring/data';
import type { SensorSource } from '@/pages/dynamic/monitoring/data';

export type MonitoringTab = 'overview' | 'sensors' | 'cctv' | 'alarms';

const tabIcons = {
    Temperature: Thermometer,
    Humidity: Droplets,
    Airflow: Gauge,
    'Water Leak': Droplets,
    Cameras: Video,
} as const;

const deviceChips: Record<SensorSource, { name: string; status: 'online' | 'offline' }[]> = {
    raritan: [
        { name: 'EMX2-111 Controller', status: 'online' },
        { name: 'EMX2-112 Controller', status: 'online' },
        { name: 'EMS Sensor Hub', status: 'online' },
    ],
    nti: [
        { name: 'ENVIROMUX-SE', status: 'online' },
        { name: 'ENVIROMUX-16R', status: 'online' },
    ],
};

const statusBadge: Record<string, { label: string; className: string }> = {
    normal: { label: 'Normal', className: 'border-transparent bg-green-600 text-white' },
    warning: { label: 'Warning', className: 'border-transparent bg-amber-500 text-white' },
    critical: { label: 'Critical', className: 'border-transparent bg-red-600 text-white' },
    offline: { label: 'Offline', className: 'text-muted-foreground' },
};

const severityBadge: Record<string, { label: string; className: string }> = {
    critical: { label: 'Critical', className: 'border-transparent bg-red-600 text-white' },
    warning: { label: 'Warning', className: 'border-transparent bg-amber-500 text-white' },
    info: { label: 'Info', className: 'border-transparent bg-sky-600 text-white' },
};

function readSourceFromUrl(url: string): SensorSource {
    try {
        const source = new URL(url, 'http://localhost').searchParams.get('source');

        return source === 'nti' ? 'nti' : 'raritan';
    } catch {
        return 'raritan';
    }
}

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: { name: string; value: number | string; color: string }[];
    label?: string;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-md border border-sidebar-border/70 bg-card px-3 py-2 text-xs shadow-sm">
            <p className="mb-1 font-medium text-foreground">{label}</p>
            {payload.map((entry) => (
                <p key={entry.name} style={{ color: entry.color }} className="flex items-center gap-2">
                    <span className="inline-block size-2 rounded-full" style={{ backgroundColor: entry.color }} />
                    {entry.name}: {entry.value}
                </p>
            ))}
        </div>
    );
}

function DeviceChips({ source }: { source: SensorSource }) {
    return (
        <div className="mb-3 flex flex-wrap gap-2">
            {deviceChips[source].map((device) => (
                <Badge key={device.name} variant="outline" className="gap-1.5 font-normal">
                    <span
                        className={`size-1.5 rounded-full ${device.status === 'online' ? 'bg-green-500' : 'bg-zinc-400'}`}
                    />
                    {device.name}
                </Badge>
            ))}
        </div>
    );
}

function SensorTable({ source }: { source: SensorSource }) {
    const rows = sensors.filter((sensor) => sensor.source === source);

    return (
        <>
            <DeviceChips source={source} />
            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-sidebar-border/70 text-muted-foreground">
                            <th className="px-4 py-3 text-center font-medium">Sensor</th>
                            <th className="px-4 py-3 text-center font-medium">Type</th>
                            <th className="px-4 py-3 text-center font-medium">Location</th>
                            <th className="px-4 py-3 text-center font-medium">Value</th>
                            <th className="px-4 py-3 text-center font-medium">Threshold</th>
                            <th className="px-4 py-3 text-center font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((sensor) => {
                            const badge = statusBadge[sensor.status];

                            return (
                                <tr
                                    key={sensor.id}
                                    className="border-b border-sidebar-border/50 last:border-b-0"
                                >
                                    <td className="px-4 py-3 text-center font-medium">{sensor.name}</td>
                                    <td className="px-4 py-3 text-center">
                                        <Badge variant="outline" className="capitalize">
                                            {sensor.type}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-center">{sensor.location}</td>
                                    <td className="px-4 py-3 text-center font-medium">
                                        {sensor.value}
                                        {sensor.unit ? ` ${sensor.unit}` : ''}
                                    </td>
                                    <td className="px-4 py-3 text-center text-muted-foreground">
                                        {sensor.threshold}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <Badge className={badge.className}>{badge.label}</Badge>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </>
    );
}

/**
 * Shared monitoring UI rendered by each monitoring child page with a fixed tab.
 */
export default function MonitoringPanel({ tab }: { tab: MonitoringTab }) {
    const { url } = usePage();
    const source = readSourceFromUrl(url);

    function changeSource(value: string) {
        router.get(
            '/monitoring/sensors',
            { source: value },
            {
                replace: true,
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return (
        <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
            <Heading
                variant="default"
                title="Monitoring"
                description="CCTV | Raritan (EMS & EMX) | NTI environment monitoring"
                className="mb-4"
            />

            <Tabs value={tab}>
                <TabsContent value="overview" className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        {summaryCards.map((card) => {
                            const Icon = tabIcons[card.label as keyof typeof tabIcons] ?? Activity;
                            const badge = statusBadge[card.status];

                            return (
                                <Card key={card.label}>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium text-muted-foreground">
                                            {card.label}
                                        </CardTitle>
                                        <Icon className="size-4 text-muted-foreground" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-baseline gap-1.5">
                                            <span className="text-2xl font-semibold tracking-tight">
                                                {card.value}
                                            </span>
                                            <span className="text-sm text-muted-foreground">{card.unit}</span>
                                            <Badge className={`ml-auto ${badge.className}`}>{badge.label}</Badge>
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">{card.detail}</p>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Temperature & Humidity · 24h</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={readings} margin={{ top: 5, right: 10, bottom: 0, left: -15 }}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--sidebar-border))" vertical={false} />
                                            <XAxis dataKey="time" tick={{ fontSize: 12 }} tickLine={false} axisLine={false} />
                                            <YAxis yAxisId="temp" tick={{ fontSize: 12 }} tickLine={false} axisLine={false} domain={[20, 30]} />
                                            <YAxis yAxisId="hum" orientation="right" tick={{ fontSize: 12 }} tickLine={false} axisLine={false} domain={[30, 70]} />
                                            <Tooltip content={<ChartTooltip />} />
                                            <Line yAxisId="temp" type="monotone" dataKey="temperature" name="Temp (°C)" stroke="#ef4444" strokeWidth={2} dot={false} />
                                            <Line yAxisId="hum" type="monotone" dataKey="humidity" name="Humidity (%)" stroke="#0ea5e9" strokeWidth={2} dot={false} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Airflow · 24h</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <AreaChart data={readings} margin={{ top: 5, right: 10, bottom: 0, left: -15 }}>
                                            <defs>
                                                <linearGradient id="airflowFill" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.35} />
                                                    <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--sidebar-border))" vertical={false} />
                                            <XAxis dataKey="time" tick={{ fontSize: 12 }} tickLine={false} axisLine={false} />
                                            <YAxis tick={{ fontSize: 12 }} tickLine={false} axisLine={false} domain={[0, 3]} />
                                            <Tooltip content={<ChartTooltip />} />
                                            <Area type="monotone" dataKey="airflow" name="Airflow (m/s)" stroke="#10b981" strokeWidth={2} fill="url(#airflowFill)" />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="sensors">
                    <Tabs value={source} onValueChange={changeSource}>
                        <TabsList>
                            <TabsTrigger value="raritan">{sensorSourceLabels.raritan}</TabsTrigger>
                            <TabsTrigger value="nti">{sensorSourceLabels.nti}</TabsTrigger>
                        </TabsList>
                        <TabsContent value="raritan" className="mt-4">
                            <SensorTable source="raritan" />
                        </TabsContent>
                        <TabsContent value="nti" className="mt-4">
                            <SensorTable source="nti" />
                        </TabsContent>
                    </Tabs>
                </TabsContent>

                <TabsContent value="cctv" className="space-y-4">
                    <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span>
                            <span className="font-medium text-foreground">
                                {onlineCameras}/{cameras.length}
                            </span>{' '}
                            cameras online
                        </span>
                        <span>
                            <span className="font-medium text-foreground">{recordingCameras}</span>{' '}
                            recording
                        </span>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {cameras.map((camera) => (
                            <Card key={camera.id} className="gap-0 overflow-hidden py-0">
                                <div className="relative aspect-video bg-black">
                                    <img
                                        src={camera.snapshot ?? '/images/cctv-placeholder.svg'}
                                        alt=""
                                        className={`size-full object-cover ${camera.status === 'offline' ? 'grayscale' : ''}`}
                                    />
                                    {camera.status === 'offline' ? (
                                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/60">
                                            <span className="text-xs font-semibold tracking-[0.3em] text-zinc-300">
                                                NO SIGNAL
                                            </span>
                                            <span className="text-[11px] text-zinc-400">Camera offline</span>
                                        </div>
                                    ) : null}
                                    <div className="absolute inset-x-0 top-0 flex items-center justify-between p-2">
                                        <span className="flex items-center gap-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white">
                                            <span
                                                className={`size-1.5 rounded-full ${
                                                    camera.status === 'online'
                                                        ? 'animate-pulse bg-red-500'
                                                        : 'bg-zinc-500'
                                                }`}
                                            />
                                            {camera.status === 'online' ? 'LIVE' : 'OFFLINE'}
                                        </span>
                                        {camera.recording ? (
                                            <span className="flex items-center gap-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white">
                                                <span className="size-1.5 rounded-full bg-red-500" />
                                                REC
                                            </span>
                                        ) : null}
                                    </div>
                                    <span className="absolute bottom-2 left-2 rounded bg-black/60 px-1.5 py-0.5 font-mono text-[11px] text-zinc-200">
                                        2026-09-30 10:24:05
                                    </span>
                                </div>
                                <CardContent className="px-4 py-3">
                                    <p className="text-sm font-medium">{camera.name}</p>
                                    <p className="text-xs text-muted-foreground">{camera.location}</p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </TabsContent>

                <TabsContent value="alarms" className="space-y-3">
                    <div className="flex h-9 w-full items-center justify-center rounded-lg bg-muted p-1">
                        <div className="flex items-center justify-center gap-1.5 rounded-md bg-background px-3 py-1 text-sm font-medium shadow-sm">
                            <h2>Active alarms</h2>
                            <Badge variant="destructive" className="px-1.5 py-0 text-xs">
                                {alarms.filter((a) => a.severity !== 'info').length}
                            </Badge>
                        </div>
                    </div>
                    {alarms.map((alarm) => {
                        const badge = severityBadge[alarm.severity];

                        return (
                            <Card key={alarm.id}>
                                <CardContent className="flex flex-col gap-2 py-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <Badge className={badge.className}>{badge.label}</Badge>
                                            <Badge variant="outline">{alarmSourceLabels[alarm.source]}</Badge>
                                            <span className="font-medium">{alarm.title}</span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">{alarm.message}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {alarm.sensor} · {alarm.location} · {alarm.time}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </TabsContent>
            </Tabs>
        </div>
    );
}
