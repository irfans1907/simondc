<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

function settingsManager(): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');

    return $user;
}

test('guests are redirected from the asset settings page', function () {
    $this->get(route('assets.settings'))->assertRedirect(route('login'));
});

test('a view-only user sees the settings but cannot change them', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->get(route('assets.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/settings', false)
            ->has('assets.data', 1)
            ->where('canManage', false)
        );

    $this->patch(route('assets.settings.update', $asset), ['in_checklist' => false])
        ->assertForbidden();
});

test('a manager can toggle a scan flag', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create([
        'in_checklist' => true,
        'in_maintenance' => true,
    ]);

    $this->actingAs($user);

    $this->patch(route('assets.settings.update', $asset), ['in_checklist' => false])
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('assets', [
        'id' => $asset->id,
        'in_checklist' => false,
        'in_maintenance' => true,
    ]);
});

test('the scan flags must be boolean', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->patch(route('assets.settings.update', $asset), ['in_checklist' => 'maybe'])
        ->assertSessionHasErrors('in_checklist');
});

test('the settings list can search assets by name', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    Asset::factory()->create(['name' => 'UPS Alpha Search']);
    Asset::factory()->create(['name' => 'Switch Beta Search']);

    $this->actingAs($user);

    $this->get(route('assets.settings', [
        'search' => 'Alpha',
        'search_column' => 'name',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.name', 'UPS Alpha Search')
        );
});

test('the settings list shows the FAP system suffix on duplicate panel names', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $category = Category::factory()->create(['name' => 'FSS Panel']);

    Asset::factory()->create([
        'name' => 'FM100',
        'category_id' => $category->id,
        'details' => ['system' => 'NN100.A'],
    ]);

    Asset::factory()->create([
        'name' => 'FM100',
        'category_id' => $category->id,
        'details' => ['system' => 'NN100.B'],
    ]);

    Asset::factory()->create([
        'name' => 'Switch Core',
        'category_id' => $category->id,
    ]);

    $this->actingAs($user);

    $this->get(route('assets.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 3)
            ->where('assets.data.0.name', 'FM100 - NN100.A')
            ->where('assets.data.1.name', 'FM100 - NN100.B')
            ->where('assets.data.2.name', 'Switch Core')
        );

    $this->get(route('assets.settings', [
        'search' => 'NN100.A',
        'search_column' => 'name',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.name', 'FM100 - NN100.A')
        );
});

test('the settings page lists the active checklist categories', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    Asset::factory()->create();
    ChecklistCategory::factory()->create(['name' => 'UPS 2', 'slug' => 'ups-2']);
    ChecklistCategory::factory()->create(['name' => 'Nonaktif', 'is_active' => false]);

    $this->actingAs($user);

    $this->get(route('assets.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('checklistCategories', 1)
            ->where('checklistCategories.0.name', 'UPS 2')
        );
});

test('a manager can file an asset under a checklist category', function () {
    $category = ChecklistCategory::factory()->create(['name' => 'UPS B', 'slug' => 'ups-b']);
    $asset = Asset::factory()->create([
        'in_checklist' => false,
        'checklist_category_id' => null,
    ]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.update', $asset), [
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ])->assertSessionHas('inertia.flash_data');

    expect($asset->fresh()->checklist_category_id)->toBe($category->id)
        ->and($asset->fresh()->in_checklist)->toBeTrue();
});

test('an asset can stay in the belum dipilih state', function () {
    $asset = Asset::factory()->create([
        'in_checklist' => false,
        'checklist_category_id' => null,
    ]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.update', $asset), [
        'in_checklist' => true,
        'checklist_category_id' => null,
    ])->assertSessionHas('inertia.flash_data');

    expect($asset->fresh()->in_checklist)->toBeTrue()
        ->and($asset->fresh()->checklist_category_id)->toBeNull();
});

test('clearing the checklist category removes the asset from the checklist', function () {
    $category = ChecklistCategory::factory()->create();
    $asset = Asset::factory()->create([
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.update', $asset), [
        'in_checklist' => false,
        'checklist_category_id' => null,
    ])->assertSessionHas('inertia.flash_data');

    expect($asset->fresh()->in_checklist)->toBeFalse()
        ->and($asset->fresh()->checklist_category_id)->toBeNull();
});

test('the checklist category must exist and be active', function () {
    $asset = Asset::factory()->create();
    $inactive = ChecklistCategory::factory()->create(['is_active' => false]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.update', $asset), [
        'checklist_category_id' => $inactive->id,
    ])->assertSessionHasErrors('checklist_category_id');

    $this->patch(route('assets.settings.update', $asset), [
        'checklist_category_id' => 999_999,
    ])->assertSessionHasErrors('checklist_category_id');
});

test('ups assets that share a name are grouped into one settings row', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Modul']);

    $first = Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-B',
    ]);
    $second = Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'ups-b',
    ]);

    $server = Category::create(['parent_id' => null, 'name' => 'Server']);
    Asset::factory()->create([
        'category_id' => $server->id,
        'name' => 'UPS-B',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 2)
            ->where('assets.data', function ($rows) use ($first, $second) {
                $group = collect($rows)->firstWhere('id', $first->id);

                if ($group === null) {
                    return false;
                }

                $ids = collect($group['asset_ids'])->sort()->values()->all();
                $expected = collect([$first->id, $second->id])->sort()->values()->all();

                return $ids === $expected && count($group['members']) === 2;
            })
        );
});

test('a bulk settings update reaches every asset in the group', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);
    $modul = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Modul']);

    $first = Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-B',
        'in_maintenance' => false,
    ]);
    $second = Asset::factory()->create([
        'category_id' => $modul->id,
        'name' => 'UPS-B',
        'in_maintenance' => false,
    ]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.bulk'), [
        'asset_ids' => [$first->id, $second->id],
        'in_maintenance' => true,
    ])->assertSessionHas('inertia.flash_data');

    expect($first->fresh()->in_maintenance)->toBeTrue()
        ->and($second->fresh()->in_maintenance)->toBeTrue();
});

test('the bulk settings update validates the asset ids', function () {
    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.bulk'), [
        'asset_ids' => [999_999],
        'in_checklist' => true,
    ])->assertSessionHasErrors('asset_ids.0');

    $this->patch(route('assets.settings.bulk'), [
        'asset_ids' => [],
        'in_checklist' => true,
    ])->assertSessionHasErrors('asset_ids');
});

test('a bulk checklist assignment materialises items for the group', function () {
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);
    $battery = Category::create(['parent_id' => $ups->id, 'name' => 'Battery']);

    $category = ChecklistCategory::create([
        'slug' => 'ups-2',
        'name' => 'UPS 2',
        'time_slots' => ['09:00'],
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ChecklistItemTemplate::create([
        'category_id' => $ups->id,
        'label' => 'VR',
        'unit' => 'V',
        'input_type' => 'number',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $first = Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-2',
        'in_checklist' => false,
        'checklist_category_id' => null,
    ]);

    $second = Asset::factory()->create([
        'category_id' => $battery->id,
        'name' => 'UPS-2',
        'in_checklist' => false,
        'checklist_category_id' => null,
    ]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.bulk'), [
        'asset_ids' => [$first->id, $second->id],
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ])->assertSessionHas('inertia.flash_data');

    expect($first->fresh()->in_checklist)->toBeTrue()
        ->and($second->fresh()->in_checklist)->toBeTrue()
        ->and(ChecklistItem::query()
            ->where('checklist_category_id', $category->id)
            ->where('is_active', true)
            ->count())->toBe(1)
        ->and(ChecklistItem::query()
            ->where('asset_id', $first->id)
            ->where('is_active', true)
            ->count())->toBe(1);
});

test('a bulk checklist removal deactivates the generated items', function () {
    $pac = Category::create(['parent_id' => null, 'name' => 'PAC']);

    $category = ChecklistCategory::create([
        'slug' => 'pac-ac',
        'name' => 'PAC & AC',
        'time_slots' => ['09:00'],
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ChecklistItemTemplate::create([
        'category_id' => $pac->id,
        'label' => 'Temperature',
        'unit' => '°C',
        'input_type' => 'number',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $asset = Asset::factory()->create([
        'category_id' => $pac->id,
        'name' => 'PAC-1',
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ]);

    $this->actingAs(settingsManager());

    $this->patch(route('assets.settings.bulk'), [
        'asset_ids' => [$asset->id],
        'in_checklist' => false,
    ])->assertSessionHas('inertia.flash_data');

    expect(ChecklistItem::query()
        ->where('asset_id', $asset->id)
        ->where('is_active', true)
        ->count())->toBe(0);
});
