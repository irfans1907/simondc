<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

/**
 * Build an asset payload with a category path of plain names.
 */
function assetPayload(array $overrides = []): array
{
    return array_merge(Asset::factory()->raw([
        'category_id' => null,
    ]), [
        'category_path' => ['Server'],
    ], $overrides);
}

test('guests are redirected from the asset management page', function () {
    $this->get(route('assets.index'))->assertRedirect(route('login'));
});

test('the asset module root redirects to the overview page', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.index'))->assertRedirect(route('assets.overview'));
});

test('a user with only the view permission can view the asset index read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});

test('a user without view or manage permission cannot view the asset index', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.index'))->assertForbidden();
});

test('a user with the manage asset management permission can access the page', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');

    $this->actingAs($user);

    $this->get(route('assets.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', true));
});

test('the asset overview page renders empty summary cards', function () {
    $superadmin = superadminUser();
    Asset::factory()->count(3)->create();

    $this->actingAs($superadmin);

    $this->get(route('assets.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dynamic/assets/overview', false)
            ->where('summary.total_assets', null)
            ->where('summary.total_categories', null)
            ->where('summary.total_maintenances', null)
            ->where('summary.total_troubleshoots', null)
        );
});

test('the overview child page renders the summary at /assets/overview', function () {
    $superadmin = superadminUser();
    Asset::factory()->count(2)->create();

    $assetMenu = Menu::create([
        'name' => 'Asset Management',
        'group' => 'Main Menu',
        'url' => '/assets',
        'icon' => 'Boxes',
        'order' => 3,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Overview',
        'group' => 'Main Menu',
        'url' => '/assets/overview',
        'parent_id' => $assetMenu->id,
        'order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get(route('assets.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dynamic/assets/overview', false)
            ->where('summary.total_assets', null)
            ->has('menu')
            ->has('activeChild')
            ->where('menu.url', '/assets')
            ->where('activeChild.url', '/assets/overview')
            ->has('menus')
        );
});

test('assets can be searched by name', function () {
    $superadmin = superadminUser();
    Asset::factory()->create(['name' => 'UPS Utama', 'serial_number' => 'SN-AAA-111', 'room' => 'Ruang Server']);
    Asset::factory()->create(['name' => 'Kamera Lobby', 'serial_number' => 'SN-BBB-222', 'room' => 'Ruang Server']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', ['search' => 'UPS']))
        ->streamedContent();

    expect($csv)->toContain('UPS Utama')
        ->not->toContain('Kamera Lobby');
});

test('assets can be searched by serial number', function () {
    $superadmin = superadminUser();
    Asset::factory()->create(['name' => 'UPS Utama', 'serial_number' => 'SN-AAA-111']);
    Asset::factory()->create(['name' => 'Kamera Lobby', 'serial_number' => 'SN-BBB-222']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', ['search' => 'SN-BBB']))
        ->streamedContent();

    expect($csv)->toContain('Kamera Lobby')
        ->not->toContain('UPS Utama');
});

test('the export includes every asset', function () {
    $superadmin = superadminUser();
    $names = collect(range(1, 15))->map(fn (int $number): string => sprintf('Aset %02d', $number));

    $names->each(fn (string $name) => Asset::factory()->create(['name' => $name]));

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export'))->streamedContent();

    foreach ($names as $name) {
        expect($csv)->toContain($name);
    }
});

test('an asset can be created with a root category only', function () {
    $superadmin = superadminUser();
    $payload = assetPayload(['category_path' => ['Server']]);

    $this->actingAs($superadmin);

    $this->post(route('assets.store'), $payload)
        ->assertRedirect(route('assets.list'));

    $category = Category::whereNull('parent_id')->where('name', 'Server')->first();

    expect($category)->not->toBeNull();

    $this->assertDatabaseHas('assets', [
        'name' => $payload['name'],
        'serial_number' => $payload['serial_number'],
        'category_id' => $category->id,
    ]);
});

test('creating an asset with a new subcategory nests it under the category', function () {
    $superadmin = superadminUser();
    $payload = assetPayload([
        'category_path' => ['UPS FRAME 1', 'Modul Eksternal'],
    ]);

    $this->actingAs($superadmin);

    $this->post(route('assets.store'), $payload)
        ->assertRedirect(route('assets.list'));

    $root = Category::whereNull('parent_id')->where('name', 'UPS FRAME 1')->first();
    $child = Category::where('parent_id', $root->id)->where('name', 'Modul Eksternal')->first();

    expect($root)->not->toBeNull();
    expect($child)->not->toBeNull();

    $this->assertDatabaseHas('assets', [
        'name' => $payload['name'],
        'category_id' => $child->id,
    ]);
});

test('creating an asset with a full four-level path creates the chain', function () {
    $superadmin = superadminUser();
    $payload = assetPayload([
        'sheet' => 'ups-batt',
        'category_path' => ['UPS', 'UPS Frame', 'Modul 1', 'Spare Part'],
    ]);

    $this->actingAs($superadmin);

    $this->post(route('assets.store'), $payload)
        ->assertRedirect(route('assets.list', ['sheet' => 'ups-batt']));

    $root = Category::whereNull('parent_id')->where('name', 'UPS')->first();
    $level2 = Category::where('parent_id', $root->id)->where('name', 'UPS Frame')->first();
    $level3 = Category::where('parent_id', $level2->id)->where('name', 'Modul 1')->first();
    $level4 = Category::where('parent_id', $level3->id)->where('name', 'Spare Part')->first();

    expect($root)->not->toBeNull();
    expect($level2)->not->toBeNull();
    expect($level3)->not->toBeNull();
    expect($level4)->not->toBeNull();

    $this->assertDatabaseHas('assets', [
        'name' => $payload['name'],
        'category_id' => $level4->id,
    ]);
});

test('category path names are matched case-insensitively within their scope', function () {
    $superadmin = superadminUser();
    $root = Category::create(['parent_id' => null, 'name' => 'Server']);
    $child = Category::create(['parent_id' => $root->id, 'name' => 'Rack Mount']);
    $payload = assetPayload([
        'category_path' => ['server', 'rack mount'],
    ]);

    $this->actingAs($superadmin);

    $this->post(route('assets.store'), $payload)
        ->assertRedirect(route('assets.list'));

    $this->assertDatabaseCount('categories', 2);

    $this->assertDatabaseHas('assets', [
        'name' => $payload['name'],
        'category_id' => $child->id,
    ]);
});

test('creating an asset only requires a name and category', function () {
    $superadmin = superadminUser();

    $this->actingAs($superadmin);

    $this->post(route('assets.store'), [])
        ->assertSessionHasErrors(['name', 'category_path']);

    $this->assertDatabaseCount('assets', 0);

    $this->post(route('assets.store'), [
        'name' => 'Aset Minimal',
        'category_path' => ['Server'],
    ])->assertRedirect(route('assets.list'));

    $this->assertDatabaseHas('assets', [
        'name' => 'Aset Minimal',
        'brand' => null,
        'serial_number' => null,
        'room' => null,
    ]);
});

test('an asset can be updated with a deeper category path', function () {
    $superadmin = superadminUser();
    $asset = Asset::factory()->create();

    $this->actingAs($superadmin);

    $this->patch(route('assets.update', $asset), assetPayload([
        'name' => 'Updated Asset Name',
        'category_path' => ['Genset', 'Silent', 'Panel ATS'],
    ]))
        ->assertRedirect(route('assets.list'));

    $root = Category::whereNull('parent_id')->where('name', 'Genset')->first();
    $level2 = Category::where('parent_id', $root->id)->where('name', 'Silent')->first();
    $level3 = Category::where('parent_id', $level2->id)->where('name', 'Panel ATS')->first();

    $this->assertDatabaseHas('assets', [
        'id' => $asset->id,
        'name' => 'Updated Asset Name',
        'category_id' => $level3->id,
    ]);
});

test('updating an asset with a shorter path moves it back up the tree', function () {
    $superadmin = superadminUser();
    $root = Category::create(['parent_id' => null, 'name' => 'Server']);
    $child = Category::create(['parent_id' => $root->id, 'name' => 'Rack Mount']);
    $asset = Asset::factory()->create(['category_id' => $child->id]);

    $this->actingAs($superadmin);

    $this->patch(route('assets.update', $asset), assetPayload([
        'category_path' => ['Server'],
    ]))
        ->assertRedirect(route('assets.list'));

    $this->assertDatabaseHas('assets', [
        'id' => $asset->id,
        'category_id' => $root->id,
    ]);
});

test('an asset can be deleted', function () {
    $superadmin = superadminUser();
    $asset = Asset::factory()->create();

    $this->actingAs($superadmin);

    $this->delete(route('assets.destroy', $asset))
        ->assertRedirect(route('assets.list'));

    $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
});

test('guests are redirected from the asset export', function () {
    $this->get(route('assets.export'))->assertRedirect(route('login'));
});

test('a user without the manage permission cannot export assets', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.export'))->assertForbidden();
});

test('the export returns a csv download of all assets', function () {
    $superadmin = superadminUser();
    Asset::factory()->count(3)->create();

    $this->actingAs($superadmin);

    $response = $this->get(route('assets.export'));

    $response->assertOk()
        ->assertDownload('assets-general-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('"Nama Perangkat",Kategori,QTY,Alias')
        ->toContain('Serial Number')
        ->toContain('Keterangan');
});

test('the csv export includes the generated keterangan', function () {
    $superadmin = superadminUser();
    $ups = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $frame = Category::create(['parent_id' => $ups->id, 'name' => 'UPS Frame']);

    Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS Export',
    ]);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', ['sheet' => 'ups-batt']))
        ->streamedContent();

    expect($csv)->toContain("UPS Export\nUPS\nUPS Frame");
});

test('the csv export lists every asset in order', function () {
    $superadmin = superadminUser();
    Asset::factory()->create(['name' => 'AAA Zzz']);
    Asset::factory()->create(['name' => 'BBB Zzz']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export'))->streamedContent();

    $positionA = strpos($csv, '"AAA Zzz"');
    $positionB = strpos($csv, '"BBB Zzz"');

    expect($positionA)->not->toBeFalse()
        ->and($positionB)->not->toBeFalse()
        ->and($positionB)->toBeGreaterThan($positionA);
});

test('the export honors the search filter', function () {
    $superadmin = superadminUser();
    Asset::factory()->create(['name' => 'UPS Utama', 'serial_number' => 'SN-AAA-111']);
    Asset::factory()->create([
        'name' => 'Kamera Lobby',
        'serial_number' => 'SN-BBB-222',
        'room' => 'Data Hall A',
    ]);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', ['search' => 'UPS']))
        ->streamedContent();

    expect($csv)->toContain('UPS Utama')
        ->not->toContain('Kamera Lobby');
});

test('searching by a specific column does not match other columns', function () {
    $superadmin = superadminUser();
    Asset::factory()->create(['name' => 'UPS Utama', 'brand' => 'ABB']);
    Asset::factory()->create(['name' => 'Pompa Air', 'brand' => 'UPS Tech']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', [
        'search' => 'UPS',
        'search_column' => 'brand',
    ]))->streamedContent();

    expect($csv)->toContain('UPS Tech')
        ->not->toContain('UPS Utama');
});

test('searching by category matches assets under descendant categories', function () {
    $superadmin = superadminUser();
    $root = Category::create(['parent_id' => null, 'name' => 'UPS']);
    $child = Category::create(['parent_id' => $root->id, 'name' => 'Rack Mount']);
    Asset::factory()->create(['category_id' => $child->id, 'name' => 'Asset Nested']);
    Asset::factory()->create(['name' => 'Asset Other']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', [
        'search' => 'UPS',
        'search_column' => 'category',
    ]))->streamedContent();

    expect($csv)->toContain('Asset Nested')
        ->not->toContain('Asset Other');
});

test('an unknown search column falls back to searching all columns', function () {
    $superadmin = superadminUser();
    Asset::factory()->create(['name' => 'UPS Utama', 'serial_number' => 'SN-AAA-111']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.export', [
        'search' => 'UPS',
        'search_column' => 'DROP TABLE',
    ]))->streamedContent();

    expect($csv)->toContain('UPS Utama');
});

test('searching by description matches the name or any category in the path', function () {
    $superadmin = superadminUser();
    $root = Category::create(['parent_id' => null, 'name' => 'UPS Frame']);
    $child = Category::create(['parent_id' => $root->id, 'name' => 'Modul 1']);
    Asset::factory()->create(['category_id' => $child->id, 'name' => 'UPS-A', 'brand' => 'DeskripsiBrand']);
    Asset::factory()->create(['name' => 'PC NOC', 'brand' => 'ACER']);

    $this->actingAs($superadmin);

    $byCategoryPath = $this->get(route('assets.export', [
        'search' => 'Modul 1',
        'search_column' => 'description',
    ]))->streamedContent();

    expect($byCategoryPath)->toContain('UPS-A')
        ->not->toContain('PC NOC');

    $byName = $this->get(route('assets.export', [
        'search' => 'NOC',
        'search_column' => 'description',
    ]))->streamedContent();

    expect($byName)->toContain('PC NOC');

    $byBrand = $this->get(route('assets.export', [
        'search' => 'DeskripsiBrand',
        'search_column' => 'description',
    ]))->streamedContent();

    expect($byBrand)->not->toContain('UPS-A');
});
