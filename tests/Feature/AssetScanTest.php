<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistDayNote;
use App\Models\ChecklistEntry;
use App\Models\ChecklistItem;
use App\Models\ChecklistSlotNote;
use App\Models\Maintenance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

test('guests are redirected from the scan pages', function () {
    $asset = Asset::factory()->create();

    $this->get(route('assets.scan'))->assertRedirect(route('login'));
    $this->get(route('assets.scan.show', $asset))->assertRedirect(route('login'));
});

test('an authenticated user without asset permission can open the scanner', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.scan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/scan/index', false)
        );
});

test('the scan target page shows the asset details and its flags', function () {
    $user = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create([
        'name' => 'UPS Pusat Data',
        'in_checklist' => true,
        'in_maintenance' => false,
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/scan/show', false)
            ->where('asset.id', $asset->id)
            ->where('asset.name', 'UPS Pusat Data')
            ->where('asset.in_checklist', true)
            ->where('asset.in_maintenance', false)
            ->where('asset.category', $asset->category->name)
            ->where('canManage', false)
            ->has('assetOptions')
            ->has('maintenances', 0)
        );
});

test('the scan target page lets a manager add maintenance for the scanned asset', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->has('assetOptions', 1)
            ->where('assetOptions.0.id', $asset->id)
        );
});

test('the scan target page shows the top-level category of a subcategorized asset', function () {
    $user = User::factory()->create(['role' => 'user']);
    $parent = Category::factory()->create(['name' => 'UPS Frame']);
    $subcategory = Category::factory()->create([
        'name' => 'Modul 1',
        'parent_id' => $parent->id,
    ]);
    $asset = Asset::factory()->create(['category_id' => $subcategory->id]);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.category', 'UPS Frame')
        );
});

test('the scan target page lists only the scanned asset maintenance history', function () {
    $user = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create();
    $other = Asset::factory()->create();
    Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => Maintenance::STATUS_DONE,
        'result' => 'Normal',
    ]);
    Maintenance::factory()->create(['asset_id' => $other->id]);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenances', 1)
            ->where('maintenances.0.status', Maintenance::STATUS_DONE)
            ->where('maintenances.0.result', 'Normal')
        );
});

test('an unknown asset cannot be opened from a scanned code', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get('/assets/999999/scan')->assertNotFound();
});

test('the grouped scan page resolves a UPS group by name', function () {
    $user = User::factory()->create(['role' => 'user']);
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'Modul']);
    $battery = Category::create(['parent_id' => $ups->id, 'name' => 'Battery']);

    $first = Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-2',
        'serial_number' => 'SN-MODUL',
    ]);

    Asset::factory()->create([
        'category_id' => $battery->id,
        'name' => 'UPS-2',
        'serial_number' => 'SN-BATT',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.group', ['name' => 'UPS-2']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/scan/show', false)
            ->where('asset.id', $first->id)
            ->has('members', 2)
            ->where('members.0.serial_number', 'SN-MODUL')
            ->where('members.1.serial_number', 'SN-BATT')
        );
});

test('the grouped scan page ignores assets outside the UPS categories', function () {
    $user = User::factory()->create(['role' => 'user']);
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'Modul']);
    $network = Category::create(['parent_id' => null, 'name' => 'Network']);

    Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-9',
    ]);

    Asset::factory()->create([
        'category_id' => $network->id,
        'name' => 'UPS-9',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.group', ['name' => 'UPS-9']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('members', 1));
});

test('an unknown group name cannot be opened from a scanned code', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.scan.group', ['name' => 'UPS-UNKNOWN']))
        ->assertNotFound();
});

test('the scan target page of a grouped asset lists its siblings', function () {
    $user = User::factory()->create(['role' => 'user']);
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'Modul']);
    $battery = Category::create(['parent_id' => $ups->id, 'name' => 'Battery']);

    $asset = Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-A',
    ]);

    Asset::factory()->create([
        'category_id' => $battery->id,
        'name' => 'UPS-A',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('members', 2));
});

test('a single asset scan page has one member', function () {
    $user = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create(['name' => 'PDU-X']);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 1)
            ->where('members.0.id', $asset->id)
        );
});

test('group members expose the sheet details instead of empty model columns', function () {
    $user = User::factory()->create(['role' => 'user']);
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'Modul']);
    $battery = Category::create(['parent_id' => $ups->id, 'name' => 'Battery']);

    Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-2',
        'details' => [
            'frame_sn' => 'UU0782',
            'modul_nomor' => '1',
            'modul_sn' => 'MM001801',
            'modul_kva' => '40',
        ],
    ]);

    Asset::factory()->create([
        'category_id' => $battery->id,
        'name' => 'UPS-2',
        'details' => [
            'batterai_bank_no' => '1',
            'batterai_merek' => 'FIAMM',
            'batterai_model' => '12SP',
            'batterai_series' => '12SP120',
            'batterai_v_ah' => '12V 120AH',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.group', ['name' => 'UPS-2']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('members.0.identity', 3)
            ->where('members.0.identity.0.label', 'Nomor')
            ->where('members.0.identity.0.value', '1')
            ->where('members.0.identity.1.label', 'SN')
            ->where('members.0.identity.1.value', 'MM001801')
            ->where('members.0.identity.2.value', '40')
            ->has('members.1.identity', 5)
            ->where('members.1.identity.0.label', 'Bank No')
            ->where('members.1.identity.0.value', '1')
            ->where('members.1.identity.2.value', '12SP')
            ->where('members.1.identity.3.value', '12SP120')
        );
});

test('the scan page shows the checklist configured for the asset group', function () {
    $user = User::factory()->create(['role' => 'user']);
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'Modul']);

    $asset = Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-2',
        'in_checklist' => true,
    ]);

    $category = ChecklistCategory::factory()->create([
        'name' => 'UPS 2',
        'time_slots' => ['09:00', '21:00'],
    ]);

    $item = ChecklistItem::factory()->create([
        'checklist_category_id' => $category->id,
        'asset_id' => $asset->id,
        'scope' => 'asset',
        'section' => 'a. Voltage IN-UPS',
        'group' => 'UPS-2',
        'label' => 'VR',
        'unit' => 'V',
        'input_type' => 'number',
        'sort_order' => 0,
    ]);

    ChecklistItem::factory()->create([
        'checklist_category_id' => $category->id,
        'asset_id' => $asset->id,
        'scope' => 'asset',
        'section' => 'Battery UPS',
        'group' => 'UPS-2',
        'label' => 'Capacity',
        'unit' => '%',
        'input_type' => 'number',
        'sort_order' => 1,
    ]);

    ChecklistEntry::factory()->create([
        'checklist_item_id' => $item->id,
        'entry_date' => today()->toDateString(),
        'time_slot' => '09:00',
        'value' => 220,
    ]);

    ChecklistDayNote::factory()->create([
        'checklist_category_id' => $category->id,
        'entry_date' => today()->toDateString(),
        'risk_level' => 'HIGH',
        'note' => 'Suhu ruang tinggi',
    ]);

    ChecklistSlotNote::factory()->create([
        'checklist_category_id' => $category->id,
        'entry_date' => today()->toDateString(),
        'time_slot' => '09:00',
        'risk_level' => 'HIGH',
        'note' => 'Suhu ruang tinggi',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('checklist.category.id', $category->id)
            ->where('checklist.category.name', 'UPS 2')
            ->where('checklist.category.time_slots', ['09:00', '21:00'])
            ->has('checklist.items', 2)
            ->where('checklist.items.0.section', 'a. Voltage IN-UPS')
            ->where('checklist.items.0.label', 'VR')
            ->where('checklist.items.0.unit', 'V')
            ->has('checklist.entries', 1)
            ->where('checklist.entries.0.item_id', $item->id)
            ->where('checklist.entries.0.time_slot', '09:00')
            ->where('checklist.entries.0.value', 220)
            ->where('checklist.dayNote.risk_level', 'HIGH')
            ->where('checklist.dayNote.note', 'Suhu ruang tinggi')
            ->has('checklist.slotNotes', 1)
            ->where('checklist.slotNotes.0.time_slot', '09:00')
            ->where('checklist.slotNotes.0.risk_level', 'HIGH')
            ->where('canManageChecklist', false)
        );
});

test('an asset without configured checklist items gets no checklist payload', function () {
    $user = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create(['name' => 'PDU-X']);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('checklist', null));
});

test('the scan page tells checklist managers they can save entries', function () {
    $user = User::factory()->create(['role' => 'user']);
    Permission::firstOrCreate(['name' => 'manage checklist', 'guard_name' => 'web']);
    $user->givePermissionTo('manage checklist');
    $asset = Asset::factory()->create(['name' => 'PDU-X']);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManageChecklist', true)
        );
});

test('saving from the scan page returns to the scan page', function () {
    Permission::firstOrCreate(['name' => 'manage checklist', 'guard_name' => 'web']);
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage checklist');

    $category = ChecklistCategory::factory()->create([
        'time_slots' => ['09:00', '21:00'],
    ]);

    $item = ChecklistItem::factory()->create([
        'checklist_category_id' => $category->id,
        'label' => 'VR',
        'input_type' => 'number',
    ]);

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), [
        'category_id' => $category->id,
        'entry_date' => today()->toDateString(),
        'risk_level' => 'LOW',
        'note' => null,
        'entries' => [
            [
                'item_id' => $item->id,
                'time_slot' => '09:00',
                'value' => 220,
                'status' => null,
                'note' => null,
            ],
        ],
        'redirect' => '/assets/scan/group?name=PDU-X',
    ])->assertRedirect('/assets/scan/group?name=PDU-X');

    $this->assertDatabaseHas('checklist_entries', [
        'checklist_item_id' => $item->id,
        'time_slot' => '09:00',
        'value' => 220,
    ]);
});

test('the scan page shows the FAP system suffix without renaming the asset', function () {
    $user = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create([
        'name' => 'FM100',
        'details' => ['system' => 'NN100.A'],
    ]);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.name', 'FM100')
            ->where('asset.display_name', 'FM100 - NN100.A')
        );
});
