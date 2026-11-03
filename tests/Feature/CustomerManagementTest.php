<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\PanelMcb;
use App\Models\Rack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view customer list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);
});

function customerPayload(Rack $rack, array $overrides = []): array
{
    return array_merge([
        'rack_id' => $rack->id,
        'city' => 'Jakarta',
        'location' => 'neuCentrIX Cikarang',
        'customer_name' => 'PT Contoh Nusantara',
        'service_type' => 'Colocation',
        'sales_channel' => 'Direct',
        'business_status' => 'Berbayar',
        'service_status' => 'Masih Berjalan',
        'order_number' => 'ORD-10001',
        'sid_rack' => 'SID-10001',
        'activation_date' => '2026-01-15',
        'power_source' => 'Single Source',
        'connectivity' => 'Fiber',
        'ip_address' => '10.10.1.5',
        'port' => '24',
        'vlan' => '100',
        'service_vlan' => '200',
        'account_manager' => 'Budi Santoso',
        'notes' => 'Catatan pelanggan',
    ], $overrides);
}

/**
 * A Panel Power category, one panel asset, and two MCB slots on it.
 *
 * @return array{0: Asset, 1: PanelMcb, 2: PanelMcb}
 */
function customerPanelFixture(): array
{
    $panel = Category::firstOrCreate(['name' => 'Panel Power', 'parent_id' => null]);
    $asset = Asset::factory()->create(['category_id' => $panel->id, 'name' => 'PDU-A']);
    $mcbA = PanelMcb::factory()->create(['asset_id' => $asset->id, 'mcb_number' => 'MCB-01']);
    $mcbB = PanelMcb::factory()->create(['asset_id' => $asset->id, 'mcb_number' => 'MCB-02']);

    return [$asset, $mcbA, $mcbB];
}

test('guests are redirected from the customer index', function () {
    $this->get(route('customer.index'))->assertRedirect(route('login'));
});

test('the customer module root redirects to the overview page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view customer list');

    $this->actingAs($user);

    $this->get(route('customer.index'))->assertRedirect(route('customer.overview'));
});

test('a user with only the view permission can view the customer overview read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view customer list');

    $this->actingAs($user);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('a user without view or manage permission cannot view the customer overview', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('customer.overview'))->assertForbidden();
});

test('the customer overview page lists customers with their rack details', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0001', 'type' => 'Sub Rack']);
    Customer::factory()->create([
        'rack_id' => $rack->id,
        'customer_name' => 'PT Maju Jaya',
        'city' => 'Bandung',
    ]);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/customer/overview', false)
            ->has('customers.data', 1)
            ->where('customers.data.0.customer_name', 'PT Maju Jaya')
            ->where('customers.data.0.rack.rack_code', 'RACK-0001')
            ->where('customers.data.0.rack.type', 'Sub Rack')
            ->where('filters.search_column', 'all')
        );
});

test('creating a customer requires the mandatory fields', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'rack_id' => '',
        'city' => '',
        'location' => '',
        'customer_name' => '',
    ]))->assertSessionHasErrors(['rack_id', 'city', 'location', 'customer_name']);

    $this->assertDatabaseCount('customers', 0);
});

test('creating a customer stores the record', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0002']);

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'activation_date' => '2026-03-01',
    ]))->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('customers', [
        'rack_id' => $rack->id,
        'customer_name' => 'PT Contoh Nusantara',
        'city' => 'Jakarta',
        'activation_date' => '2026-03-01',
    ]);
});

test('a rack can only be assigned to a single customer', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();
    Customer::factory()->create(['rack_id' => $rack->id]);

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'customer_name' => 'PT Pelanggan Kedua',
    ]))->assertSessionHasErrors('rack_id');

    $this->assertDatabaseCount('customers', 1);
});

test('the rack dropdown excludes racks already assigned to a customer', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $taken = Rack::factory()->create(['rack_code' => 'RACK-0001']);
    $free = Rack::factory()->create(['rack_code' => 'RACK-0002']);
    Customer::factory()->create(['rack_id' => $taken->id]);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableRacks', 1)
            ->where('availableRacks.0.id', $free->id)
            ->where('availableRacks.0.rack_code', 'RACK-0002')
        );
});

test('the status and power source choices must come from the allowed lists', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'business_status' => 'Gratis',
        'service_status' => 'Aktif',
        'power_source' => 'Triple Source',
        'city' => 'Denpasar',
        'location' => 'neuCentrIX Bogor',
    ]))->assertSessionHasErrors([
        'business_status',
        'service_status',
        'power_source',
        'city',
        'location',
    ]);
});

test('the activation date must be a valid date', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'activation_date' => 'bukan-tanggal',
    ]))->assertSessionHasErrors('activation_date');
});

test('updating a customer can keep its own rack', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();
    $customer = Customer::factory()->create(['rack_id' => $rack->id]);

    $this->actingAs($superadmin);

    $this->patch(route('customer.update', $customer), customerPayload($rack, [
        'customer_name' => 'PT Nama Baru',
    ]))->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'rack_id' => $rack->id,
        'customer_name' => 'PT Nama Baru',
    ]);
});

test('updating a customer can move it to another free rack', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $oldRack = Rack::factory()->create();
    $newRack = Rack::factory()->create();
    $customer = Customer::factory()->create(['rack_id' => $oldRack->id]);

    $this->actingAs($superadmin);

    $this->patch(route('customer.update', $customer), customerPayload($newRack))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'rack_id' => $newRack->id,
    ]);
});

test('updating a customer to a rack held by another customer is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $occupied = Rack::factory()->create();
    $ownRack = Rack::factory()->create();
    Customer::factory()->create(['rack_id' => $occupied->id]);
    $customer = Customer::factory()->create(['rack_id' => $ownRack->id]);

    $this->actingAs($superadmin);

    $this->patch(route('customer.update', $customer), customerPayload($occupied))
        ->assertSessionHasErrors('rack_id');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'rack_id' => $ownRack->id,
    ]);
});

test('deleting a customer frees its rack for another customer', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0007']);
    $customer = Customer::factory()->create(['rack_id' => $rack->id]);

    $this->actingAs($superadmin);

    $this->delete(route('customer.destroy', $customer))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseCount('customers', 0);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableRacks', 1)
            ->where('availableRacks.0.rack_code', 'RACK-0007')
        );
});

test('creating a customer marks its rack as occupied', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0008']);

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('racks', [
        'id' => $rack->id,
        'status' => Rack::OCCUPIED_STATUS,
    ]);
});

test('moving a customer to another rack frees the old rack and occupies the new one', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $oldRack = Rack::factory()->create();
    $newRack = Rack::factory()->create();
    $customer = Customer::factory()->create(['rack_id' => $oldRack->id]);

    Rack::whereKey($oldRack->id)->update(['status' => Rack::OCCUPIED_STATUS]);

    $this->actingAs($superadmin);

    $this->patch(route('customer.update', $customer), customerPayload($newRack))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('racks', [
        'id' => $oldRack->id,
        'status' => Rack::DEFAULT_STATUS,
    ]);
    $this->assertDatabaseHas('racks', [
        'id' => $newRack->id,
        'status' => Rack::OCCUPIED_STATUS,
    ]);
});

test('deleting a customer returns its rack to ready for sale', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();
    $customer = Customer::factory()->create(['rack_id' => $rack->id]);

    Rack::whereKey($rack->id)->update(['status' => Rack::OCCUPIED_STATUS]);

    $this->actingAs($superadmin);

    $this->delete(route('customer.destroy', $customer))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('racks', [
        'id' => $rack->id,
        'status' => Rack::DEFAULT_STATUS,
    ]);
});

test('the rack overview shows the assigned customer name beside the status', function () {
    Permission::firstOrCreate(['name' => 'view rack management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage rack management', 'guard_name' => 'web']);
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');
    $superadmin->givePermissionTo('manage rack management');
    $taken = Rack::factory()->create(['rack_code' => 'RACK-0001']);
    $free = Rack::factory()->create(['rack_code' => 'RACK-0002']);
    Customer::factory()->create(['rack_id' => $taken->id, 'customer_name' => 'PT Maju Jaya']);

    $this->actingAs($superadmin);

    $this->get(route('rack.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('racks.data', 2)
            ->where('racks.data.0.rack_code', 'RACK-0001')
            ->where('racks.data.0.customers.0.customer_name', 'PT Maju Jaya')
            ->has('racks.data.1.customers', 0)
        );
});

test('searching racks by customer name matches the related customer', function () {
    Permission::firstOrCreate(['name' => 'view rack management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage rack management', 'guard_name' => 'web']);
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-0001']);
    $other = Rack::factory()->create(['rack_code' => 'RACK-0002']);
    Customer::factory()->create(['rack_id' => $rack->id, 'customer_name' => 'PT Cari Rack']);
    Customer::factory()->create(['rack_id' => $other->id, 'customer_name' => 'PT Lain']);

    $this->actingAs($superadmin);

    $this->get(route('rack.overview', [
        'search' => 'Cari Rack',
        'search_column' => 'customer_name',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('racks.data', 1)
            ->where('racks.data.0.rack_code', 'RACK-0001')
            ->where('filters.search_column', 'customer_name')
        );

    $this->get(route('rack.overview', [
        'search' => 'PT Lain',
        'search_column' => 'all',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('racks.data', 1)
            ->where('racks.data.0.rack_code', 'RACK-0002')
        );
});

test('a rack assigned to a customer cannot be deleted', function () {
    Permission::firstOrCreate(['name' => 'manage rack management', 'guard_name' => 'web']);
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create();
    Customer::factory()->create(['rack_id' => $rack->id]);

    $this->actingAs($superadmin);

    $this->delete(route('rack.destroy', $rack))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('racks', ['id' => $rack->id]);
});

test('a user without the manage permission cannot create a customer', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view customer list');
    $rack = Rack::factory()->create();

    $this->actingAs($user);

    $this->post(route('customer.store'), customerPayload($rack))->assertForbidden();
});

test('searching customers by rack code matches the related rack', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create(['rack_code' => 'RACK-9001']);
    $otherRack = Rack::factory()->create(['rack_code' => 'RACK-9002']);
    Customer::factory()->create(['rack_id' => $rack->id, 'customer_name' => 'PT Cari Rack']);
    Customer::factory()->create(['rack_id' => $otherRack->id, 'customer_name' => 'PT Lain']);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview', ['search' => 'RACK-9001', 'search_column' => 'rack_code']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.customer_name', 'PT Cari Rack')
        );
});

test('an unknown customer search column falls back to searching all columns', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rack = Rack::factory()->create();
    Customer::factory()->create(['rack_id' => $rack->id, 'customer_name' => 'PT Fallback']);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview', ['search' => 'Fallback', 'search_column' => 'hacked']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('filters.search_column', 'all')
        );
});

test('switch options list device names from the switch category only', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');

    $network = Category::create(['name' => 'Network']);
    $switch = Category::create(['parent_id' => $network->id, 'name' => 'Switch']);
    $managed = Category::create(['parent_id' => $switch->id, 'name' => 'Managed']);
    $server = Category::create(['name' => 'Server']);

    Asset::factory()->create(['category_id' => $switch->id, 'name' => 'Cisco', 'type' => 'Switch']);
    Asset::factory()->create(['category_id' => $managed->id, 'name' => 'CRS3xx', 'type' => 'Switch']);
    Asset::factory()->create(['category_id' => $network->id, 'name' => 'Cisco IE', 'type' => 'Switch']);
    Asset::factory()->create(['category_id' => $server->id, 'name' => 'Dell PowerEdge', 'type' => 'Rack Mount']);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('switchOptions', ['CRS3xx', 'Cisco'])
        );
});

test('the customer overview page serves menu props for breadcrumbs', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');

    $customerMenu = Menu::create([
        'name' => 'Customer List',
        'group' => 'Main Menu',
        'url' => '/customer',
        'icon' => 'FileUser',
        'order' => 5,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Overview',
        'group' => 'Main Menu',
        'url' => '/customer/overview',
        'parent_id' => $customerMenu->id,
        'order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('menu.url', '/customer')
            ->where('activeChild.url', '/customer/overview')
        );
});

test('the panel dropdown lists panel power assets with their mcb availability', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$asset, $mcbA, $mcbB] = customerPanelFixture();
    Asset::factory()->create([
        'category_id' => Category::firstOrCreate(['name' => 'Server', 'parent_id' => null])->id,
        'name' => 'Dell PowerEdge',
    ]);
    $rack = Rack::factory()->create();
    $customer = Customer::factory()->create([
        'rack_id' => $rack->id,
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('panelOptions', 1)
            ->where('panelOptions.0.id', $asset->id)
            ->where('panelOptions.0.name', 'PDU-A')
            ->has('panelOptions.0.mcbs', 2)
            ->where('panelOptions.0.mcbs.0.id', $mcbA->id)
            ->where('panelOptions.0.mcbs.0.taken_by', $customer->id)
            ->where('panelOptions.0.mcbs.1.id', $mcbB->id)
            ->where('panelOptions.0.mcbs.1.taken_by', null)
        );
});

test('creating a customer links the selected panel and mcb', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$asset, $mcbA] = customerPanelFixture();
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]))->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('customers', [
        'rack_id' => $rack->id,
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]);
});

test('an mcb must belong to the selected panel', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$assetA] = customerPanelFixture();
    $panelCat = Category::firstOrCreate(['name' => 'Panel Power', 'parent_id' => null]);
    $assetB = Asset::factory()->create(['category_id' => $panelCat->id, 'name' => 'PDU-B']);
    $foreignMcb = PanelMcb::factory()->create(['asset_id' => $assetB->id]);
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'panel_asset_id' => $assetA->id,
        'panel_mcb_id' => $foreignMcb->id,
    ]))->assertSessionHasErrors('panel_mcb_id');

    $this->assertDatabaseCount('customers', 0);
});

test('a panel outside the panel power category is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $server = Category::firstOrCreate(['name' => 'Server', 'parent_id' => null]);
    $asset = Asset::factory()->create(['category_id' => $server->id]);
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'panel_asset_id' => $asset->id,
    ]))->assertSessionHasErrors('panel_asset_id');
});

test('an mcb can only feed one customer', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$asset, $mcbA] = customerPanelFixture();
    $rack = Rack::factory()->create();
    Customer::factory()->create([
        'rack_id' => $rack->id,
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]);
    $otherRack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($otherRack, [
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]))->assertSessionHasErrors('panel_mcb_id');

    $this->assertDatabaseCount('customers', 1);
});

test('a customer can keep its own mcb when updated', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$asset, $mcbA] = customerPanelFixture();
    $rack = Rack::factory()->create();
    $customer = Customer::factory()->create([
        'rack_id' => $rack->id,
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]);

    $this->actingAs($superadmin);

    $this->patch(route('customer.update', $customer), customerPayload($rack, [
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
        'customer_name' => 'PT Nama Baru',
    ]))->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'panel_mcb_id' => $mcbA->id,
        'customer_name' => 'PT Nama Baru',
    ]);
});

test('dual source requires the second pair and single source forbids it', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$asset, $mcbA, $mcbB] = customerPanelFixture();

    $this->actingAs($superadmin);

    $dualRack = Rack::factory()->create();
    $this->post(route('customer.store'), customerPayload($dualRack, [
        'power_source' => 'Dual Source',
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]))->assertSessionHasErrors(['panel_asset_id_2', 'panel_mcb_id_2']);

    $singleRack = Rack::factory()->create();
    $this->post(route('customer.store'), customerPayload($singleRack, [
        'power_source' => 'Single Source',
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
        'panel_asset_id_2' => $asset->id,
        'panel_mcb_id_2' => $mcbB->id,
    ]))->assertSessionHasErrors(['panel_asset_id_2', 'panel_mcb_id_2']);

    $this->assertDatabaseCount('customers', 0);
});

test('dual source customers store both pairs and cannot reuse one mcb', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    [$asset, $mcbA, $mcbB] = customerPanelFixture();
    $rack = Rack::factory()->create();

    $this->actingAs($superadmin);

    $this->post(route('customer.store'), customerPayload($rack, [
        'power_source' => 'Dual Source',
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
        'panel_asset_id_2' => $asset->id,
        'panel_mcb_id_2' => $mcbB->id,
    ]))->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('customers', [
        'rack_id' => $rack->id,
        'panel_mcb_id' => $mcbA->id,
        'panel_mcb_id_2' => $mcbB->id,
    ]);

    $reuseRack = Rack::factory()->create();
    $this->post(route('customer.store'), customerPayload($reuseRack, [
        'power_source' => 'Dual Source',
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
        'panel_asset_id_2' => $asset->id,
        'panel_mcb_id_2' => $mcbA->id,
    ]))->assertSessionHasErrors('panel_mcb_id_2');
});

test('a panel asset assigned to a customer cannot be deleted', function () {
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
    $superadmin = superadminUser();
    $superadmin->givePermissionTo(['manage customer list', 'manage asset management']);
    [$asset] = customerPanelFixture();
    $rack = Rack::factory()->create();
    Customer::factory()->create(['rack_id' => $rack->id, 'panel_asset_id' => $asset->id]);

    $this->actingAs($superadmin);

    $this->delete(route('assets.destroy', $asset))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('assets', ['id' => $asset->id]);
});

test('an mcb assigned to a customer cannot be deleted', function () {
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
    $superadmin = superadminUser();
    $superadmin->givePermissionTo(['manage customer list', 'manage asset management']);
    [$asset, $mcbA] = customerPanelFixture();
    $rack = Rack::factory()->create();
    Customer::factory()->create([
        'rack_id' => $rack->id,
        'panel_asset_id' => $asset->id,
        'panel_mcb_id' => $mcbA->id,
    ]);

    $this->actingAs($superadmin);

    $this->delete(route('panel-mcbs.destroy', [$asset, $mcbA]))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('panel_mcbs', ['id' => $mcbA->id]);
});

test('rack outlets are exposed for the power source correlation', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $takenRack = Rack::factory()->create([
        'rack_code' => 'RACK-7001',
        'pdu_outlet_1' => 'Ada',
        'pdu_outlet_2' => 'Tidak Ada',
    ]);
    $freeRack = Rack::factory()->create([
        'rack_code' => 'RACK-7002',
        'pdu_outlet_1' => 'Ada',
        'pdu_outlet_2' => 'Ada',
    ]);
    Customer::factory()->create([
        'rack_id' => $takenRack->id,
        'power_source' => 'Dual Source',
    ]);

    $this->actingAs($superadmin);

    $this->get(route('customer.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableRacks', 1)
            ->where('availableRacks.0.rack_code', 'RACK-7002')
            ->where('availableRacks.0.pdu_outlet_1', 'Ada')
            ->where('availableRacks.0.pdu_outlet_2', 'Ada')
            ->where('customers.data.0.rack.pdu_outlet_1', 'Ada')
            ->where('customers.data.0.rack.pdu_outlet_2', 'Tidak Ada')
            ->where('customers.data.0.power_source', 'Dual Source')
        );
});

test('guests are redirected from the customer export', function () {
    $this->get(route('customer.export'))->assertRedirect(route('login'));
});

test('a view-only user cannot export customers', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view customer list');

    $this->actingAs($user);

    $this->get(route('customer.export'))->assertForbidden();
});

test('the customer export returns a csv download honoring the search filter', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $rackA = Rack::factory()->create(['rack_code' => 'RACK-7101']);
    $rackB = Rack::factory()->create(['rack_code' => 'RACK-7102']);
    Customer::factory()->create(customerPayload($rackA, ['customer_name' => 'PT Export Contoh']));
    Customer::factory()->create(customerPayload($rackB, ['customer_name' => 'PT Lain Jaya']));

    $this->actingAs($superadmin);

    $response = $this->get(route('customer.export', [
        'search' => 'Export Contoh',
        'search_column' => 'customer_name',
    ]))
        ->assertOk()
        ->assertDownload('customers-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('No,Kota,Lokasi,"Nama Customer","Tipe Rak","Jenis Layanan","Sales Chanel","Status Bisnis","Status Layanan","No Order",SID,"Tanggal Aktivasi","Power Source","Panel PDU","Nomor MCB",Connectivity,IP,Switch,"Port Number",VLAN,"Layanan VLAN","Nama AM / Sales",Catatan')
        ->toContain('"PT Export Contoh"')
        ->not->toContain('"PT Lain Jaya"');
});
