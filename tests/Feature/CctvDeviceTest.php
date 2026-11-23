<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use App\Models\Maintenance;
use App\Models\Troubleshoot;
use App\Models\User;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ChecklistTemplateSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view checklist', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage checklist', 'guard_name' => 'web']);

    $this->seed(AssetCategorySeeder::class);
    $this->seed(ChecklistSeeder::class);
    $this->seed(ChecklistTemplateSeeder::class);
});

function cctvRootCategory(string $name): Category
{
    return Category::query()
        ->whereNull('parent_id')
        ->where('name', $name)
        ->firstOrFail();
}

function cctvDeviceManager(): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo(['view asset management', 'manage asset management']);

    return $user;
}

function storeCctvDeviceWithCameras(TestCase $test, User $user, string $name = 'CCTV-1'): void
{
    $test->actingAs($user);

    $test->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => $name,
        'category_path' => ['DVR'],
        'brand' => 'Hikvision',
        'ip_address' => '10.0.0.10',
        'type' => 'DVR',
        'serial_number' => 'SN-DEVICE',
        'model' => 'DS-7208',
        'room' => 'Ruang NOC',
        'floor' => '3',
        'capacity' => 'SLOT-2 : 2TB',
        'cameras' => [
            [
                'camera_no' => 'CHANEL-1',
                'alias_kamera' => 'AREA PARKIR',
                'lantai_kamera' => '2',
                'catatan' => 'Kamera parkir',
            ],
            [
                'camera_no' => 'CHANEL-2',
                'alias_kamera' => 'EX-PINTU UTAMA',
            ],
            [
                'camera_no' => '',
                'alias_kamera' => '',
            ],
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));
}

test('storing a cctv device creates camera assets in the camera category', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->where('category_id', cctvRootCategory('DVR')->id)
        ->firstOrFail();

    $cameras = Asset::query()
        ->where('parent_asset_id', $device->id)
        ->orderBy('id')
        ->get();

    expect($device->parent_asset_id)->toBeNull()
        ->and($device->capacity)->toBe('SLOT-2 : 2TB')
        ->and($cameras)->toHaveCount(2)
        ->and($cameras[0]->name)->toBe('AREA PARKIR')
        ->and($cameras[1]->name)->toBe('EX-PINTU UTAMA')
        ->and($cameras[0]->category_id)->toBe(cctvRootCategory('Kamera')->id)
        ->and($cameras[0]->details['camera_no'])->toBe('CHANEL-1')
        ->and($cameras[0]->details['lantai_kamera'])->toBe('2:1')
        ->and($cameras[0]->notes)->toBe('Kamera parkir')
        ->and($cameras[0]->brand)->toBe('Hikvision')
        ->and($cameras[0]->capacity)->toBeNull()
        ->and($cameras[0]->in_checklist)->toBeFalse();
});

test('the cctv tables show the device and its cameras separately', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $this->get(route('assets.list', ['sheet' => 'cctv']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.device.groups.data', 1)
            ->has('tables.device.groups.data.0.rows', 1)
            ->where('tables.device.groups.data.0.rows.0.values.nama', 'CCTV-1')
            ->where('tables.device.groups.data.0.rows.0.values.kategori', 'DVR')
            ->where('tables.device.groups.data.0.rows.0.values.jumlah_kamera', '2')
            ->where('tables.camera.hidden', ['nama'])
            ->has('tables.camera.groups.data', 1)
            ->has('tables.camera.groups.data.0.rows', 2)
            ->where('tables.camera.groups.data.0.shared.perangkat', 'CCTV-1')
            ->where('tables.camera.groups.data.0.shared.kategori', 'DVR')
            ->where('tables.camera.groups.data.0.rows.0.values.nama', 'AREA PARKIR')
            ->where('tables.camera.groups.data.0.rows.1.values.nama', 'EX-PINTU UTAMA')
        );
});

test('the cctv camera table paginates independently from the device table', function () {
    $manager = cctvDeviceManager();
    storeCctvDeviceWithCameras($this, $manager, 'CCTV-1');
    storeCctvDeviceWithCameras($this, $manager, 'CCTV-2');

    $this->get(route('assets.list', ['sheet' => 'cctv', 'per_page' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.device.groups.data', 1)
            ->where('tables.device.groups.total', 2)
            ->has('tables.camera.groups.data', 1)
            ->where('tables.camera.groups.total', 2)
            ->has('tables.camera.groups.data.0.rows', 2)
        );
});

test('searching a device name also finds its cameras', function () {
    $manager = cctvDeviceManager();
    storeCctvDeviceWithCameras($this, $manager, 'CCTV-1');
    storeCctvDeviceWithCameras($this, $manager, 'CCTV-2');

    $this->get(route('assets.list', ['sheet' => 'cctv', 'search' => 'CCTV-2']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tables.device.groups.data', 1)
            ->where('tables.device.groups.data.0.rows.0.values.nama', 'CCTV-2')
            ->has('tables.camera.groups.data', 1)
            ->where('tables.camera.groups.data.0.shared.perangkat', 'CCTV-2')
            ->has('tables.camera.groups.data.0.rows', 2)
        );
});

test('a manager can add a camera to an existing device', function () {
    $manager = cctvDeviceManager();
    storeCctvDeviceWithCameras($this, $manager);

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->actingAs($manager);

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'GUDANG',
        'category_path' => ['Kamera'],
        'parent_asset_id' => $device->id,
        'details' => [
            'camera_no' => 'CHANEL-5',
            'alias_kamera' => 'GUDANG',
            'lantai_kamera' => '2',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $camera = Asset::query()->where('name', 'GUDANG')->firstOrFail();

    expect($camera->parent_asset_id)->toBe($device->id)
        ->and($camera->category_id)->toBe(cctvRootCategory('Kamera')->id)
        ->and($camera->brand)->toBe('Hikvision')
        ->and($camera->ip_address)->toBe('10.0.0.10')
        ->and($camera->room)->toBe('Ruang NOC')
        ->and($camera->floor)->toBe('3')
        ->and($camera->details['camera_no'])->toBe('CHANEL-5')
        ->and($camera->details['lantai_kamera'])->toBe('2:1')
        ->and($camera->in_checklist)->toBeFalse()
        ->and(Asset::query()->where('category_id', cctvRootCategory('DVR')->id)->count())->toBe(1);
});

test('a camera requires a device parent and rejects another camera', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $camera = Asset::query()->where('parent_asset_id', $device->id)->firstOrFail();

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'TANPA INDUK',
        'category_path' => ['Kamera'],
        'details' => ['camera_no' => 'CHANEL-9'],
    ])->assertSessionHasErrors('parent_asset_id');

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'SALAH INDUK',
        'category_path' => ['Kamera'],
        'parent_asset_id' => $camera->id,
        'details' => ['camera_no' => 'CHANEL-9'],
    ])->assertSessionHasErrors('parent_asset_id');

    expect(Asset::query()->where('name', 'TANPA INDUK')->exists())->toBeFalse()
        ->and(Asset::query()->where('name', 'SALAH INDUK')->exists())->toBeFalse();
});

test('a camera created without a name is named after its alias or channel', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'category_path' => ['Kamera'],
        'parent_asset_id' => $device->id,
        'details' => [
            'alias_kamera' => 'GUDANG',
            'camera_no' => 'CHANEL-7',
            'lantai_kamera' => '2',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'category_path' => ['Kamera'],
        'parent_asset_id' => $device->id,
        'details' => [
            'camera_no' => 'CHANEL-8',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect(Asset::query()->where('name', 'GUDANG')->where('parent_asset_id', $device->id)->exists())->toBeTrue()
        ->and(Asset::query()->where('name', 'CHANEL-8')->where('parent_asset_id', $device->id)->exists())->toBeTrue();
});

test('editing a camera alias renames the camera asset', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $camera = Asset::query()->where('name', 'AREA PARKIR')->firstOrFail();
    $camera->update(['capacity' => 'SLOT-9 : 9TB']);

    $this->patch(route('assets.update', ['asset' => $camera->id]), [
        'sheet' => 'cctv',
        'category_path' => ['Kamera'],
        'details' => [
            'camera_no' => 'CHANEL-1',
            'alias_kamera' => 'LOBI BARU',
            'lantai_kamera' => '2',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect($camera->fresh()->name)->toBe('LOBI BARU')
        ->and($camera->fresh()->parent_asset_id)->not->toBeNull()
        ->and($camera->fresh()->capacity)->toBeNull();
});

test('the merge command moves camera capacities onto the device', function () {
    $dvr = cctvRootCategory('DVR');
    $kamera = cctvRootCategory('Kamera');

    $device = Asset::factory()->create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-9',
        'capacity' => 'SLOT-2 : 2TB',
    ]);

    $first = Asset::factory()->create([
        'category_id' => $kamera->id,
        'name' => 'AREA PARKIR',
        'parent_asset_id' => $device->id,
        'capacity' => 'SLOT-3 : 1TB',
    ]);

    $second = Asset::factory()->create([
        'category_id' => $kamera->id,
        'name' => 'GUDANG',
        'parent_asset_id' => $device->id,
        'capacity' => 'SLOT-2 : 2TB',
    ]);

    $this->artisan('cctv:merge-capacity')->assertSuccessful();

    expect($device->fresh()->capacity)->toBe('SLOT-2 : 2TB; SLOT-3 : 1TB')
        ->and($first->fresh()->capacity)->toBeNull()
        ->and($second->fresh()->capacity)->toBeNull();

    $this->artisan('cctv:merge-capacity')->assertSuccessful();

    expect($device->fresh()->capacity)->toBe('SLOT-2 : 2TB; SLOT-3 : 1TB')
        ->and($first->fresh()->capacity)->toBeNull();
});

test('creating a device with the same identity updates it and merges capacity', function () {
    $manager = cctvDeviceManager();
    storeCctvDeviceWithCameras($this, $manager);

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->actingAs($manager);

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'CCTV-1',
        'category_path' => ['DVR'],
        'brand' => 'Hikvision',
        'ip_address' => '10.0.0.10',
        'type' => 'DVR',
        'serial_number' => 'SN-DEVICE',
        'model' => 'DS-7208',
        'room' => 'Ruang NOC',
        'floor' => '3',
        'capacity' => 'SLOT-3 : 1TB',
        'cameras' => [
            [
                'camera_no' => 'CHANEL-3',
                'alias_kamera' => 'GUDANG',
            ],
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $device->refresh();

    expect(Asset::query()
        ->where('category_id', cctvRootCategory('DVR')->id)
        ->where('name', 'CCTV-1')
        ->count())->toBe(1)
        ->and($device->capacity)->toBe('SLOT-2 : 2TB; SLOT-3 : 1TB')
        ->and(Asset::query()->where('parent_asset_id', $device->id)->count())->toBe(3)
        ->and(Asset::query()
            ->where('name', 'GUDANG')
            ->where('parent_asset_id', $device->id)
            ->exists())->toBeTrue();
});

test('creating a device with a different identity adds a new device', function () {
    $manager = cctvDeviceManager();
    storeCctvDeviceWithCameras($this, $manager);

    $this->actingAs($manager);

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'CCTV-1',
        'category_path' => ['DVR'],
        'brand' => 'Hikvision',
        'ip_address' => '10.0.0.10',
        'type' => 'DVR',
        'serial_number' => 'SN-OTHER',
        'model' => 'DS-7208',
        'cameras' => [
            [
                'camera_no' => 'CHANEL-3',
                'alias_kamera' => 'GUDANG',
            ],
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect(Asset::query()
        ->where('category_id', cctvRootCategory('DVR')->id)
        ->where('name', 'CCTV-1')
        ->count())->toBe(2);
});

test('creating the same camera twice updates it instead of duplicating', function () {
    $manager = cctvDeviceManager();
    storeCctvDeviceWithCameras($this, $manager);

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $payload = [
        'sheet' => 'cctv',
        'category_path' => ['Kamera'],
        'parent_asset_id' => $device->id,
        'details' => [
            'camera_no' => 'CHANEL-6',
            'alias_kamera' => 'LAB',
            'lantai_kamera' => '2',
        ],
    ];

    $this->actingAs($manager);

    $this->post(route('assets.store-bulk'), $payload)
        ->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $this->post(route('assets.store-bulk'), $payload)
        ->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect(Asset::query()
        ->where('name', 'LAB')
        ->where('parent_asset_id', $device->id)
        ->count())->toBe(1);
});

test('multi-line capacity is stored on a single semicolon line', function () {
    $this->actingAs(cctvDeviceManager());

    $this->post(route('assets.store-bulk'), [
        'sheet' => 'cctv',
        'name' => 'CCTV-7',
        'category_path' => ['DVR'],
        'capacity' => "SLOT-2 : 2TB\r\nSLOT-3 : 1TB\nSLOT-2 : 2TB",
        'cameras' => [
            [
                'camera_no' => 'CHANEL-1',
                'alias_kamera' => 'AREA PARKIR',
            ],
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect(Asset::query()->where('name', 'CCTV-7')->firstOrFail()->capacity)
        ->toBe('SLOT-2 : 2TB; SLOT-3 : 1TB');
});

test('the duplicate command merges device records with the same identity', function () {
    $dvr = cctvRootCategory('DVR');
    $kamera = cctvRootCategory('Kamera');

    $identity = [
        'category_id' => $dvr->id,
        'name' => 'CCTV-8',
        'brand' => 'SAMSUNG',
        'ip_address' => '10.0.0.8',
        'serial_number' => 'SN-8',
        'model' => 'SRD-8',
    ];

    $survivor = Asset::factory()->create([
        ...$identity,
        'capacity' => 'SLOT-1 : 1TB',
    ]);

    $duplicate = Asset::factory()->create([
        ...$identity,
        'capacity' => 'SLOT-2 : 2TB',
    ]);

    $camera = Asset::factory()->create([
        'category_id' => $kamera->id,
        'name' => 'GUDANG',
        'parent_asset_id' => $duplicate->id,
    ]);

    $maintenance = Maintenance::factory()->create(['asset_id' => $duplicate->id]);
    $troubleshoot = Troubleshoot::factory()->create(['asset_id' => $duplicate->id]);

    $this->artisan('cctv:merge-duplicate-devices')->assertSuccessful();

    expect(Asset::query()->find($duplicate->id))->toBeNull()
        ->and($survivor->fresh()->capacity)->toBe('SLOT-1 : 1TB; SLOT-2 : 2TB')
        ->and($camera->fresh()->parent_asset_id)->toBe($survivor->id)
        ->and($maintenance->fresh()->asset_id)->toBe($survivor->id)
        ->and($troubleshoot->fresh()->asset_id)->toBe($survivor->id);

    $this->artisan('cctv:merge-duplicate-devices')->assertSuccessful();

    expect($survivor->fresh()->capacity)->toBe('SLOT-1 : 1TB; SLOT-2 : 2TB');
});

test('asset settings shows one row per device with cameras as members', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->get(route('assets.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.name', 'CCTV-1 DVR')
            ->where('assets.data.0.asset_ids', [$device->id])
            ->has('assets.data.0.members', 3)
            ->where('assets.data.0.members.0.name', 'CCTV-1 DVR')
            ->where('assets.data.0.members.1.name', 'AREA PARKIR Kamera')
            ->where('assets.data.0.scan_group.name', 'CCTV-1')
            ->where('assets.data.0.scan_group.category_id', cctvRootCategory('DVR')->id)
        );
});

test('the category list counts the device and its cameras separately', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $this->get(route('asset-categories.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $counts = collect($page->toArray()['props']['categories'])
                ->pluck('assets_count', 'name');

            expect($counts['DVR'])->toBe(1)
                ->and($counts['Kamera'])->toBe(2);
        });
});

test('the cctv checklist has one row per device and none for cameras', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $device->update([
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ]);

    $items = ChecklistItem::query()
        ->where('checklist_category_id', $category->id)
        ->where('is_active', true)
        ->get();

    expect($items)->toHaveCount(1)
        ->and($items->first()->label)->toBe('CCTV-1 DVR')
        ->and($items->first()->asset_id)->toBe($device->id);
});

test('scanning a camera channel shows its device', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $camera = Asset::query()->where('parent_asset_id', $device->id)->firstOrFail();

    $this->actingAs(User::factory()->create(['role' => 'user']));

    $this->get(route('assets.scan.show', $camera))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.id', $device->id)
            ->where('asset.display_name', 'CCTV-1 DVR')
            ->has('members', 3)
        );
});

test('the group scan page resolves a cctv device by name and category', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->actingAs(User::factory()->create(['role' => 'user']));

    $this->get(route('assets.scan.group', [
        'name' => 'CCTV-1',
        'category' => cctvRootCategory('DVR')->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.id', $device->id)
            ->where('asset.display_name', 'CCTV-1 DVR')
            ->has('members', 3)
        );
});

test('older cctv stickers using category_id still resolve', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->actingAs(User::factory()->create(['role' => 'user']));

    $this->get(route('assets.scan.group', [
        'name' => 'CCTV-1',
        'category_id' => cctvRootCategory('DVR')->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('asset.id', $device->id));
});

test('deleting a cctv device deletes its camera channels', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->delete(route('assets.destroy', [
        'asset' => $device->id,
        'sheet' => 'cctv',
    ]))->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    expect(Asset::query()->where('parent_asset_id', $device->id)->count())->toBe(0)
        ->and(Asset::query()->find($device->id))->toBeNull();
});

test('updating a cctv device copies its shared fields to its cameras', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $this->patch(route('assets.update', ['asset' => $device->id]), [
        'sheet' => 'cctv',
        'name' => 'CCTV-1',
        'category_path' => ['DVR'],
        'brand' => 'Dahua',
        'ip_address' => '10.0.0.99',
        'room' => 'Ruang Server',
        'floor' => '4',
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $cameras = Asset::query()->where('parent_asset_id', $device->id)->get();

    expect($cameras)->toHaveCount(2);

    foreach ($cameras as $camera) {
        expect($camera->brand)->toBe('Dahua')
            ->and($camera->ip_address)->toBe('10.0.0.99')
            ->and($camera->room)->toBe('Ruang Server')
            ->and($camera->floor)->toBe('4')
            ->and($camera->parent_asset_id)->toBe($device->id);
    }
});

test('the settings bulk update never puts a camera channel on the checklist', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $cameraIds = Asset::query()->where('parent_asset_id', $device->id)->pluck('id')->all();

    $this->patch(route('assets.settings.bulk'), [
        'asset_ids' => [$device->id, ...$cameraIds],
        'in_checklist' => true,
    ])->assertRedirect();

    expect($device->fresh()->in_checklist)->toBeTrue()
        ->and(Asset::query()->whereIn('id', $cameraIds)->where('in_checklist', true)->count())->toBe(0);
});

test('editing a camera channel keeps it linked to its device', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $device = Asset::query()
        ->where('name', 'CCTV-1')
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $camera = Asset::query()->where('parent_asset_id', $device->id)->orderBy('id')->firstOrFail();

    $this->patch(route('assets.update', ['asset' => $camera->id]), [
        'sheet' => 'cctv',
        'name' => $camera->name,
        'category_path' => ['Kamera'],
        'details' => [
            'camera_no' => 'CHANEL-1',
            'alias_kamera' => 'AREA PARKIR',
            'lantai_kamera' => '5',
        ],
    ])->assertRedirect(route('assets.list', ['sheet' => 'cctv']));

    $camera->refresh();

    expect($camera->parent_asset_id)->toBe($device->id)
        ->and($camera->details['lantai_kamera'])->toBe('5:1');
});

test('the cctv export fills the device name and camera count', function () {
    storeCctvDeviceWithCameras($this, cctvDeviceManager());

    $response = $this->get(route('assets.export', ['sheet' => 'cctv']));
    $response->assertOk();

    $lines = array_map(
        fn (string $line): array => str_getcsv($line, ',', '"', '\\'),
        explode("\n", trim($response->streamedContent())),
    );
    $header = $lines[0];
    $perangkatColumn = array_search('Nama Perangkat', $header, true);
    $jumlahColumn = array_search('Jumlah Kamera', $header, true);

    $deviceLine = collect($lines)->first(fn (array $line): bool => ($line[0] ?? '') === 'CCTV-1');
    $cameraLine = collect($lines)->first(fn (array $line): bool => ($line[0] ?? '') === 'AREA PARKIR');

    expect($perangkatColumn)->not->toBeFalse()
        ->and($jumlahColumn)->not->toBeFalse()
        ->and($cameraLine[$perangkatColumn])->toBe('CCTV-1')
        ->and($deviceLine[$jumlahColumn])->toBe('2');
});

test('the split command converts legacy channel records and removes empty rows', function () {
    $dvr = cctvRootCategory('DVR');

    foreach ([
        ['camera_no' => 'CHANEL-1', 'alias_kamera' => 'AREA PARKIR'],
        ['camera_no' => 'CHANEL-2', 'alias_kamera' => 'EX-PINTU UTAMA'],
        ['camera_no' => '', 'alias_kamera' => ''],
    ] as $details) {
        Asset::factory()->create([
            'category_id' => $dvr->id,
            'name' => 'CCTV-9',
            'details' => $details,
        ]);
    }

    $this->artisan('cctv:split-devices')->assertSuccessful();

    $device = Asset::query()
        ->where('name', 'CCTV-9')
        ->where('category_id', $dvr->id)
        ->whereNull('parent_asset_id')
        ->firstOrFail();

    $cameras = Asset::query()
        ->where('parent_asset_id', $device->id)
        ->orderBy('id')
        ->get();

    expect(Asset::query()->where('name', 'CCTV-9')->where('category_id', $dvr->id)->count())->toBe(1)
        ->and($cameras)->toHaveCount(2)
        ->and($cameras[0]->name)->toBe('AREA PARKIR')
        ->and($cameras[0]->category_id)->toBe(cctvRootCategory('Kamera')->id);

    $this->artisan('cctv:split-devices')->assertSuccessful();

    expect(Asset::query()->where('name', 'CCTV-9')->where('category_id', $dvr->id)->count())->toBe(1)
        ->and(Asset::query()->where('parent_asset_id', $device->id)->count())->toBe(2);
});
