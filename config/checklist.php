<?php

/*
|--------------------------------------------------------------------------
| Checklist definitions
|--------------------------------------------------------------------------
|
| The 13 infrastructure checklist categories mirror the daily log workbook.
| Each category carries its own time slots and a flat list of items. Items
| are grouped for display by `section` then `group`; both are optional so a
| simple category can leave them null.
|
| The ChecklistSeeder turns this definition into checklist_categories and
| checklist_items rows with updateOrCreate, so editing an item here and
| re-running the seeder keeps existing entries intact.
|
*/

$fiveSlots = ['09:00', '14:00', '17:00', '21:00', '06:00'];
$twoSlots = ['09:00', '21:00'];

$number = fn (string $label, string $unit = ''): array => [
    'label' => $label,
    'unit' => $unit !== '' ? $unit : null,
    'input_type' => 'number',
];

$status = fn (string $label): array => [
    'label' => $label,
    'unit' => null,
    'input_type' => 'status',
];

$reading = fn (string $label, string $unit = ''): array => [
    'label' => $label,
    'unit' => $unit !== '' ? $unit : null,
    'input_type' => 'number',
];

$item = fn (?string $section, ?string $group, array $definition): array => [
    'section' => $section !== '' ? $section : null,
    'group' => $group !== '' ? $group : null,
    ...$definition,
];

/*
| PAC & AC
*/
$pacRooms = [
    'P A C IT Hall Lantai 3' => [
        ['PAC-3.1 (Wing Barat)', true],
        ['PAC-3.3 (Wing Barat)', true],
        ['PAC-3.4 (Wing Barat)', true],
        ['PAC-3.8 (Wing Barat)', true],
        ['PAC-3.5 (Wing Timur)', true],
        ['PAC-3.6 (Wing Timur)', true],
        ['PAC-3.7 (Wing Timur)', true],
    ],
    'P A C IT Hall Lantai 4' => [
        ['PAC-4.1', true],
        ['PAC-4.2', true],
        ['PAC-4.3', true],
        ['PAC-4.4', true],
        ['PAC-4.5', true],
        ['PAC-4.6', true],
    ],
    'A C Ruang MMR Lantai 4' => [
        ['AC-MMR4.1', false],
        ['AC-MMR4.2', false],
        ['AC-MMR-4.3', false],
    ],
    'P A C IT Hall Lantai 2' => [
        ['PAC-2.1', true],
        ['PAC-2.2', true],
        ['PAC-2.3', true],
        ['PAC-2.4', true],
        ['PAC-2.5', true],
        ['PAC-2.6', true],
    ],
    'A C Ruang MMR Lantai 2' => [
        ['AC-MMR2.1', false],
        ['AC-MMR2.2', false],
    ],
    'A C Ruang Utility (UPS) 2 Lantai 1' => [
        ['AC-UPS2-1', false],
        ['AC-UPS2-2', false],
    ],
    'A C Ruang Utility (UPS) A&B Lantai 1' => [
        ['AC-UPSA-1', false],
        ['AC-UPSA-2', false],
        ['AC-UPSA-3', false],
        ['AC-UPSA-4', false],
        ['AC-UPSA-5', false],
    ],
    'A C Ruang Utility (BATTERY) A&B Lantai 1' => [
        ['AC-BATT-1', false],
        ['AC-BATT-2', false],
    ],
    'A C Ruang Interconnection (NER)' => [
        ['AC-NER.1', false],
    ],
];

$pacItems = [];

foreach ($pacRooms as $section => $groups) {
    foreach ($groups as [$group, $hasHumidity]) {
        $pacItems[] = $item($section, $group, $number('Temperatur', '°C'));

        if ($hasHumidity) {
            $pacItems[] = $item($section, $group, $number('Humidity', '%'));
        }

        $pacItems[] = $item($section, $group, $status('Indoor'));
        $pacItems[] = $item($section, $group, $status('Outdoor blower'));
    }
}

/*
| Ruangan
*/
$ruanganGroups = [
    'DATA CENTER LANTAI 3 WING BARAT',
    'DATA CENTER LANTAI 3 WING TIMUR',
    'DATA CENTER LANTAI 4 NEUCENTRIX',
    'MMR LANTAI 4',
    'DATA CENTER LANTAI 2 NEUCENTRIX',
    'MMR LANTAI 2.1',
    'MMR LANTAI 2.2',
    'RUANG UPS 2 LANTAI 1',
    'RUANG UPS A & B',
    'RUANG BATTERY UPS A & B',
    'RUANG NER',
];

$ruanganItems = [];

foreach ($ruanganGroups as $group) {
    $ruanganItems[] = $item(null, $group, $number('Temperatur', '°C'));
    $ruanganItems[] = $item(null, $group, $number('Humidity', '%'));
}

/*
| LOAD DC
*/
$loadGroups = ['MDP - A', 'MDP - B', 'MDP - C', 'MDP - D', 'MDP - E'];

$loadItems = [];

foreach ($loadGroups as $group) {
    foreach (['Fase R', 'Fase S', 'Fase T'] as $phase) {
        $loadItems[] = $item('Pengecekan Load Ampere (Amp)', $group, $number($phase, 'A'));
    }
}

/*
| UPS
*/
$upsSections = [
    'a. Voltage IN-UPS' => [['VS', 'V'], ['VT', 'V']],
    'a. Voltage OUT-UPS' => [['VS', 'V'], ['VT', 'V']],
    'b. Ampere IN-UPS' => [['IR', 'A'], ['IS', 'A'], ['IT', 'A']],
    'c. Ampere OUT-UPS' => [['IR', 'A'], ['IS', 'A'], ['IT', 'A']],
    'c Prosentage Power UPS' => [['R', '%'], ['S', '%'], ['T', '%']],
    'Frequency UPS' => [['Frequency', 'Hz']],
    'Battery UPS' => [
        ['Capacity', '%'],
        ['Autonomy Time (Hours)', 'Jam'],
        ['Minutes', 'Menit'],
    ],
    'TEMP' => [['Temperature', '°C']],
];

$upsItemsFor = function (string $device) use ($upsSections, $item, $number): array {
    $items = [];

    foreach ($upsSections as $section => $fields) {
        foreach ($fields as [$label, $unit]) {
            $items[] = $item($section, $device, $number($label, $unit));
        }
    }

    return $items;
};

/*
| Fire protection (FAP)
*/
$fapItems = [
    'fap-nn100-lt1' => [
        ['Fire Protection System NN100 Lt.1', $status('Status Control Panel')],
        ['Fire Protection System NN100 Lt.1', $reading('Pilot', 'MPa')],
        ['Fire Protection System NN100 Lt.1', $reading('Storage', 'MPa')],
        ['Fire Protection System NN100 Lt.1', $reading('Temp', '°C')],
    ],
    'fap-ig55-lt2' => [
        ['Fire Protection System Inert Gas Lt.2', $status('Status Control Panel')],
        ['Fire Protection System Inert Gas Lt.2', $reading('Storage', 'bar')],
        ['Fire Protection System Inert Gas Lt.2', $reading('Temp', '°C')],
    ],
    'fap-nn100-lt4' => [
        ['Fire Protection System NN100 Lt.4', $status('Status Control Panel')],
        ['Fire Protection System NN100 Lt.4', $reading('Pilot', 'MPa')],
        ['Fire Protection System NN100 Lt.4', $reading('Storage', 'MPa')],
        ['Fire Protection System NN100 Lt.4', $reading('Temp', '°C')],
    ],
    'fap-fm200' => [
        ['Fire Protection System FM200 Lt.3', $status('Status Control Panel')],
        ['Fire Protection System FM200 Lt.3', $status('Kondisi Tabung')],
        ['Fire Protection System FM200 Lt.3', $reading('Temp', '°C')],
    ],
];

$fapItemsFor = function (string $slug) use ($fapItems, $item): array {
    return array_map(
        fn (array $definition): array => $item(null, $definition[0], $definition[1]),
        $fapItems[$slug],
    );
};

/*
| CCTV
*/
$cctvItems = [
    'CAMERA',
    'DVR 1',
    'DVR 2',
    'DVR 3',
    'DVR 4',
    'DVR 5',
    'BACK UP',
];

$cctvItemsFor = fn (): array => array_map(
    fn (string $label): array => $item(null, 'CCTV', $status($label)),
    $cctvItems,
);

/*
| APAR
*/
$aparItems = [];

foreach (['LANTAI 1', 'LANTAI 2', 'LANTAI 3', 'LANTAI 4'] as $floor) {
    $aparItems[] = $item('APAR (ALAT PEMADAM API RINGAN)', $floor, $status('TABUNG'));
    $aparItems[] = $item('APAR (ALAT PEMADAM API RINGAN)', $floor, $status('PRESSURE'));
}

return [
    'categories' => [
        [
            'slug' => 'pac-ac',
            'name' => 'PAC & AC',
            'time_slots' => $fiveSlots,
            'sort_order' => 1,
            'items' => $pacItems,
        ],
        [
            'slug' => 'ruangan',
            'name' => 'Ruangan',
            'time_slots' => $fiveSlots,
            'sort_order' => 2,
            'items' => $ruanganItems,
        ],
        [
            'slug' => 'load-dc',
            'name' => 'LOAD DC LEMBONG',
            'time_slots' => $fiveSlots,
            'sort_order' => 3,
            'items' => $loadItems,
        ],
        [
            'slug' => 'ups-2',
            'name' => 'UPS 2',
            'time_slots' => $fiveSlots,
            'sort_order' => 4,
            'items' => $upsItemsFor('UPS 2 NEWAVE 6 x 40 KVA'),
        ],
        [
            'slug' => 'ups-a',
            'name' => 'UPS A',
            'time_slots' => $fiveSlots,
            'sort_order' => 5,
            'items' => $upsItemsFor('UPS A NEWAVE 8 x 50 KVA'),
        ],
        [
            'slug' => 'ups-b',
            'name' => 'UPS B',
            'time_slots' => $fiveSlots,
            'sort_order' => 6,
            'items' => $upsItemsFor('UPS B NEWAVE 6 x 50 KVA'),
        ],
        [
            'slug' => 'ups-c',
            'name' => 'UPS C',
            'time_slots' => $fiveSlots,
            'sort_order' => 7,
            'items' => $upsItemsFor('UPS C NEWAVE 6 x 50 KVA'),
        ],
        [
            'slug' => 'fap-nn100-lt1',
            'name' => 'FAP NN100 Lt.1',
            'time_slots' => $twoSlots,
            'sort_order' => 8,
            'items' => $fapItemsFor('fap-nn100-lt1'),
        ],
        [
            'slug' => 'fap-ig55-lt2',
            'name' => 'FAP IG55 Lt.2',
            'time_slots' => $twoSlots,
            'sort_order' => 9,
            'items' => $fapItemsFor('fap-ig55-lt2'),
        ],
        [
            'slug' => 'fap-nn100-lt4',
            'name' => 'FAP NN100 Lt.4',
            'time_slots' => $twoSlots,
            'sort_order' => 10,
            'items' => $fapItemsFor('fap-nn100-lt4'),
        ],
        [
            'slug' => 'fap-fm200',
            'name' => 'FAP FM200',
            'time_slots' => $twoSlots,
            'sort_order' => 11,
            'items' => $fapItemsFor('fap-fm200'),
        ],
        [
            'slug' => 'cctv',
            'name' => 'CCTV',
            'time_slots' => $twoSlots,
            'sort_order' => 12,
            'items' => $cctvItemsFor(),
        ],
        [
            'slug' => 'apar',
            'name' => 'APAR',
            'time_slots' => $twoSlots,
            'sort_order' => 13,
            'items' => $aparItems,
        ],
    ],
];
