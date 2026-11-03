export type SensorReading = {
    time: string;
    temperature: number;
    humidity: number;
    airflow: number;
};

export type SensorSource = 'raritan' | 'nti';

export type AlarmSource = 'raritan' | 'nti' | 'cctv';

export type Sensor = {
    id: number;
    name: string;
    type: 'temperature' | 'humidity' | 'airflow' | 'water' | 'contact' | 'smoke' | 'power' | 'motion';
    location: string;
    value: number;
    unit: string;
    threshold: string;
    status: 'normal' | 'warning' | 'critical' | 'offline';
    source: SensorSource;
};

export type Alarm = {
    id: number;
    title: string;
    sensor: string;
    location: string;
    severity: 'critical' | 'warning' | 'info';
    message: string;
    time: string;
    source: AlarmSource;
};

export const sensorSourceLabels: Record<SensorSource, string> = {
    raritan: 'Raritan (EMS & EMX)',
    nti: 'NTI (ENVIROMUX)',
};

export const alarmSourceLabels: Record<AlarmSource, string> = {
    raritan: 'Raritan',
    nti: 'NTI',
    cctv: 'CCTV',
};

export type SummaryCard = {
    label: string;
    value: string;
    unit: string;
    status: 'normal' | 'warning' | 'critical';
    detail: string;
};

export const summaryCards: SummaryCard[] = [
    {
        label: 'Temperature',
        value: '24.5',
        unit: '°C',
        status: 'normal',
        detail: 'Avg inlet · ASHRAE A1 compliant',
    },
    {
        label: 'Humidity',
        value: '48',
        unit: '%RH',
        status: 'normal',
        detail: 'Avg relative humidity',
    },
    {
        label: 'Airflow',
        value: '1.8',
        unit: 'm/s',
        status: 'warning',
        detail: 'Cold aisle · below 2.0 m/s target',
    },
    {
        label: 'Water Leak',
        value: 'OK',
        unit: '',
        status: 'normal',
        detail: 'No leak detected in 8 zones',
    },
    {
        label: 'Cameras',
        value: '11/12',
        unit: 'online',
        status: 'warning',
        detail: '1 camera offline · 6 recording',
    },
];

export const readings: SensorReading[] = [
    { time: '00:00', temperature: 23.8, humidity: 46, airflow: 2.1 },
    { time: '02:00', temperature: 23.6, humidity: 47, airflow: 2.2 },
    { time: '04:00', temperature: 23.5, humidity: 47, airflow: 2.2 },
    { time: '06:00', temperature: 23.9, humidity: 46, airflow: 2.1 },
    { time: '08:00', temperature: 24.4, humidity: 48, airflow: 2.0 },
    { time: '10:00', temperature: 25.1, humidity: 49, airflow: 1.9 },
    { time: '12:00', temperature: 25.6, humidity: 51, airflow: 1.8 },
    { time: '14:00', temperature: 25.9, humidity: 52, airflow: 1.7 },
    { time: '16:00', temperature: 25.4, humidity: 50, airflow: 1.8 },
    { time: '18:00', temperature: 24.8, humidity: 49, airflow: 1.9 },
    { time: '20:00', temperature: 24.3, humidity: 48, airflow: 2.0 },
    { time: '22:00', temperature: 24.0, humidity: 47, airflow: 2.1 },
];

export const sensors: Sensor[] = [
    { id: 1, name: 'T-01 Inlet Top', type: 'temperature', location: 'Rack A-01', value: 24.2, unit: '°C', threshold: '≤ 27 °C', status: 'normal', source: 'raritan' },
    { id: 2, name: 'T-02 Inlet Middle', type: 'temperature', location: 'Rack A-01', value: 25.8, unit: '°C', threshold: '≤ 27 °C', status: 'normal', source: 'raritan' },
    { id: 3, name: 'T-03 Inlet Bottom', type: 'temperature', location: 'Rack A-01', value: 27.4, unit: '°C', threshold: '≤ 27 °C', status: 'critical', source: 'raritan' },
    { id: 4, name: 'T-04 Outlet', type: 'temperature', location: 'Rack A-02', value: 34.1, unit: '°C', threshold: '≤ 40 °C', status: 'normal', source: 'raritan' },
    { id: 5, name: 'H-01 Humidity Cold Aisle', type: 'humidity', location: 'Cold Aisle 1', value: 48, unit: '%RH', threshold: '40 – 60 %RH', status: 'normal', source: 'raritan' },
    { id: 6, name: 'H-02 Humidity Hot Aisle', type: 'humidity', location: 'Hot Aisle 1', value: 52, unit: '%RH', threshold: '40 – 60 %RH', status: 'normal', source: 'raritan' },
    { id: 7, name: 'AF-01 Floor Perforated', type: 'airflow', location: 'Cold Aisle 1', value: 1.6, unit: 'm/s', threshold: '≥ 2.0 m/s', status: 'warning', source: 'raritan' },
    { id: 8, name: 'AF-02 Ceiling Return', type: 'airflow', location: 'Hot Aisle 1', value: 2.3, unit: 'm/s', threshold: '≥ 1.5 m/s', status: 'normal', source: 'raritan' },
    { id: 9, name: 'W-01 Water Rope', type: 'water', location: 'CRAC Unit 1', value: 0, unit: '', threshold: 'Leak = 0', status: 'normal', source: 'raritan' },
    { id: 10, name: 'W-02 Water Rope', type: 'water', location: 'CRAC Unit 2', value: 1, unit: '', threshold: 'Leak = 0', status: 'critical', source: 'raritan' },
    { id: 11, name: 'DC-01 Door Contact', type: 'contact', location: 'Rack A-01', value: 1, unit: '', threshold: 'Closed', status: 'warning', source: 'raritan' },
    { id: 12, name: 'DC-02 Door Contact', type: 'contact', location: 'Rack A-02', value: 0, unit: '', threshold: 'Closed', status: 'normal', source: 'raritan' },
    { id: 13, name: 'NTI Smoke Detector', type: 'smoke', location: 'Server Room B', value: 0, unit: '', threshold: 'Smoke = 0', status: 'normal', source: 'nti' },
    { id: 14, name: 'NTI Water Detection', type: 'water', location: 'UPS Room', value: 0, unit: '', threshold: 'Leak = 0', status: 'normal', source: 'nti' },
    { id: 15, name: 'NTI Power Status', type: 'power', location: 'UPS Room', value: 1, unit: '', threshold: 'Utility OK', status: 'normal', source: 'nti' },
    { id: 16, name: 'NTI Motion Sensor', type: 'motion', location: 'Server Room B', value: 0, unit: '', threshold: 'No motion', status: 'normal', source: 'nti' },
    { id: 17, name: 'NTI Door Contact', type: 'contact', location: 'Server Room B', value: 0, unit: '', threshold: 'Closed', status: 'normal', source: 'nti' },
    { id: 18, name: 'NTI Temp Probe', type: 'temperature', location: 'Generator Room', value: 31.2, unit: '°C', threshold: '≤ 35 °C', status: 'warning', source: 'nti' },
];

export const alarms: Alarm[] = [
    {
        id: 1,
        title: 'Water leak detected',
        sensor: 'W-02 Water Rope',
        location: 'CRAC Unit 2',
        severity: 'critical',
        message: 'Leak rope reports liquid contact. Inspect CRAC Unit 2 drainage immediately.',
        time: '2026-09-30 09:42',
        source: 'raritan',
    },
    {
        id: 2,
        title: 'Temperature above threshold',
        sensor: 'T-03 Inlet Bottom',
        location: 'Rack A-01',
        severity: 'critical',
        message: 'Inlet temperature 27.4 °C exceeds 27 °C limit at rack bottom.',
        time: '2026-09-30 09:31',
        source: 'raritan',
    },
    {
        id: 3,
        title: 'Airflow below target',
        sensor: 'AF-01 Floor Perforated',
        location: 'Cold Aisle 1',
        severity: 'warning',
        message: 'Airflow 1.6 m/s is below the 2.0 m/s cooling target.',
        time: '2026-09-30 08:57',
        source: 'raritan',
    },
    {
        id: 4,
        title: 'Cabinet door open',
        sensor: 'DC-01 Door Contact',
        location: 'Rack A-01',
        severity: 'warning',
        message: 'Front door has been open for more than 5 minutes.',
        time: '2026-09-30 08:12',
        source: 'raritan',
    },
    {
        id: 5,
        title: 'Sensor hub reconnected',
        sensor: 'EMX2-111 Controller',
        location: 'Rack B-04',
        severity: 'info',
        message: 'EMX controller reconnected after 30 seconds of packet loss.',
        time: '2026-09-30 07:05',
        source: 'raritan',
    },
    {
        id: 6,
        title: 'Camera offline',
        sensor: 'CAM-07 CRAC Area',
        location: 'CRAC Area',
        severity: 'warning',
        message: 'Camera has been unreachable for 4 minutes. Check the PoE switch port.',
        time: '2026-09-30 09:05',
        source: 'cctv',
    },
    {
        id: 7,
        title: 'Generator room temperature high',
        sensor: 'NTI Temp Probe',
        location: 'Generator Room',
        severity: 'warning',
        message: 'Temperature 31.2 °C is approaching the 35 °C limit in the generator room.',
        time: '2026-09-30 08:44',
        source: 'nti',
    },
];
