<?php

/*
|--------------------------------------------------------------------------
| Asset list sheet presets
|--------------------------------------------------------------------------
|
| The Asset List page mirrors the column layout of the source workbook
| (ASET PER LANTAI dan kategory neuCentrIX Lembong). Each sheet is a
| preset: picking it in the dropdown swaps the table header. "General"
| follows the ASET PER LANTAI sheet; Sheet1 is intentionally left out
| because it only lists PAC/AC names, not assets.
|
| "categories" filters the list to an asset category subtree. An empty
| list means "all categories" (General). Categories listed on a
| non-General sheet are managed from that sheet; General only displays
| them read-only.
|
| "storage" maps a column key to a column on the assets table. Keys not
| listed are stored in the assets.details JSON column.
|
| Every sheet has a Lantai column so the overview can group assets by
| floor later, plus a Catatan column stored in the shared notes column.
|
*/

$columns = fn (array $map): array => collect($map)
    ->map(fn (string $label, string $key): array => [
        'key' => $key,
        'label' => $label,
    ])
    ->values()
    ->all();

return [
    'default' => 'general',

    /*
    | Columns generated on the fly instead of read from the database.
    | "kategori" shows the category path and "keterangan" is composed from
    | the asset name plus its category path.
    */
    'computed' => ['kategori', 'keterangan', 'jumlah_kamera', 'perangkat'],

    'sheets' => [
        'general' => [
            'label' => 'GENERAL',
            'categories' => [],
            'columns' => $columns([
                'nama_perangkat' => 'Nama Perangkat',
                'kategori' => 'Kategori',
                'qty' => 'QTY',
                'alias' => 'Alias',
                'merek' => 'Merek',
                'type' => 'Type',
                'serial_number' => 'Serial Number',
                'model' => 'Model',
                'kapasitas' => 'Kapasitas',
                'tahun_operasi' => 'Tahun Operasi',
                'tahun_manufaktur' => 'Tahun Manufaktur',
                'ruang' => 'Ruang',
                'lantai' => 'Lantai',
                'milik' => 'Milik',
                'ip_address' => 'IP Address',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'nama_perangkat' => 'name',
                'qty' => 'quantity',
                'alias' => 'alias',
                'merek' => 'brand',
                'type' => 'type',
                'serial_number' => 'serial_number',
                'model' => 'model',
                'kapasitas' => 'capacity',
                'tahun_operasi' => 'operation_year',
                'tahun_manufaktur' => 'manufacture_year',
                'ruang' => 'room',
                'lantai' => 'floor',
                'milik' => 'owner',
                'ip_address' => 'ip_address',
                'catatan' => 'notes',
            ],
            'required' => ['nama_perangkat'],
        ],

        'ups-batt' => [
            'label' => 'UPS & BATTERY',
            'categories' => ['UPS'],
            'columns' => $columns([
                'nama' => 'Nama',
                'merek' => 'Merek',
                'kapasitas_kva' => 'Kapasitas KVA',
                'frame_nomor' => 'Frame - Nomor',
                'frame_tahun' => 'Frame - Tahun',
                'frame_sn' => 'Frame - SN',
                'modul_nomor' => 'Modul - Nomor',
                'modul_sn' => 'Modul - SN',
                'modul_kva' => 'Modul - KVA',
                'modul_tahun' => 'Modul - Tahun',
                'batterai_bank_no' => 'Batterai - Bank No',
                'batterai_merek' => 'Batterai - Merek',
                'batterai_v_ah' => 'Batterai - V / AH',
                'batterai_model' => 'Batterai - Model',
                'batterai_series' => 'Batterai - Series',
                'batterai_jumlah' => 'Batterai - Jumlah',
                'batterai_tahun_prod' => 'Batterai - Tahun Prod',
                'batterai_tahun_pakai' => 'Batterai - Tahun Pakai',
                'lantai' => 'Lantai',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'nama' => 'name',
                'merek' => 'brand',
                'kapasitas_kva' => 'capacity',
                'lantai' => 'floor',
                'catatan' => 'notes',
            ],
            'required' => ['nama'],
            'form_groups' => [
                'UPS Frame' => ['frame_nomor', 'frame_tahun', 'frame_sn'],
                'UPS Modul' => ['modul_nomor', 'modul_sn', 'modul_kva', 'modul_tahun'],
                'Battery' => [
                    'batterai_bank_no',
                    'batterai_merek',
                    'batterai_v_ah',
                    'batterai_model',
                    'batterai_series',
                    'batterai_jumlah',
                    'batterai_tahun_prod',
                    'batterai_tahun_pakai',
                ],
                'UPS Electronic' => [],
            ],
            /*
            | Keywords matched against every selected category level (case
            | insensitive) to decide which extra fields appear. "UPS Frame 1"
            | matches "frame", "Modul 2" matches "modul", and so on.
            */
            'form_group_keywords' => [
                'UPS Frame' => ['frame'],
                'UPS Modul' => ['modul'],
                'Battery' => ['battery', 'baterai', 'batterai'],
                'UPS Electronic' => ['electronic'],
            ],
            /*
            | A category whose name contains one of these keywords is treated
            | as a battery category. The sheet then renders two tables: one
            | for UPS assets and one for the battery assets linked to them.
            */
            'battery_keywords' => ['battery', 'baterai', 'batterai'],
            'tables' => [
                'ups' => [
                    'label' => 'UPS',
                    'columns' => [
                        'nama',
                        'merek',
                        'kapasitas_kva',
                        'frame_nomor',
                        'frame_tahun',
                        'frame_sn',
                        'modul_nomor',
                        'modul_sn',
                        'modul_kva',
                        'modul_tahun',
                        'lantai',
                        'keterangan',
                        'catatan',
                    ],
                    /*
                    | Columns rendered once per UPS group with rowSpan. The
                    | identity columns span every frame of the same UPS, while
                    | the shared frame columns span the modules of one frame.
                    */
                    'identity' => [
                        'nama',
                        'merek',
                        'kapasitas_kva',
                    ],
                    'shared' => [
                        'frame_nomor',
                        'frame_tahun',
                        'frame_sn',
                    ],
                ],
                'battery' => [
                    'label' => 'BATTERY',
                    'columns' => [
                        'nama',
                        'merek',
                        'kapasitas_kva',
                        'batterai_bank_no',
                        'batterai_merek',
                        'batterai_v_ah',
                        'batterai_model',
                        'batterai_series',
                        'batterai_jumlah',
                        'batterai_tahun_prod',
                        'batterai_tahun_pakai',
                        'keterangan',
                        'catatan',
                    ],
                    /*
                    | Columns rendered once per UPS group with rowSpan; the
                    | values come from the parent UPS asset.
                    */
                    'shared' => ['nama', 'merek', 'kapasitas_kva'],
                ],
            ],
        ],

        'fap' => [
            'label' => 'FAP',
            'categories' => ['FSS Panel'],
            /*
            | One panel owns several rows. The panel identity columns render
            | once with rowSpan; the rest (system, zone, tube, etc.) stay per
            | row.
            */
            'row_group' => [
                'group_by' => ['panel'],
                'identity' => ['lantai', 'panel', 'merek', 'model'],
            ],
            'columns' => $columns([
                'lantai' => 'Lantai',
                'panel' => 'Panel',
                'merek' => 'Merek',
                'model' => 'Model',
                'system' => 'System',
                'jumlah_tabung' => 'Jumlah Tabung',
                'kapasitas' => 'Kapasitas',
                'zona' => 'Zona',
                'nama_zona' => 'Nama Zona',
                'tabung_merek' => 'Tabung Merek',
                'manual_release' => 'Manual Release',
                'manual_abort' => 'Manual Abort',
                'speaker_horn_bell' => 'Speaker / Horn / Bell',
                'discharge_indicator_lamp' => 'Discharge Indicator Lamp',
                'fire_detector_name' => 'Fire Detector Name',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'lantai' => 'floor',
                'panel' => 'name',
                'merek' => 'brand',
                'model' => 'model',
                'kapasitas' => 'capacity',
                'catatan' => 'notes',
            ],
            'required' => ['panel'],
        ],

        'ems' => [
            'label' => 'EMS',
            'categories' => ['EMS', 'Sensor TH'],
            /*
            | One device (Nama Perangkat) owns several sensor rows. The device
            | columns render once with rowSpan; sensor columns stay per row.
            */
            'row_group' => [
                'group_by' => ['nama_perangkat'],
                'identity' => [
                    'lantai_perangkat',
                    'nama_perangkat',
                    'qty',
                    'alias',
                    'ip_address',
                    'merek',
                    'type',
                    'serial_number',
                    'model',
                    'kapasitas',
                    'tahun_operasi',
                    'tahun_manufaktur',
                    'ruang',
                ],
            ],
            'columns' => $columns([
                'lantai_perangkat' => 'Lantai Perangkat',
                'nama_perangkat' => 'Nama Perangkat',
                'qty' => 'QTY',
                'alias' => 'Alias',
                'ip_address' => 'IP Address',
                'merek' => 'Merek',
                'type' => 'Type',
                'serial_number' => 'Serial Number',
                'model' => 'Model',
                'kapasitas' => 'Kapasitas',
                'tahun_operasi' => 'Tahun Operasi',
                'tahun_manufaktur' => 'Tahun Manufaktur',
                'ruang' => 'Ruang',
                'milik' => 'Milik',
                'nama_sensor' => 'Nama Sensor',
                'jenis_sensor' => 'Jenis Sensor',
                'lantai_sensor' => 'Lantai Sensor',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'lantai_perangkat' => 'floor',
                'nama_perangkat' => 'name',
                'qty' => 'quantity',
                'alias' => 'alias',
                'ip_address' => 'ip_address',
                'merek' => 'brand',
                'type' => 'type',
                'serial_number' => 'serial_number',
                'model' => 'model',
                'kapasitas' => 'capacity',
                'tahun_operasi' => 'operation_year',
                'tahun_manufaktur' => 'manufacture_year',
                'ruang' => 'room',
                'milik' => 'owner',
                'catatan' => 'notes',
            ],
            'required' => ['nama_perangkat'],
            'counts' => ['lantai_sensor'],
        ],

        'cctv' => [
            'label' => 'CCTV',
            'categories' => ['DVR', 'NVR', 'Kamera'],
            /*
            | Camera channels of a DVR/NVR are stored as their own assets in
            | this category, linked to the device through parent_asset_id.
            */
            'camera_category' => 'Kamera',
            /*
            | Two stacked tables, mirroring the UPS & Battery sheet: one row
            | per DVR/NVR device, plus one table for the camera channels. The
            | camera table repeats the device columns once per device group.
            */
            'tables' => [
                'device' => [
                    'label' => 'Perangkat CCTV',
                    'columns' => [
                        'nama',
                        'kategori',
                        'merek_perangkat',
                        'alias_perangkat',
                        'ip_perangkat',
                        'type',
                        'serial_number',
                        'model',
                        'tahun_operasi',
                        'kapasitas_hd',
                        'tahun_manufaktur',
                        'ruang',
                        'lantai_perangkat',
                        'jumlah_kamera',
                        'keterangan',
                        'catatan',
                    ],
                    'identity' => ['nama', 'kategori'],
                ],
                'camera' => [
                    'label' => 'Kamera',
                    /*
                    | The asset name is derived from the camera alias, so the
                    | Nama column would only repeat Alias (Kamera). It stays in
                    | the column list (and row values) for the edit form.
                    */
                    'hidden_columns' => ['nama'],
                    'columns' => [
                        'perangkat',
                        'kategori',
                        'merek_perangkat',
                        'ip_perangkat',
                        'ruang',
                        'lantai_perangkat',
                        'nama',
                        'camera_no',
                        'alias_kamera',
                        'merek_kamera',
                        'model_type',
                        'sn',
                        'analog_digital',
                        'ip_kamera',
                        'key_pass',
                        'lantai_kamera',
                        'keterangan',
                        'catatan',
                    ],
                    'shared' => [
                        'perangkat',
                        'kategori',
                        'merek_perangkat',
                        'ip_perangkat',
                        'ruang',
                        'lantai_perangkat',
                    ],
                ],
            ],
            /*
            | Camera rows that share the same device identity are merged into
            | one visual group: the identity columns render once with rowSpan
            | while the remaining columns stay per camera.
            */
            'row_group' => [
                /*
                | The device identity: rows of one device (same name and
                | category) merge even when a shared field such as type was
                | filled inconsistently. Merged cells show the first
                | non-empty value found in the group.
                */
                'group_by' => ['nama', 'kategori'],
                /*
                | Camera channels are real assets in the Kamera category, so
                | their name and category must stay visible on their own row
                | instead of being merged into the device cell.
                */
                'per_row_identity' => ['nama', 'kategori'],
                'identity' => [
                    'nama',
                    'kategori',
                    'merek_perangkat',
                    'ip_perangkat',
                    'type',
                    'serial_number',
                    'model',
                    'tahun_operasi',
                    'ruang',
                    'lantai_perangkat',
                ],
            ],
            /*
            | Columns repeated per camera in the create form. The other
            | columns are filled once for the device and copied to every
            | created camera asset.
            */
            'multi_row' => [
                'label' => 'Kamera',
                'keys' => [
                    'camera_no',
                    'alias_kamera',
                    'merek_kamera',
                    'model_type',
                    'sn',
                    'analog_digital',
                    'ip_kamera',
                    'key_pass',
                    'lantai_kamera',
                    'catatan',
                ],
            ],
            'columns' => $columns([
                'nama' => 'Nama',
                'kategori' => 'Kategori',
                'perangkat' => 'Nama Perangkat',
                'merek_perangkat' => 'Merek (Perangkat)',
                'alias_perangkat' => 'Alias (Perangkat)',
                'ip_perangkat' => 'IP Address (Perangkat)',
                'type' => 'Type',
                'serial_number' => 'Serial Number',
                'model' => 'Model',
                'tahun_operasi' => 'Tahun Operasi',
                'kapasitas_hd' => 'Kapasitas HD',
                'tahun_manufaktur' => 'Tahun Manufaktur',
                'ruang' => 'Ruang',
                'lantai_perangkat' => 'Lantai (Perangkat)',
                'jumlah_kamera' => 'Jumlah Kamera',
                'camera_no' => 'Camera No.',
                'alias_kamera' => 'Alias (Kamera)',
                'merek_kamera' => 'Merek (Kamera)',
                'model_type' => 'Model/Type',
                'sn' => 'SN',
                'analog_digital' => 'Analog/Digital',
                'ip_kamera' => 'IP Address (Kamera)',
                'key_pass' => 'Key/ Pass',
                'lantai_kamera' => 'Lantai (Kamera)',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'nama' => 'name',
                'merek_perangkat' => 'brand',
                'alias_perangkat' => 'alias',
                'ip_perangkat' => 'ip_address',
                'type' => 'type',
                'serial_number' => 'serial_number',
                'model' => 'model',
                'tahun_operasi' => 'operation_year',
                'kapasitas_hd' => 'capacity',
                'tahun_manufaktur' => 'manufacture_year',
                'ruang' => 'room',
                'lantai_perangkat' => 'floor',
                'catatan' => 'notes',
            ],
            'required' => ['nama'],
            'counts' => ['lantai_kamera'],
        ],

        'apar' => [
            'label' => 'APAR',
            'categories' => ['APAR'],
            'columns' => $columns([
                'lantai' => 'Lantai',
                'nomor' => 'Nomor',
                'ruang' => 'Ruang',
                'merek' => 'Merek',
                'jenis' => 'Jenis',
                'pressure_gauge' => 'Pressure Gauge',
                'berat' => 'Berat',
                'tanggal_aktif' => 'Tanggal Aktif',
                'tanggal_kadaluarsa' => 'Tanggal Kadaluarsa',
                'aset' => 'Aset',
                'digantung' => 'Digantung',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'lantai' => 'floor',
                'nomor' => 'name',
                'ruang' => 'room',
                'merek' => 'brand',
                'catatan' => 'notes',
            ],
            'required' => ['nomor'],
        ],

        'rak-dan-pdu' => [
            'label' => 'RACK & PDU',
            'categories' => ['Rack'],
            'columns' => $columns([
                'lantai' => 'Lantai',
                'nama_perangkat' => 'Nama Perangkat',
                'qty' => 'QTY',
                'alias' => 'Alias',
                'ip_address' => 'IP Address',
                'merek' => 'Merek',
                'type' => 'Type',
                'serial_number' => 'Serial Number',
                'model' => 'Model',
                'kapasitas' => 'Kapasitas',
                'dimensi' => 'Dimensi',
                'pdu_1' => 'PDU.1',
                'pdu_2' => 'PDU.2',
                'tahun_operasi' => 'Tahun Operasi',
                'tahun_manufaktur' => 'Tahun Manufaktur',
                'ruang' => 'Ruang',
                'lantai_perangkat' => 'Lantai (Perangkat)',
                'milik' => 'Milik',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'lantai' => 'floor',
                'nama_perangkat' => 'name',
                'qty' => 'quantity',
                'alias' => 'alias',
                'ip_address' => 'ip_address',
                'merek' => 'brand',
                'type' => 'type',
                'serial_number' => 'serial_number',
                'model' => 'model',
                'kapasitas' => 'capacity',
                'tahun_operasi' => 'operation_year',
                'tahun_manufaktur' => 'manufacture_year',
                'ruang' => 'room',
                'milik' => 'owner',
                'catatan' => 'notes',
            ],
            'required' => ['nama_perangkat'],
        ],

        'akses-pintu' => [
            'label' => 'ACCESS DOOR',
            'categories' => ['Akses Pintu'],
            'columns' => $columns([
                'id_pintu' => 'ID Pintu',
                'merek' => 'Merek',
                'nama_pintu' => 'Nama Pintu',
                'type_model' => 'Type / Model',
                'serial_number' => 'Serial Number',
                'ip_address' => 'IP Address',
                'm_lock' => 'M-Lock',
                'exit_button' => 'Exit Button',
                'emergency_button' => 'Emergency Button',
                'lantai' => 'Lantai',
                'keterangan' => 'Keterangan',
                'catatan' => 'Catatan',
            ]),
            'storage' => [
                'nama_pintu' => 'name',
                'merek' => 'brand',
                'type_model' => 'type',
                'serial_number' => 'serial_number',
                'ip_address' => 'ip_address',
                'lantai' => 'floor',
                'catatan' => 'notes',
            ],
            'required' => ['nama_pintu'],
        ],
    ],
];
