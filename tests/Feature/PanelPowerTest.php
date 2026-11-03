<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Menu;
use App\Models\PanelMcb;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

function panelPowerCategory(): Category
{
    return Category::firstOrCreate(['name' => 'Panel Power', 'parent_id' => null]);
}

function panelAsset(array $overrides = []): Asset
{
    return Asset::factory()->create(array_merge([
        'category_id' => panelPowerCategory()->id,
        'name' => 'PDU-A1',
    ], $overrides));
}

test('guests are redirected from the panel power list', function () {
    $this->get(route('assets.panelpowerlist'))->assertRedirect(route('login'));
});

test('a user without asset permissions cannot view the panel power list', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.panelpowerlist'))->assertForbidden();
});

test('a user with only the view permission sees the panel power list read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.panelpowerlist'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('the panel power list shows panel power assets with their mcbs', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset(['name' => 'PDU-A1', 'floor' => '1', 'room' => 'NER']);
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-10']);
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-2']);

    Asset::factory()->create([
        'category_id' => Category::firstOrCreate(['name' => 'Server', 'parent_id' => null])->id,
        'name' => 'Dell PowerEdge',
    ]);

    $this->actingAs($superadmin);

    $this->get(route('assets.panelpowerlist'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/panelpowerlist', false)
            ->has('panels', 1)
            ->where('panels.0.name', 'PDU-A1')
            ->where('panels.0.floor', '1')
            ->where('panels.0.panel_mcbs.0.mcb_number', 'MCB-2')
            ->where('panels.0.panel_mcbs.1.mcb_number', 'MCB-10')
        );
});

test('panels in a panel power sub-category are listed too', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $child = Category::create([
        'parent_id' => panelPowerCategory()->id,
        'name' => 'Panel Power LT2',
    ]);
    Asset::factory()->create(['category_id' => $child->id, 'name' => 'PDU-B1']);

    $this->actingAs($superadmin);

    $this->get(route('assets.panelpowerlist'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('panels', 1)
            ->where('panels.0.name', 'PDU-B1')
        );
});

test('adding an mcb to a panel stores it', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.store', $panel), ['mcb_number' => 'MCB-01'])
        ->assertRedirect(route('assets.panelpowerlist'));

    $this->assertDatabaseHas('panel_mcbs', [
        'asset_id' => $panel->id,
        'mcb_number' => 'MCB-01',
    ]);
});

test('duplicate mcb numbers in the same panel are rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.store', $panel), ['mcb_number' => 'MCB-01'])
        ->assertSessionHasErrors('mcb_number');

    $this->assertDatabaseCount('panel_mcbs', 1);
});

test('the same mcb number is allowed on a different panel', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    $otherPanel = panelAsset(['name' => 'PDU-B1']);
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.store', $otherPanel), ['mcb_number' => 'MCB-01'])
        ->assertRedirect(route('assets.panelpowerlist'));

    $this->assertDatabaseCount('panel_mcbs', 2);
});

test('an mcb number is required', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.store', $panel), ['mcb_number' => ''])
        ->assertSessionHasErrors('mcb_number');
});

test('updating an mcb renames it', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    $mcb = PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);

    $this->actingAs($superadmin);

    $this->patch(route('panel-mcbs.update', [$panel, $mcb]), ['mcb_number' => 'MCB-99'])
        ->assertRedirect(route('assets.panelpowerlist'));

    $this->assertDatabaseHas('panel_mcbs', [
        'id' => $mcb->id,
        'mcb_number' => 'MCB-99',
    ]);
});

test('updating an mcb to a number already used in the panel is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);
    $mcb = PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-02']);

    $this->actingAs($superadmin);

    $this->patch(route('panel-mcbs.update', [$panel, $mcb]), ['mcb_number' => 'MCB-01'])
        ->assertSessionHasErrors('mcb_number');
});

test('deleting an mcb removes it', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    $mcb = PanelMcb::factory()->create(['asset_id' => $panel->id]);

    $this->actingAs($superadmin);

    $this->delete(route('panel-mcbs.destroy', [$panel, $mcb]))
        ->assertRedirect(route('assets.panelpowerlist'));

    $this->assertDatabaseCount('panel_mcbs', 0);
});

test('mcb routes are scoped to their panel asset', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    $otherPanel = panelAsset(['name' => 'PDU-B1']);
    $mcb = PanelMcb::factory()->create(['asset_id' => $panel->id]);

    $this->actingAs($superadmin);

    $this->patch(route('panel-mcbs.update', [$otherPanel, $mcb]), ['mcb_number' => 'MCB-99'])
        ->assertNotFound();

    $this->delete(route('panel-mcbs.destroy', [$otherPanel, $mcb]))->assertNotFound();
});

test('managing mcbs on a non-panel asset is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create([
        'category_id' => Category::firstOrCreate(['name' => 'Server', 'parent_id' => null])->id,
    ]);

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.store', $asset), ['mcb_number' => 'MCB-01'])
        ->assertNotFound();
});

test('a user without the manage permission cannot add mcbs', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $panel = panelAsset();

    $this->actingAs($user);

    $this->post(route('panel-mcbs.store', $panel), ['mcb_number' => 'MCB-01'])
        ->assertForbidden();
});

test('the panel power list serves menu props for breadcrumbs', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');

    $assetsMenu = Menu::create([
        'name' => 'Asset Management',
        'group' => 'Main Menu',
        'url' => '/assets',
        'icon' => 'FileBox',
        'order' => 3,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Panel Power List',
        'group' => 'Main Menu',
        'url' => '/assets/panelpowerlist',
        'parent_id' => $assetsMenu->id,
        'order' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get(route('assets.panelpowerlist'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('menu.url', '/assets')
            ->where('activeChild.url', '/assets/panelpowerlist')
        );
});

test('adding many mcbs at once stores sequential numbers', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 3])
        ->assertRedirect(route('assets.panelpowerlist'));

    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);
    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $panel->id, 'mcb_number' => 'MCB-02']);
    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $panel->id, 'mcb_number' => 'MCB-03']);
    $this->assertDatabaseCount('panel_mcbs', 3);
});

test('batch numbering continues from the highest existing number and padding', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-02']);

    $widePadPanel = panelAsset(['name' => 'PDU-B1']);
    PanelMcb::factory()->create(['asset_id' => $widePadPanel->id, 'mcb_number' => 'MCB-009']);

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 2])
        ->assertRedirect(route('assets.panelpowerlist'));

    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $panel->id, 'mcb_number' => 'MCB-03']);
    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $panel->id, 'mcb_number' => 'MCB-04']);

    $this->post(route('panel-mcbs.batch', $widePadPanel), ['count' => 1]);

    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $widePadPanel->id, 'mcb_number' => 'MCB-010']);
});

test('batch numbering ignores existing numbers that do not end in digits', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'MCB-01']);
    PanelMcb::factory()->create(['asset_id' => $panel->id, 'mcb_number' => 'spare']);

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 1]);

    $this->assertDatabaseHas('panel_mcbs', ['asset_id' => $panel->id, 'mcb_number' => 'MCB-02']);
    $this->assertDatabaseCount('panel_mcbs', 3);
});

test('batch requires a valid count', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $panel = panelAsset();

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 0])
        ->assertSessionHasErrors('count');

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 51])
        ->assertSessionHasErrors('count');

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 'banyak'])
        ->assertSessionHasErrors('count');

    $this->assertDatabaseCount('panel_mcbs', 0);
});

test('batch on a non-panel asset is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create([
        'category_id' => Category::firstOrCreate(['name' => 'Server', 'parent_id' => null])->id,
    ]);

    $this->actingAs($superadmin);

    $this->post(route('panel-mcbs.batch', $asset), ['count' => 3])->assertNotFound();
});

test('a user without the manage permission cannot batch add mcbs', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $panel = panelAsset();

    $this->actingAs($user);

    $this->post(route('panel-mcbs.batch', $panel), ['count' => 3])->assertForbidden();
});
