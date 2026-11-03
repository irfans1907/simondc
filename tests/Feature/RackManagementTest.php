<?php

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\RackTypeSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view rack management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage rack management', 'guard_name' => 'web']);

    $this->seed(RackTypeSeeder::class);
});

function rackPayload(array $overrides = []): array
{
    return array_merge([
        'rack_code' => 'RACK-1000',
        'floor' => '1',
        'room' => 'NOC',
        'brand' => 'APC',
        'type' => 'U Rack',
        'capacity' => '1U',
        'dimension' => '600x1000x2000mm',
        'pdu_outlet_1' => 'Ada',
        'pdu_outlet_2' => 'Tidak Ada',
        'manufacture_year' => '2024',
        'operation_year' => '2025',
        'notes' => 'Catatan rak',
    ], $overrides);
}

test('guests are redirected from the rack index', function () {
    $this->get(route('rack.index'))->assertRedirect(route('login'));
});

test('the rack module root redirects to the overview page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view rack management');

    $this->actingAs($user);

    $this->get(route('rack.index'))->assertRedirect(route('rack.overview'));
});

test('a user with only the view permission can view the rack index read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view rack management');

    $this->actingAs($user);

    $this->get(route('rack.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('a user without view or manage permission cannot view the rack index', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('rack.index'))->assertForbidden();
});

test('the rack index page lists racks', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0001', 'type' => 'Sub Rack']);

    $this->actingAs($superadmin);

    $this->get(route('rack.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/rack/overview', false)
            ->has('racks.data', 1)
            ->where('racks.data.0.rack_code', 'RACK-0001')
            ->where('filters.search_column', 'all')
        );
});

test('creating a rack requires a manually entered rack code', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload(['rack_code' => '']))
        ->assertSessionHasErrors('rack_code');

    $this->assertDatabaseCount('racks', 0);
});

test('creating a rack stores the manually entered rack code', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload(['rack_code' => 'CUSTOM-01']))
        ->assertRedirect(route('rack.index'));

    $this->assertDatabaseHas('racks', [
        'rack_code' => 'CUSTOM-01',
        'room' => 'NOC',
        'status' => 'Ready For Sale',
    ]);
});

test('creating a rack derives its capacity from the selected type', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload([
        'type' => 'Full Rack Server',
        'capacity' => '999U',
    ]))->assertRedirect(route('rack.index'));

    $this->assertDatabaseHas('racks', [
        'type' => 'Full Rack Server',
        'capacity' => '42U',
    ]);
});

test('creating a rack requires every mandatory field', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload([
        'floor' => '',
        'dimension' => '',
        'pdu_outlet_1' => '',
    ]))->assertSessionHasErrors(['floor', 'dimension', 'pdu_outlet_1']);
});

test('the rack type must be one of the allowed values', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload(['type' => 'Rack Custom']))
        ->assertSessionHasErrors('type');
});

test('the pdu outlet must be ada or tidak ada', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload(['pdu_outlet_2' => 'Maybe']))
        ->assertSessionHasErrors('pdu_outlet_2');
});

test('the rack code must be unique', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0001']);

    $this->actingAs($superadmin);

    $this->post(route('rack.store'), rackPayload(['rack_code' => 'RACK-0001']))
        ->assertSessionHasErrors('rack_code');
});

test('updating a rack with an empty rack code is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0007', 'room' => 'NOC']);

    $this->actingAs($superadmin);

    $this->patch(route('rack.update', $rack), rackPayload([
        'rack_code' => '',
        'room' => 'Data Hall A',
    ]))->assertSessionHasErrors('rack_code');

    $this->assertDatabaseHas('racks', [
        'id' => $rack->id,
        'rack_code' => 'RACK-0007',
        'room' => 'NOC',
    ]);
});

test('updating a rack derives its capacity from the new type', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create(['type' => 'U Rack', 'capacity' => '1U']);

    $this->actingAs($superadmin);

    $this->patch(route('rack.update', $rack), rackPayload([
        'type' => 'Full Rack Network',
    ]))->assertRedirect(route('rack.index'));

    $this->assertDatabaseHas('racks', [
        'id' => $rack->id,
        'type' => 'Full Rack Network',
        'capacity' => '45U',
    ]);
});

test('a rack can be deleted', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->delete(route('rack.destroy', $rack))
        ->assertRedirect(route('rack.index'));

    $this->assertDatabaseMissing('racks', ['id' => $rack->id]);
});

test('guests are redirected from the rack export', function () {
    $this->get(route('rack.export'))->assertRedirect(route('login'));
});

test('a user without the manage permission cannot export racks', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('rack.export'))->assertForbidden();
});

test('the export returns a numbered csv download of all racks', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0001']);
    Rack::factory()->create(['rack_code' => 'RACK-0002']);

    $this->actingAs($superadmin);

    $response = $this->get(route('rack.export'));

    $response->assertOk()
        ->assertDownload('racks-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('No,"Rack ID",Lantai,Ruang,Status')
        ->toContain("\n1,RACK-0001")
        ->toContain("\n2,RACK-0002");
});

test('the export honors the search filter', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0001', 'type' => 'Sub Rack']);
    Rack::factory()->create(['rack_code' => 'RACK-0002', 'type' => 'U Rack']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('rack.export', [
        'search' => 'Sub Rack',
        'search_column' => 'type',
    ]))->streamedContent();

    expect($csv)->toContain('RACK-0001')
        ->not->toContain('RACK-0002');
});

test('the rack export includes the assigned customer name', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0001']);
    Customer::factory()->create(['rack_id' => $rack->id, 'customer_name' => 'PT Maju Jaya']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('rack.export'))->streamedContent();

    expect($csv)
        ->toContain('Status,"Nama Customer",Merek')
        ->toContain('PT Maju Jaya');
});

test('an unknown rack search column falls back to searching all columns', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0001', 'room' => 'NOC']);

    $this->actingAs($superadmin);

    $this->get(route('rack.overview', ['search' => 'NOC', 'search_column' => 'DROP TABLE']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search_column', 'all')
            ->has('racks.data', 1)
        );
});

test('the overview child page renders the rack list at /rack/overview', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0001', 'type' => 'Sub Rack']);

    $rackMenu = Menu::create([
        'name' => 'Rack Management',
        'group' => 'Main Menu',
        'url' => '/rack',
        'icon' => 'Server',
        'order' => 4,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Overview',
        'group' => 'Main Menu',
        'url' => '/rack/overview',
        'parent_id' => $rackMenu->id,
        'order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get('/rack/overview')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/rack/overview', false)
            ->has('racks.data', 1)
            ->where('racks.data.0.rack_code', 'RACK-0001')
            ->has('menu')
            ->has('activeChild')
            ->where('menu.url', '/rack')
            ->where('activeChild.url', '/rack/overview')
            ->has('menus')
        );
});

test('the racklist page lists rack types with menu props', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');
    Rack::factory()->create(['rack_code' => 'RACK-0002', 'type' => 'U Rack']);

    $rackMenu = Menu::create([
        'name' => 'Rack Management',
        'group' => 'Main Menu',
        'url' => '/rack',
        'icon' => 'Server',
        'order' => 4,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Rack List',
        'group' => 'Main Menu',
        'url' => '/rack/racklist',
        'parent_id' => $rackMenu->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get('/rack/racklist')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/rack/racklist', false)
            ->has('rackTypes.data')
            ->where('rackTypes.data.0.name', 'Full Rack Network')
            ->where('filters.search_column', 'all')
            ->has('menu')
            ->has('activeChild')
            ->where('menu.url', '/rack')
            ->where('activeChild.url', '/rack/racklist')
            ->has('menus')
        );
});

test('a child menu with a generated page renders its own dynamic component', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');

    $parent = Menu::create([
        'name' => 'Rack Management',
        'group' => 'Main Menu',
        'url' => '/rack',
        'icon' => 'Server',
        'order' => 4,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Rack List',
        'group' => 'Main Menu',
        'url' => '/rack/racklist',
        'parent_id' => $parent->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get('/rack/racklist')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dynamic/rack/racklist', false));
});

test('a child menu without its own page redirects to the module index', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');

    $parent = Menu::create([
        'name' => 'Rack Management',
        'group' => 'Main Menu',
        'url' => '/rack',
        'icon' => 'Server',
        'order' => 4,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Ghost',
        'group' => 'Main Menu',
        'url' => '/rack/ghost',
        'parent_id' => $parent->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get('/rack/ghost')->assertRedirect(route('rack.index'));
});

test('an uppercase child url reaches the child page route instead of a 405', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');

    $parent = Menu::create([
        'name' => 'Rack Management',
        'group' => 'Main Menu',
        'url' => '/rack',
        'icon' => 'Server',
        'order' => 4,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Upper Test',
        'group' => 'Main Menu',
        'url' => '/rack/UpperTest',
        'parent_id' => $parent->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get('/rack/UpperTest')->assertRedirect(route('rack.index'));
});
