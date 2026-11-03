<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\User;
use App\Models\VlanTagRequest;
use App\Models\VlanTagRequestSwitch;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view customer list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);
});

/**
 * A switch asset inside the Network category (the only category the
 * request form accepts).
 */
function networkSwitchAsset(string $name = 'SW-Core-01'): Asset
{
    $network = Category::firstOrCreate(['name' => 'Network', 'parent_id' => null]);

    return Asset::factory()->create(['category_id' => $network->id, 'name' => $name]);
}

/**
 * Build a VLAN tag request payload pointing at the given switch asset.
 */
function vlanRequestPayload(Asset $switch, array $overrides = []): array
{
    return array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'order_number' => 'ORD-70001',
        'sid' => 'SID-70001',
        'service_name' => 'Colocation',
        'vlan' => '300',
        'mode' => 'ACCESS',
        'switches' => [
            ['asset_id' => $switch->id, 'port_number' => 'Gi0/1'],
        ],
    ], $overrides);
}

test('guests are redirected from the vlan tag request page', function () {
    $this->get(route('customer.vlanreq'))->assertRedirect(route('login'));
});

test('a user without view or manage permission cannot view the vlan tag request page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('customer.vlanreq'))->assertForbidden();
});

test('a user with only the view permission sees the page read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view customer list');

    $this->actingAs($user);

    $this->get(route('customer.vlanreq'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('the vlan tag request page lists requests with customer and switch details', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset('SW-Core-01');
    $customer = Customer::factory()->create(['customer_name' => 'PT Contoh Nusantara']);
    $request = VlanTagRequest::factory()->create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-70001',
        'mode' => 'ACCESS',
    ]);
    VlanTagRequestSwitch::factory()->create([
        'vlan_tag_request_id' => $request->id,
        'asset_id' => $switch->id,
        'port_number' => 'Gi0/1',
    ]);

    $this->actingAs($superadmin);

    $this->get(route('customer.vlanreq'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/customer/vlanreq', false)
            ->has('vlanTagRequests.data', 1)
            ->where('vlanTagRequests.data.0.customer.customer_name', 'PT Contoh Nusantara')
            ->where('vlanTagRequests.data.0.switches.0.asset.name', 'SW-Core-01')
            ->where('vlanTagRequests.data.0.switches.0.port_number', 'Gi0/1')
            ->has('networkAssets', 1)
            ->where('canManage', true)
        );
});

test('the list search filters by the chosen column', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $match = VlanTagRequest::factory()->create(['sid' => 'SID-77777']);
    VlanTagRequest::factory()->create(['sid' => 'SID-88888']);

    $this->actingAs($superadmin);

    $this->get(route('customer.vlanreq', ['search' => 'SID-77777', 'search_column' => 'sid']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vlanTagRequests.data', 1)
            ->where('vlanTagRequests.data.0.id', $match->id)
            ->where('filters.search_column', 'sid')
        );
});

test('creating a vlan tag request requires the mandatory fields', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');

    $this->actingAs($superadmin);

    $this->post(route('customer.vlanreq.store'), [])
        ->assertSessionHasErrors([
            'customer_id',
            'order_number',
            'sid',
            'service_name',
            'vlan',
            'mode',
            'switches',
        ]);
});

test('an invalid mode is rejected', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset();

    $this->actingAs($superadmin);

    $this->post(route('customer.vlanreq.store'), vlanRequestPayload($switch, ['mode' => 'MIRROR']))
        ->assertSessionHasErrors('mode');
});

test('a switch row must point at a network asset', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $server = Category::firstOrCreate(['name' => 'Server', 'parent_id' => null]);
    $wrong = Asset::factory()->create(['category_id' => $server->id, 'name' => 'SRV-01']);

    $this->actingAs($superadmin);

    $this->post(route('customer.vlanreq.store'), vlanRequestPayload($wrong))
        ->assertSessionHasErrors('switches.0.asset_id');
});

test('only a lag request may list more than one switch', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset();

    $this->actingAs($superadmin);

    $this->post(route('customer.vlanreq.store'), vlanRequestPayload($switch, [
        'mode' => 'ACCESS',
        'switches' => [
            ['asset_id' => $switch->id, 'port_number' => 'Gi0/1'],
            ['asset_id' => $switch->id, 'port_number' => 'Gi0/2'],
        ],
    ]))->assertSessionHasErrors('switches');
});

test('creating a vlan tag request stores it with its switch rows', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset();

    $this->actingAs($superadmin);

    $this->post(route('customer.vlanreq.store'), vlanRequestPayload($switch))
        ->assertRedirect(route('customer.vlanreq'))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('vlan_tag_requests', [
        'order_number' => 'ORD-70001',
        'sid' => 'SID-70001',
        'service_name' => 'Colocation',
        'vlan' => '300',
        'mode' => 'ACCESS',
    ]);

    $this->assertDatabaseHas('vlan_tag_request_switches', [
        'asset_id' => $switch->id,
        'port_number' => 'Gi0/1',
    ]);
});

test('a lag request may span multiple switches', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset('SW-Core-01');
    $second = networkSwitchAsset('SW-Acc-02');

    $this->actingAs($superadmin);

    $this->post(route('customer.vlanreq.store'), vlanRequestPayload($switch, [
        'mode' => 'LAG',
        'switches' => [
            ['asset_id' => $switch->id, 'port_number' => 'Gi0/1'],
            ['asset_id' => $second->id, 'port_number' => 'Gi0/2'],
        ],
    ]))
        ->assertRedirect(route('customer.vlanreq'))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseCount('vlan_tag_requests', 1);
    $this->assertDatabaseCount('vlan_tag_request_switches', 2);
});

test('updating a vlan tag request rebuilds its switch rows', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset();
    $request = VlanTagRequest::factory()->create();
    VlanTagRequestSwitch::factory()->create([
        'vlan_tag_request_id' => $request->id,
        'asset_id' => $switch->id,
        'port_number' => 'Gi0/9',
    ]);
    $customer = Customer::factory()->create();

    $this->actingAs($superadmin);

    $this->patch(route('customer.vlanreq.update', $request), [
        'customer_id' => $customer->id,
        'order_number' => 'ORD-80002',
        'sid' => 'SID-80002',
        'service_name' => 'Managed Service',
        'vlan' => '400',
        'mode' => 'LAG',
        'switches' => [
            ['asset_id' => $switch->id, 'port_number' => 'Gi0/1'],
            ['asset_id' => $switch->id, 'port_number' => 'Gi0/2'],
        ],
    ])
        ->assertRedirect(route('customer.vlanreq'))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('vlan_tag_requests', [
        'id' => $request->id,
        'order_number' => 'ORD-80002',
        'mode' => 'LAG',
    ]);
    $this->assertDatabaseCount('vlan_tag_request_switches', 2);
    $this->assertDatabaseMissing('vlan_tag_request_switches', ['port_number' => 'Gi0/9']);
});

test('a view-only user cannot create, update, delete, or export', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view customer list');
    $switch = networkSwitchAsset();
    $request = VlanTagRequest::factory()->create();
    $payload = vlanRequestPayload($switch);

    $this->actingAs($user);

    $this->post(route('customer.vlanreq.store'), $payload)->assertForbidden();
    $this->patch(route('customer.vlanreq.update', $request), $payload)->assertForbidden();
    $this->delete(route('customer.vlanreq.destroy', $request))->assertForbidden();
    $this->get(route('customer.vlanreq.export'))->assertForbidden();

    $this->assertDatabaseCount('vlan_tag_requests', 1);
});

test('deleting a vlan tag request removes its switch rows', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset();
    $request = VlanTagRequest::factory()->create();
    VlanTagRequestSwitch::factory()->create([
        'vlan_tag_request_id' => $request->id,
        'asset_id' => $switch->id,
    ]);

    $this->actingAs($superadmin);

    $this->delete(route('customer.vlanreq.destroy', $request))
        ->assertRedirect(route('customer.vlanreq'))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseCount('vlan_tag_requests', 0);
    $this->assertDatabaseCount('vlan_tag_request_switches', 0);
});

test('the export returns a csv download honoring the search filter', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage customer list');
    $switch = networkSwitchAsset('SW-Core-01');
    $customer = Customer::factory()->create(['customer_name' => 'PT Contoh Nusantara']);
    $request = VlanTagRequest::factory()->create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-70001',
        'sid' => 'SID-70001',
        'service_name' => 'Colocation',
        'vlan' => '300',
        'mode' => 'LAG',
    ]);
    VlanTagRequestSwitch::factory()->create([
        'vlan_tag_request_id' => $request->id,
        'asset_id' => $switch->id,
        'port_number' => 'Gi0/1',
    ]);
    VlanTagRequestSwitch::factory()->create([
        'vlan_tag_request_id' => $request->id,
        'asset_id' => $switch->id,
        'port_number' => 'Gi0/2',
    ]);
    VlanTagRequest::factory()->create(['order_number' => 'ORD-99999', 'sid' => 'SID-99999']);

    $this->actingAs($superadmin);

    $response = $this->get(route('customer.vlanreq.export', [
        'search' => 'SID-70001',
        'search_column' => 'sid',
    ]))
        ->assertOk()
        ->assertDownload('vlan-tag-requests-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('No,"Nomor Order",SID,"Nama Pelanggan","Nama Layanan",VLAN,Mode,Switch,"Port Number"')
        ->toContain("\n1,ORD-70001")
        ->toContain('PT Contoh Nusantara')
        ->toContain('Gi0/1; Gi0/2')
        ->not->toContain('ORD-99999');
});
