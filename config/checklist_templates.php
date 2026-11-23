<?php

/*
|--------------------------------------------------------------------------
| Asset checklist templates
|--------------------------------------------------------------------------
|
| Assets flagged in_checklist get their checklist_items materialised from
| the templates below, keyed by the ROOT (top-level) asset category. A
| sub-category such as "Modul 1" (under "UPS Frame", under "UPS") uses the
| root "UPS" template.
|
| "mappings" links each existing checklist category (by slug) to the asset
| categories that feed it. One checklist category can collect several
| asset categories, and an asset category belongs to exactly one checklist
| category.
|
*/

$status = fn (string $label, ?string $section = null): array => [
    'section' => $section,
    'label' => $label,
    'unit' => null,
    'input_type' => 'status',
];

$number = fn (string $label, string $unit = '', ?string $section = null): array => [
    'section' => $section,
    'label' => $label,
    'unit' => $unit !== '' ? $unit : null,
    'input_type' => 'number',
];

return [
    'mappings' => [
        'pac-ac' => ['PAC', 'AC'],
        'ruangan' => ['EMS', 'Sensor TH', 'Network', 'Akses Pintu', 'Monitor', 'PC', 'Server'],
        'load-dc' => ['Panel Power', 'Rectifire', 'Genset'],
        'ups-2' => ['UPS'],
        'cctv' => ['Kamera', 'NVR', 'DVR'],
        'apar' => ['APAR'],
        'fap-fm200' => [
            'FSS Panel',
            'Smoke Detector',
            'Tabung FSS',
            'Evacuated Lamp',
            'FSS Horn',
            'FSS Siren',
            'FSS Manual Release',
            'FSS Manual Abort',
        ],
    ],

    'templates' => [
        'PAC' => [
            $number('Temperature', '°C'),
            $number('Humidity', '%'),
            $status('Indoor'),
            $status('Outdoor blower'),
        ],
        'AC' => [
            $number('Temperature', '°C'),
            $status('Indoor'),
            $status('Outdoor blower'),
        ],
        'Sensor TH' => [
            $number('Temperature', '°C'),
            $number('Humidity', '%'),
        ],
        'EMS' => [$status('Status')],
        'Network' => [$status('Status')],
        'Akses Pintu' => [$status('Status')],
        'Monitor' => [$status('Status')],
        'PC' => [$status('Status')],
        'Server' => [$status('Status')],
        'Panel Power' => [
            $number('Fase R', 'A', 'Pengecekan Load Ampere (Amp)'),
            $number('Fase S', 'A', 'Pengecekan Load Ampere (Amp)'),
            $number('Fase T', 'A', 'Pengecekan Load Ampere (Amp)'),
        ],
        'Rectifire' => [
            $number('Voltage', 'V'),
            $number('Current', 'A'),
        ],
        'Genset' => [
            $status('Status'),
            $number('Fuel Level', '%'),
            $number('Temperature', '°C'),
        ],
        'UPS' => [
            $number('VR', 'V', 'a. Voltage IN-UPS'),
            $number('VS', 'V', 'a. Voltage IN-UPS'),
            $number('VT', 'V', 'a. Voltage IN-UPS'),
            $number('VR', 'V', 'a. Voltage OUT-UPS'),
            $number('VS', 'V', 'a. Voltage OUT-UPS'),
            $number('VT', 'V', 'a. Voltage OUT-UPS'),
            $number('IR', 'A', 'b. Ampere IN-UPS'),
            $number('IS', 'A', 'b. Ampere IN-UPS'),
            $number('IT', 'A', 'b. Ampere IN-UPS'),
            $number('IR', 'A', 'c. Ampere OUT-UPS'),
            $number('IS', 'A', 'c. Ampere OUT-UPS'),
            $number('IT', 'A', 'c. Ampere OUT-UPS'),
            $number('R', '%', 'c. Prosentage Power UPS'),
            $number('S', '%', 'c. Prosentage Power UPS'),
            $number('T', '%', 'c. Prosentage Power UPS'),
            $number('Frequency', 'Hz', 'Frequency UPS'),
            $number('Capacity', '%', 'Battery UPS'),
            $number('Autonomy Time', 'Jam', 'Battery UPS'),
            $number('Minutes', 'Menit', 'Battery UPS'),
            $number('Temperature', '°C', 'TEMP'),
        ],
        'Kamera' => [$status('Status')],
        'NVR' => [$status('Status')],
        'DVR' => [$status('Status')],
        'APAR' => [
            $status('Kondisi Tabung'),
            $status('Pressure'),
        ],
        'FSS Panel' => [$status('Status Control Panel')],
        'Smoke Detector' => [$status('Status')],
        'Tabung FSS' => [
            $status('Kondisi Tabung'),
            $number('Pressure', 'bar'),
        ],
        'Evacuated Lamp' => [$status('Status')],
        'FSS Horn' => [$status('Status')],
        'FSS Siren' => [$status('Status')],
        'FSS Manual Release' => [$status('Status')],
        'FSS Manual Abort' => [$status('Status')],
    ],
];
