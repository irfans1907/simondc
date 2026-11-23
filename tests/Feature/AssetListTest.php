<?php

use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

function assetListViewer(): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    return $user;
}

function assetListManager(): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');

    return $user;
}

test('guests are redirected from the asset list page', function () {
    $this->get(route('assets.list'))->assertRedirect(route('login'));
});

test('a view-only user can open the asset list', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/list', false)
            ->where('canManage', false)
        );
});

test('the asset list exposes the eight workbook sheets', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sheets', 8)
            ->where('sheets.0.slug', 'general')
            ->where('sheets.0.label', 'GENERAL')
            ->where('sheets.1.slug', 'ups-batt')
            ->where('sheets.1.label', 'UPS & BATTERY')
            ->where('sheets.2.slug', 'fap')
            ->where('sheets.7.slug', 'akses-pintu')
            ->where('sheets.7.label', 'ACCESS DOOR')
        );
});

test('the asset list defaults to the general sheet', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeSheet', 'general')
            ->has('columns', 17)
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'nama_perangkat'
                    && $column['label'] === 'Nama Perangkat'))
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'lantai'
                    && $column['label'] === 'Lantai'
                    && $column['storage'] === 'floor'))
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'catatan'
                    && $column['label'] === 'Catatan'))
        );
});

test('every sheet has lantai and catatan columns', function () {
    $this->actingAs(assetListViewer());

    $sheets = [
        'general' => 17,
        'ups-batt' => 21,
        'fap' => 17,
        'ems' => 19,
        'cctv' => 26,
        'apar' => 13,
        'rak-dan-pdu' => 20,
        'akses-pintu' => 12,
    ];

    foreach ($sheets as $sheet => $columnCount) {
        $this->get(route('assets.list', ['sheet' => $sheet]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeSheet', $sheet)
                ->has('columns', $columnCount)
                ->where('columns', fn ($columns) => collect($columns)
                    ->contains(fn ($column) => $column['storage'] === 'floor'))
                ->where('columns', fn ($columns) => $sheet !== 'ems'
                    || collect($columns)->contains(fn ($column) => $column['key'] === 'lantai_sensor'))
                ->where('columns', fn ($columns) => collect($columns)
                    ->contains(fn ($column) => $column['key'] === 'catatan'))
            );
    }
});

test('the ems sheet starts with the device floor column', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ems']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.key', 'lantai_perangkat')
            ->where('columns.0.label', 'Lantai Perangkat')
            ->where('columns.0.storage', 'floor'));
});

test('the ems sheet merges sensor rows that share one device', function () {
    $ems = Category::create(['parent_id' => null, 'name' => 'EMS']);

    foreach ([
        ['UPS A&B Lt.1', '1:1', 'TELKOM DCS'],
        ['DC Room Lt.4.1', '4:1', 'TELKOM TDE'],
    ] as [$sensor, $sensorFloors, $owner]) {
        Asset::create([
            'category_id' => $ems->id,
            'name' => 'NTI',
            'quantity' => '1',
            'alias' => 'NTI SLAVE',
            'ip_address' => '10.64.2.180',
            'brand' => 'NTI',
            'type' => 'EMX2-888',
            'serial_number' => 'PH80300021',
            'model' => 'SENSOR',
            'capacity' => '20GB',
            'operation_year' => '2014',
            'manufacture_year' => '2002',
            'room' => 'IT Hall Lt.4',
            'owner' => $owner,
            'floor' => '4',
            'details' => [
                'nama_sensor' => $sensor,
                'jenis_sensor' => 'TH',
                'lantai_sensor' => $sensorFloors,
            ],
        ]);
    }

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ems']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('groupedTable.identity', fn ($identity) => collect($identity)->all() === [
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
            ])
            ->has('groupedTable.groups.data', 1)
            ->has('groupedTable.groups.data.0.rows', 2)
            ->where('groupedTable.groups.data.0.rows.0.values.nama_perangkat', 'NTI')
            ->where('groupedTable.groups.data.0.rows.0.values.lantai_perangkat', '4')
            ->where('groupedTable.groups.data.0.rows.0.values.milik', 'TELKOM DCS')
            ->where('groupedTable.groups.data.0.rows.0.values.nama_sensor', 'UPS A&B Lt.1')
            ->where('groupedTable.groups.data.0.rows.1.values.milik', 'TELKOM TDE')
            ->where('groupedTable.groups.data.0.rows.1.values.nama_sensor', 'DC Room Lt.4.1'));
});

test('the ems preset carries the device values for autofill', function () {
    $ems = Category::create(['parent_id' => null, 'name' => 'EMS']);

    Asset::create([
        'category_id' => $ems->id,
        'name' => 'NTI',
        'quantity' => '1',
        'alias' => 'NTI SLAVE',
        'ip_address' => '10.64.2.180',
        'brand' => 'NTI',
        'type' => 'EMX2-888',
        'serial_number' => 'PH80300021',
        'model' => 'SENSOR',
        'capacity' => '20GB',
        'operation_year' => '2014',
        'manufacture_year' => '2002',
        'room' => 'IT Hall Lt.4',
        'owner' => 'TELKOM DCS',
        'floor' => '4',
        'details' => [
            'nama_sensor' => 'UPS A&B Lt.1',
            'jenis_sensor' => 'TH',
            'lantai_sensor' => '1:1',
        ],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ems']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assetPresets', fn ($presets) => collect($presets)->contains(
                fn ($preset) => $preset['name'] === 'NTI'
                    && $preset['category_path'] === ['EMS']
                    && $preset['values']['lantai_perangkat'] === '4'
                    && $preset['values']['qty'] === '1'
                    && $preset['values']['alias'] === 'NTI SLAVE'
                    && $preset['values']['ip_address'] === '10.64.2.180'
                    && $preset['values']['merek'] === 'NTI'
                    && $preset['values']['type'] === 'EMX2-888'
                    && $preset['values']['serial_number'] === 'PH80300021'
                    && $preset['values']['model'] === 'SENSOR'
                    && $preset['values']['kapasitas'] === '20GB'
                    && $preset['values']['tahun_operasi'] === '2014'
                    && $preset['values']['tahun_manufaktur'] === '2002'
                    && $preset['values']['ruang'] === 'IT Hall Lt.4'
                    && ! array_key_exists('nama_perangkat', $preset['values'])
                    && ! array_key_exists('milik', $preset['values'])
                    && ! array_key_exists('nama_sensor', $preset['values'])
                    && ! array_key_exists('jenis_sensor', $preset['values'])
                    && ! array_key_exists('lantai_sensor', $preset['values'])
                    && ! array_key_exists('catatan', $preset['values']),
            )));
});

test('the fap preset carries the panel values for autofill', function () {
    $fssPanel = Category::create(['parent_id' => null, 'name' => 'FSS Panel']);

    Asset::create([
        'category_id' => $fssPanel->id,
        'name' => 'FM100',
        'floor' => '3',
        'brand' => 'NOHMI',
        'model' => 'INTEGLEX',
        'capacity' => '400KG',
        'details' => [
            'zona' => '4',
            'system' => 'NN100.A',
            'nama_zona' => 'DC.1 LT.4',
            'manual_abort' => '4',
            'tabung_merek' => 'NN100',
            'jumlah_tabung' => '30',
            'manual_release' => '4',
            'speaker_horn_bell' => 'HORN/BELL',
            'fire_detector_name' => '36',
            'discharge_indicator_lamp' => '4',
        ],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'fap']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assetPresets', fn ($presets) => collect($presets)->contains(
                fn ($preset) => $preset['name'] === 'FM100'
                    && $preset['category_path'] === ['FSS Panel']
                    && $preset['values'] === [
                        'lantai' => '3',
                        'merek' => 'NOHMI',
                        'model' => 'INTEGLEX',
                    ],
            )));
});

test('the fap grouped table merges rows that share one panel', function () {
    $fssPanel = Category::create(['parent_id' => null, 'name' => 'FSS Panel']);

    foreach ([
        ['NN100.A', '4', 'HORN/BELL'],
        ['NN200.B', '5', 'SPEAKER'],
    ] as [$system, $zona, $speaker]) {
        Asset::create([
            'category_id' => $fssPanel->id,
            'name' => 'FM100',
            'floor' => '3',
            'brand' => 'NOHMI',
            'model' => 'INTEGLEX',
            'details' => [
                'system' => $system,
                'zona' => $zona,
                'speaker_horn_bell' => $speaker,
            ],
        ]);
    }

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'fap']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('groupedTable.identity', fn ($identity) => collect($identity)->all() === [
                'lantai',
                'panel',
                'merek',
                'model',
            ])
            ->has('groupedTable.groups.data', 1)
            ->has('groupedTable.groups.data.0.rows', 2)
            ->where('groupedTable.groups.data.0.rows.0.values.lantai', '3')
            ->where('groupedTable.groups.data.0.rows.0.values.panel', 'FM100')
            ->where('groupedTable.groups.data.0.rows.0.values.merek', 'NOHMI')
            ->where('groupedTable.groups.data.0.rows.0.values.model', 'INTEGLEX')
            ->where('groupedTable.groups.data.0.rows.0.values.system', 'NN100.A')
            ->where('groupedTable.groups.data.0.rows.1.values.system', 'NN200.B')
            ->where('groupedTable.groups.data.0.rows.0.values.zona', '4')
            ->where('groupedTable.groups.data.0.rows.1.values.zona', '5'));
});

test('the sheet query switches the column preset', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'cctv']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeSheet', 'cctv')
            ->has('columns', 26)
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'camera_no'
                    && $column['label'] === 'Camera No.'))
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'alias_kamera'
                    && $column['label'] === 'Alias (Kamera)'))
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'catatan'
                    && $column['label'] === 'Catatan'))
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'kategori'
                    && $column['label'] === 'Kategori'
                    && $column['computed'] === true))
            ->where('columns', fn ($columns) => collect($columns)
                ->doesntContain(fn ($column) => $column['key'] === 'dvr_nvr'))
        );
});

test('an unknown sheet falls back to the default', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'does-not-exist']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeSheet', 'general')
            ->has('columns', 17)
        );
});

test('the cctv device table collapses legacy rows that share an identity', function () {
    $dvr = Category::create(['parent_id' => null, 'name' => 'DVR']);

    foreach ([
        [1, 'SLOT-1 : 1TB'],
        [2, 'SLOT-2 : 2TB'],
        [3, 'SLOT-2 : 2TB'],
    ] as [$cameraNumber, $capacity]) {
        Asset::create([
            'category_id' => $dvr->id,
            'name' => 'CCTV-1',
            'brand' => 'Hikvision',
            'ip_address' => '10.0.0.10',
            'type' => 'DVR',
            'serial_number' => 'SN-DEVICE',
            'model' => 'DS-7208',
            'capacity' => $capacity,
            'operation_year' => '2024',
            'room' => 'Ruang NOC',
            'floor' => '3',
            'details' => ['camera_no' => (string) $cameraNumber],
        ]);
    }

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'cctv']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.device.groups.data', 1)
            ->has('tables.device.groups.data.0.rows', 1)
            ->where('tables.device.groups.data.0.rows.0.values.nama', 'CCTV-1')
            ->where('tables.device.groups.data.0.rows.0.values.kategori', 'DVR')
            ->where('tables.device.groups.data.0.rows.0.values.kapasitas_hd', 'SLOT-1 : 1TB; SLOT-2 : 2TB')
            ->where('tables.device.groups.data.0.rows.0.values.jumlah_kamera', '0')
            ->has('tables.camera.groups.data', 0));
});

test('the cctv preset carries the device values for autofill', function () {
    $dvr = Category::create(['parent_id' => null, 'name' => 'DVR']);

    Asset::create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-1',
        'brand' => 'Hikvision',
        'alias' => 'DVR-01',
        'ip_address' => '10.0.0.10',
        'serial_number' => 'SN-DEVICE',
        'model' => 'DS-7208',
        'operation_year' => '2024',
        'capacity' => '2TB',
        'manufacture_year' => '2023',
        'room' => 'Ruang NOC',
        'floor' => '3',
        'notes' => 'Catatan perangkat',
        'details' => ['camera_no' => '1'],
    ]);

    Asset::create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-1',
        'brand' => 'Hikvision',
        'ip_address' => '10.0.0.10',
        'type' => 'DS-7216HGHI-F2/M',
        'serial_number' => 'SN-DEVICE',
        'model' => 'DS-7208',
        'operation_year' => '2024',
        'capacity' => '3TB',
        'room' => 'Ruang NOC',
        'floor' => '3',
        'details' => ['camera_no' => '2'],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'cctv']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assetPresets', fn ($presets) => collect($presets)->contains(
                fn ($preset) => $preset['name'] === 'CCTV-1'
                    && $preset['category_path'] === ['DVR']
                    && $preset['values']['merek_perangkat'] === 'Hikvision'
                    && $preset['values']['ip_perangkat'] === '10.0.0.10'
                    && $preset['values']['type'] === 'DS-7216HGHI-F2/M'
                    && $preset['values']['lantai_perangkat'] === '3'
                    && ! array_key_exists('nama', $preset['values'])
                    && ! array_key_exists('kategori', $preset['values'])
                    && ! array_key_exists('keterangan', $preset['values'])
                    && ! array_key_exists('alias_perangkat', $preset['values'])
                    && ! array_key_exists('kapasitas_hd', $preset['values'])
                    && ! array_key_exists('tahun_manufaktur', $preset['values'])
                    && ! array_key_exists('camera_no', $preset['values'])
                    && ! array_key_exists('catatan', $preset['values']),
            )));
});

test('the cctv device table merges rows that differ only in a non-identity field', function () {
    $dvr = Category::create(['parent_id' => null, 'name' => 'DVR']);

    Asset::create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-1',
        'brand' => 'SAMSUNG',
        'details' => ['camera_no' => 'CHANEL-1'],
    ]);

    Asset::create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-1',
        'brand' => 'SAMSUNG',
        'type' => 'DS-7216HGHI-F2/M',
        'details' => ['camera_no' => 'CHANEL-2'],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'cctv']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.device.groups.data', 1)
            ->has('tables.device.groups.data.0.rows', 1)
            ->where('tables.device.groups.data.0.rows.0.values.type', 'DS-7216HGHI-F2/M')
            ->where('tables.device.groups.data.0.rows.0.values.jumlah_kamera', '0'));
});

test('the cctv device table paginates by device group', function () {
    $dvr = Category::create(['parent_id' => null, 'name' => 'DVR']);

    foreach (['CCTV-1', 'CCTV-2'] as $name) {
        foreach ([1, 2] as $cameraNumber) {
            Asset::create([
                'category_id' => $dvr->id,
                'name' => $name,
                'brand' => 'Hikvision',
                'details' => ['camera_no' => (string) $cameraNumber],
            ]);
        }
    }

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'cctv', 'per_page' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.device.groups.data', 1)
            ->where('tables.device.groups.total', 2)
            ->where('tables.device.groups.per_page', 1)
            ->has('tables.device.groups.data.0.rows', 1));
});

test('a manager can store several camera rows sharing one device', function () {
    Category::create(['parent_id' => null, 'name' => 'DVR']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'CCTV-1',
        'category_path' => ['DVR'],
        'brand' => 'Hikvision',
        'ip_address' => '10.0.0.10',
        'type' => 'DVR',
        'serial_number' => 'SN-DEVICE',
        'model' => 'DS-7208',
        'operation_year' => '2024',
        'room' => 'Ruang NOC',
        'floor' => '3',
        'cameras' => [
            [
                'camera_no' => '1',
                'alias_kamera' => 'Lobby',
                'lantai_kamera' => '2:5, 3:3',
                'catatan' => 'Kamera utama',
            ],
            [
                'camera_no' => '2',
                'alias_kamera' => 'Parking',
                'lantai_kamera' => '1:2',
            ],
            [
                'camera_no' => '',
                'alias_kamera' => '',
            ],
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $assets = Asset::query()->where('name', 'CCTV-1')->orderBy('id')->get();

    expect($assets)->toHaveCount(2)
        ->and($assets[0]->details['camera_no'])->toBe('1')
        ->and($assets[0]->details['lantai_kamera'])->toBe('2:5, 3:3')
        ->and($assets[0]->notes)->toBe('Kamera utama')
        ->and($assets[1]->details['camera_no'])->toBe('2')
        ->and($assets[1]->details['lantai_kamera'])->toBe('1:2')
        ->and($assets[1]->brand)->toBe('Hikvision');
});

test('the bulk store keeps one device asset when every camera row is empty', function () {
    Category::create(['parent_id' => null, 'name' => 'DVR']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'CCTV-EMPTY',
        'category_path' => ['DVR'],
        'cameras' => [
            ['camera_no' => '', 'alias_kamera' => ''],
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect(Asset::query()->where('name', 'CCTV-EMPTY')->count())->toBe(1);
});

test('the asset list page renders the child menu props', function () {
    $assetMenu = Menu::create([
        'name' => 'Asset Management',
        'group' => 'Main Menu',
        'url' => '/assets',
        'icon' => 'Boxes',
        'order' => 3,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Asset List',
        'group' => 'Main Menu',
        'url' => '/assets/list',
        'parent_id' => $assetMenu->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('menu')
            ->has('activeChild')
            ->where('menu.url', '/assets')
            ->where('activeChild.url', '/assets/list')
            ->has('menus')
        );
});

test('the general sheet lists every asset with its managed sheet flag', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $apar = Category::create(['parent_id' => null, 'name' => 'APAR']);
    $server = Category::create(['parent_id' => null, 'name' => 'Server']);

    Asset::factory()->create(['category_id' => $ups->id, 'name' => 'UPS Alpha']);
    Asset::factory()->create(['category_id' => $apar->id, 'name' => 'APAR Beta']);
    Asset::factory()->create(['category_id' => $server->id, 'name' => 'Server Gamma']);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 3)
            ->where('assets.data.0.values.nama_perangkat', 'APAR Beta')
            ->where('assets.data.0.managed_sheet', 'APAR')
            ->where('assets.data.1.values.nama_perangkat', 'Server Gamma')
            ->where('assets.data.1.managed_sheet', null)
            ->where('assets.data.2.values.nama_perangkat', 'UPS Alpha')
            ->where('assets.data.2.managed_sheet', 'UPS & BATTERY')
        );
});

test('general hides categories managed by another sheet from the form', function () {
    Category::create(['parent_id' => null, 'name' => 'UPS']);
    Category::create(['parent_id' => null, 'name' => 'Server']);
    Category::create(['parent_id' => null, 'name' => 'APAR']);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', fn ($categories) => collect($categories)
                ->pluck('name')
                ->all() === ['Server'])
        );
});

test('general cannot create an asset in a category managed by another sheet', function () {
    Category::create(['parent_id' => null, 'name' => 'UPS']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'general',
        'name' => 'UPS From General',
        'category_path' => ['UPS'],
    ])->assertSessionHasErrors('category_path.0');

    $this->assertDatabaseMissing('assets', ['name' => 'UPS From General']);
});

test('general can create an asset for an unmanaged category', function () {
    Category::create(['parent_id' => null, 'name' => 'Server']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'general',
        'name' => 'Server From General',
        'category_path' => ['Server'],
    ])->assertRedirect(route('assets.list'));

    $this->assertDatabaseHas('assets', ['name' => 'Server From General']);
});

test('a sheet only lists its category subtree', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $upsFrame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);
    $apar = Category::create(['parent_id' => null, 'name' => 'APAR']);

    Asset::factory()->create(['category_id' => $upsFrame->id, 'name' => 'UPS Nested']);
    Asset::factory()->create(['category_id' => $apar->id, 'name' => 'APAR Outside']);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.ups.groups.data', 1)
            ->where('tables.ups.groups.data.0.rows.0.values.nama', 'UPS Nested')
        );
});

test('floor counts are normalised and parsed', function () {
    expect(AssetSheetHelper::normalizeFloorCounts(' 3:3, 2:5, 2:1 '))->toBe('2:6, 3:3')
        ->and(AssetSheetHelper::normalizeFloorCounts('2'))->toBe('2:1')
        ->and(AssetSheetHelper::normalizeFloorCounts(''))->toBe('')
        ->and(AssetSheetHelper::floorCounts('2:5, 3:3'))->toEqual([2 => 5, 3 => 3]);
});

test('count columns show the floor jumlah hint', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ems']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns', function ($columns) {
                $column = collect($columns)->firstWhere('key', 'lantai_sensor');

                return $column !== null
                    && $column['hint'] === 'Format lantai:jumlah, contoh 2:5, 3:3.';
            })
        );

    $this->get(route('assets.list', ['sheet' => 'cctv']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns', function ($columns) {
                $column = collect($columns)->firstWhere('key', 'lantai_kamera');

                return $column !== null
                    && $column['hint'] === 'Format lantai:jumlah, contoh 2:5, 3:3.';
            })
        );
});

test('a manager can store floor counts for ems sensors', function () {
    $ems = Category::create(['parent_id' => null, 'name' => 'EMS']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ems',
        'name' => 'NTI-1',
        'category_path' => ['EMS'],
        'details' => [
            'lantai_sensor' => ' 3:3, 2:5, 2:1 ',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'ems']));

    $asset = Asset::query()->where('name', 'NTI-1')->firstOrFail();

    expect($asset->category_id)->toBe($ems->id)
        ->and($asset->details['lantai_sensor'])->toBe('2:6, 3:3');
});

test('a manager can store the device floor for ems assets', function () {
    Category::create(['parent_id' => null, 'name' => 'EMS']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ems',
        'name' => 'EMS-1',
        'category_path' => ['EMS'],
        'floor' => '3',
    ])->assertRedirect(route('assets.list', ['sheet' => 'ems']));

    expect(Asset::query()->where('name', 'EMS-1')->firstOrFail()->floor)->toBe('3');
});

test('a manager can store camera counts per floor', function () {
    $dvr = Category::create(['parent_id' => null, 'name' => 'DVR']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'cctv',
        'name' => 'CCTV-1',
        'category_path' => ['DVR'],
        'floor' => '3',
        'details' => [
            'lantai_kamera' => '2:5, 3:3',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $asset = Asset::query()->where('name', 'CCTV-1')->firstOrFail();

    expect($asset->category_id)->toBe($dvr->id)
        ->and($asset->details['lantai_kamera'])->toBe('2:5, 3:3');
});

test('invalid floor counts are rejected', function () {
    Category::create(['parent_id' => null, 'name' => 'EMS']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ems',
        'name' => 'NTI-2',
        'category_path' => ['EMS'],
        'details' => [
            'lantai_sensor' => '2:abc',
        ],
    ])->assertSessionHasErrors('details.lantai_sensor');

    $this->assertDatabaseMissing('assets', ['name' => 'NTI-2']);
});

test('sheet specific columns are read from the details json', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);

    Asset::factory()->create([
        'category_id' => $ups->id,
        'name' => 'UPS-A',
        'floor' => '1',
        'details' => [
            'frame_sn' => 'FRAME-001',
            'batterai_merek' => 'FIAMM',
        ],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tables.ups.groups.data.0.rows.0.values.lantai', '1')
            ->where('tables.ups.groups.data.0.rows.0.values.nama', 'UPS-A')
            ->where('tables.ups.groups.data.0.rows.0.values.frame_sn', 'FRAME-001')
            ->where('tables.ups.columns', fn ($columns) => collect($columns)
                ->doesntContain(fn ($column) => $column['key'] === 'batterai_merek'))
        );
});

test('keterangan is generated from the asset name and category path', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);

    Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-B',
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tables.ups.groups.data.0.rows.0.values.keterangan', "UPS-B\nUPS\nUPS Frame")
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'keterangan'
                    && $column['computed'] === true))
        );
});

test('the general sheet shows the category path next to the name', function () {
    $network = Category::create(['parent_id' => null, 'name' => 'Network']);
    $switch = Category::create(['parent_id' => $network->id, 'name' => 'Switch']);

    Asset::factory()->create([
        'category_id' => $switch->id,
        'name' => 'Cisco',
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'general']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assets.data.0.values.kategori', 'Network > Switch')
            ->where('columns', fn ($columns) => collect($columns)
                ->contains(fn ($column) => $column['key'] === 'kategori'
                    && $column['label'] === 'Kategori'
                    && $column['computed'] === true))
        );
});

test('the asset list search matches category names from keterangan', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);

    Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-B',
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', [
        'sheet' => 'ups-batt',
        'search' => 'UPS Frame',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.ups.groups.data', 1)
            ->where('tables.ups.groups.data.0.rows.0.values.nama', 'UPS-B')
        );
});

test('the ups sheet exposes form groups per sub category', function () {
    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('formGroups.common', ['nama', 'merek', 'kapasitas_kva', 'lantai', 'catatan'])
            ->where('formGroups.groups.0.name', 'UPS Frame')
            ->where('formGroups.groups.0.keys', ['frame_nomor', 'frame_tahun', 'frame_sn'])
            ->where('formGroups.groups.0.keywords', ['frame'])
            ->where('formGroups.groups.1.name', 'UPS Modul')
            ->where('formGroups.groups.1.keywords', ['modul'])
            ->where('formGroups.groups.2.name', 'Battery')
            ->where('formGroups.groups.2.keywords', ['battery', 'baterai', 'batterai'])
            ->where('formGroups.groups.3.name', 'UPS Electronic')
            ->where('formGroups.groups.3.keys', [])
            ->where('formGroups.groups.3.keywords', ['electronic'])
        );

    $this->get(route('assets.list', ['sheet' => 'general']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('formGroups', null));
});

test('the asset list can be searched', function () {
    $server = Category::create(['parent_id' => null, 'name' => 'Server']);

    Asset::factory()->create(['category_id' => $server->id, 'name' => 'Alpha Server']);
    Asset::factory()->create(['category_id' => $server->id, 'name' => 'Beta Server']);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['search' => 'Alpha']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.values.nama_perangkat', 'Alpha Server')
        );
});

test('the asset list paginates', function () {
    $server = Category::create(['parent_id' => null, 'name' => 'Server']);
    Asset::factory()->count(15)->create(['category_id' => $server->id]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 10)
            ->where('assets.total', 15)
            ->where('assets.per_page', 10)
        );
});

test('a manager can create an asset with sheet details', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ups-batt',
        'name' => 'UPS-C',
        'category_path' => ['UPS'],
        'floor' => '2',
        'brand' => 'NEWAVE',
        'details' => [
            'frame_sn' => 'FRAME-C',
            'batterai_merek' => 'VEKTOR',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'ups-batt']));

    $asset = Asset::query()->where('name', 'UPS-C')->firstOrFail();

    expect($asset->category_id)->toBe($ups->id)
        ->and($asset->floor)->toBe('2')
        ->and($asset->details)->toMatchArray([
            'frame_sn' => 'FRAME-C',
            'batterai_merek' => 'VEKTOR',
        ]);
});

test('creating an asset with identical fields updates the existing asset', function () {
    Category::create(['parent_id' => null, 'name' => 'APAR']);

    $this->actingAs(assetListManager());

    $payload = [
        'sheet' => 'apar',
        'name' => 'APAR-9',
        'category_path' => ['APAR'],
        'brand' => 'Yamato',
        'room' => 'Lobby',
        'floor' => '1',
    ];

    $this->post(route('assets.store'), $payload)
        ->assertRedirect(route('assets.list', ['sheet' => 'apar']));
    $this->post(route('assets.store'), $payload)
        ->assertRedirect(route('assets.list', ['sheet' => 'apar']));

    expect(Asset::query()->where('name', 'APAR-9')->count())->toBe(1)
        ->and(Asset::query()->where('name', 'APAR-9')->firstOrFail()->room)->toBe('Lobby');
});

test('creating an asset with a different field adds another asset', function () {
    Category::create(['parent_id' => null, 'name' => 'APAR']);

    $this->actingAs(assetListManager());

    $payload = [
        'sheet' => 'apar',
        'name' => 'APAR-9',
        'category_path' => ['APAR'],
        'brand' => 'Yamato',
        'room' => 'Lobby',
        'floor' => '1',
    ];

    $this->post(route('assets.store'), $payload);
    $this->post(route('assets.store'), [...$payload, 'room' => 'Gudang']);

    expect(Asset::query()->where('name', 'APAR-9')->count())->toBe(2);
});

test('a sheet rejects a category from another sheet', function () {
    Category::create(['parent_id' => null, 'name' => 'UPS']);
    Category::create(['parent_id' => null, 'name' => 'APAR']);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ups-batt',
        'name' => 'Wrong Category',
        'category_path' => ['APAR'],
    ])->assertSessionHasErrors('category_path.0');

    $this->assertDatabaseMissing('assets', ['name' => 'Wrong Category']);
});

test('a manager can update an asset and its details', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $asset = Asset::factory()->create([
        'category_id' => $ups->id,
        'name' => 'UPS-D',
        'details' => ['frame_sn' => 'OLD'],
    ]);

    $this->actingAs(assetListManager());

    $this->patch(route('assets.update', $asset), [
        'sheet' => 'ups-batt',
        'name' => 'UPS-D Updated',
        'category_path' => ['UPS'],
        'details' => ['frame_sn' => 'NEW'],
    ])->assertRedirect(route('assets.list', ['sheet' => 'ups-batt']));

    expect($asset->fresh()->name)->toBe('UPS-D Updated')
        ->and($asset->fresh()->details)->toMatchArray(['frame_sn' => 'NEW']);
});

test('a manager can delete an asset from the list', function () {
    $server = Category::create(['parent_id' => null, 'name' => 'Server']);
    $asset = Asset::factory()->create(['category_id' => $server->id]);

    $this->actingAs(assetListManager());

    $this->delete(route('assets.destroy', $asset), ['sheet' => 'general'])
        ->assertRedirect(route('assets.list'));

    $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
});

test('a view-only user cannot change assets', function () {
    $server = Category::create(['parent_id' => null, 'name' => 'Server']);
    $asset = Asset::factory()->create(['category_id' => $server->id]);

    $this->actingAs(assetListViewer());

    $this->post(route('assets.store'), [
        'name' => 'Nope',
        'category_path' => ['Server'],
    ])->assertForbidden();

    $this->patch(route('assets.update', $asset), [
        'name' => 'Nope',
        'category_path' => ['Server'],
    ])->assertForbidden();

    $this->delete(route('assets.destroy', $asset))->assertForbidden();
});

test('the ups sheet renders separate ups and battery tables', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $batteryCategory = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Battery']);

    $upsAsset = Asset::factory()->create([
        'category_id' => $ups->id,
        'name' => 'UPS-A',
        'brand' => 'NEWAVE',
        'capacity' => '100',
        'details' => ['frame_sn' => 'FRAME-1'],
    ]);

    Asset::factory()->create([
        'category_id' => $batteryCategory->id,
        'parent_asset_id' => $upsAsset->id,
        'name' => 'UPS-A',
        'brand' => 'OTHER',
        'capacity' => '999',
        'details' => ['batterai_merek' => 'FIAMM', 'batterai_bank_no' => '1'],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.ups.groups.data', 1)
            ->where('tables.ups.groups.data.0.rows.0.values.nama', 'UPS-A')
            ->where('tables.ups.groups.data.0.rows.0.values.frame_sn', 'FRAME-1')
            ->where('tables.ups.shared', fn ($shared) => collect($shared)->contains('frame_sn'))
            ->where('tables.ups.identity', fn ($identity) => collect($identity)->contains('nama'))
            ->where('assetPresets', fn ($presets) => collect($presets)->contains(
                fn ($preset) => $preset['name'] === 'UPS-A'
                    && $preset['brand'] === 'NEWAVE'
                    && $preset['capacity'] === '100',
            ))
            ->has('tables.battery.groups.data', 1)
            ->where('tables.battery.groups.data.0.name', 'UPS-A')
            ->where('tables.battery.groups.data.0.brand', 'NEWAVE')
            ->where('tables.battery.groups.data.0.capacity', '100')
            ->has('tables.battery.groups.data.0.rows', 1)
            ->where('tables.battery.groups.data.0.rows.0.values.batterai_merek', 'FIAMM')
            ->where('tables.battery.groups.data.0.rows.0.values.keterangan', "UPS-A\nUPS\nUPS Battery")
            ->where('tables.battery.columns', fn ($columns) => collect($columns)->contains(
                fn ($column) => $column['key'] === 'keterangan'
                    && $column['label'] === 'Keterangan'
                    && $column['computed'] === true,
            ))
            ->where('tables.battery.columns', function ($columns) {
                $keys = collect($columns)->pluck('key')->values();

                return $keys->search('keterangan') !== false
                    && $keys->search('keterangan') < $keys->search('catatan');
            })
            ->has('upsOptions', 1)
            ->where('upsOptions.0.name', 'UPS-A')
            ->where('batteryKeywords', fn ($keywords) => collect($keywords)->contains('battery'))
            ->where('tables.ups.columns', fn ($columns) => collect($columns)
                ->doesntContain(fn ($column) => $column['key'] === 'batterai_merek'))
            ->where('tables.battery.columns', fn ($columns) => collect($columns)
                ->doesntContain(fn ($column) => $column['key'] === 'frame_sn'))
        );
});

test('ups modules of the same identity are grouped across frames', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'Frame']);
    $modul = Category::create(['parent_id' => $frame->id, 'name' => 'Modul']);

    foreach (['1', '2'] as $number) {
        Asset::factory()->create([
            'category_id' => $modul->id,
            'name' => 'UPS-C',
            'brand' => 'NEWAVE',
            'capacity' => '240',
            'details' => [
                'frame_nomor' => '1',
                'frame_tahun' => '2004',
                'frame_sn' => 'UU0782',
                'modul_nomor' => $number,
            ],
        ]);
    }

    Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-C',
        'brand' => 'NEWAVE',
        'capacity' => '240',
        'details' => [
            'frame_nomor' => '2',
            'frame_tahun' => '2004',
            'frame_sn' => 'UU9999',
            'modul_nomor' => '1',
        ],
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.ups.groups.data', 1)
            ->has('tables.ups.groups.data.0.rows', 3)
            ->where('tables.ups.groups.data.0.rows.0.values.frame_sn', 'UU0782')
            ->where('tables.ups.groups.data.0.rows.1.values.frame_sn', 'UU0782')
            ->where('tables.ups.groups.data.0.rows.2.values.frame_sn', 'UU9999')
            ->where('tables.ups.identity', fn ($identity) => collect($identity)->all() === [
                'nama',
                'merek',
                'kapasitas_kva',
            ])
            ->where('tables.ups.shared', fn ($shared) => collect($shared)->all() === [
                'frame_nomor',
                'frame_tahun',
                'frame_sn',
            ])
            ->where('assetPresets', fn ($presets) => collect($presets)->contains(
                fn ($preset) => $preset['name'] === 'UPS-C'
                    && $preset['brand'] === 'NEWAVE'
                    && $preset['capacity'] === '240'
                    && $preset['frames'][0] === [
                        'nomor' => '1',
                        'tahun' => '2004',
                        'sn' => 'UU0782',
                    ],
            ))
        );
});

test('ups parent options are deduplicated by identity', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'Modul']);

    $first = Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-X',
        'brand' => 'NEWAVE',
        'capacity' => '240',
    ]);

    foreach (['2', '3'] as $number) {
        Asset::factory()->create([
            'category_id' => $modul->id,
            'name' => 'UPS-X',
            'brand' => 'NEWAVE',
            'capacity' => '240',
            'details' => ['modul_nomor' => $number],
        ]);
    }

    Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-Y',
        'brand' => 'APC',
        'capacity' => '100',
    ]);

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('upsOptions', 2)
            ->where('upsOptions.0.id', $first->id)
            ->where('upsOptions.0.name', 'UPS-X')
            ->where('upsOptions.1.name', 'UPS-Y')
        );
});

test('battery banks of the same ups are grouped under one parent row', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $batteryCategory = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Battery']);

    $upsAsset = Asset::factory()->create([
        'category_id' => $ups->id,
        'name' => 'UPS-B',
        'brand' => 'NEWAVE',
        'capacity' => '200',
    ]);

    foreach (['1', '2'] as $bank) {
        Asset::factory()->create([
            'category_id' => $batteryCategory->id,
            'parent_asset_id' => $upsAsset->id,
            'name' => 'UPS-B',
            'details' => ['batterai_bank_no' => $bank],
        ]);
    }

    $this->actingAs(assetListViewer());

    $this->get(route('assets.list', ['sheet' => 'ups-batt']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.battery.groups.data', 1)
            ->where('tables.battery.groups.data.0.name', 'UPS-B')
            ->where('tables.battery.groups.data.0.brand', 'NEWAVE')
            ->where('tables.battery.groups.data.0.capacity', '200')
            ->has('tables.battery.groups.data.0.rows', 2)
        );
});

test('a battery asset requires a ups parent and copies its shared values', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    Category::create(['parent_id' => $ups->id, 'name' => 'UPS Battery']);

    $upsAsset = Asset::factory()->create([
        'category_id' => $ups->id,
        'name' => 'UPS-C',
        'brand' => 'VEKTOR',
        'capacity' => '300',
    ]);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ups-batt',
        'category_path' => ['UPS', 'UPS Battery'],
        'details' => ['batterai_bank_no' => '2'],
    ])->assertSessionHasErrors('parent_asset_id');

    $this->post(route('assets.store'), [
        'sheet' => 'ups-batt',
        'category_path' => ['UPS', 'UPS Battery'],
        'parent_asset_id' => $upsAsset->id,
        'details' => ['batterai_bank_no' => '2'],
    ])->assertRedirect(route('assets.list', ['sheet' => 'ups-batt']));

    $battery = Asset::query()
        ->where('parent_asset_id', $upsAsset->id)
        ->firstOrFail();

    expect($battery->name)->toBe('UPS-C')
        ->and($battery->brand)->toBe('VEKTOR')
        ->and($battery->capacity)->toBe('300')
        ->and($battery->details)->toMatchArray(['batterai_bank_no' => '2']);
});

test('a battery asset cannot use another battery as its parent', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $batteryCategory = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Battery']);

    $upsAsset = Asset::factory()->create([
        'category_id' => $ups->id,
        'name' => 'UPS-D',
    ]);

    $battery = Asset::factory()->create([
        'category_id' => $batteryCategory->id,
        'parent_asset_id' => $upsAsset->id,
        'name' => 'UPS-D',
    ]);

    $this->actingAs(assetListManager());

    $this->post(route('assets.store'), [
        'sheet' => 'ups-batt',
        'category_path' => ['UPS', 'UPS Battery'],
        'parent_asset_id' => $battery->id,
        'details' => ['batterai_bank_no' => '3'],
    ])->assertSessionHasErrors('parent_asset_id');
});

test('the export follows the active sheet columns', function () {
    $apar = Category::create(['parent_id' => null, 'name' => 'APAR']);
    Asset::factory()->create(['category_id' => $apar->id, 'name' => 'APAR-1']);

    $this->actingAs(assetListManager());

    $response = $this->get(route('assets.export', ['sheet' => 'apar']));

    $response->assertOk()
        ->assertDownload('assets-apar-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Lantai')
        ->toContain('Nomor')
        ->toContain('Ruang')
        ->toContain('Merek')
        ->toContain('Catatan')
        ->toContain('APAR-1');
});
