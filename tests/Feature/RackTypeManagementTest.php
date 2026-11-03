<?php

use App\Models\Rack;
use App\Models\RackType;
use App\Models\User;
use Database\Seeders\RackTypeSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view rack management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage rack management', 'guard_name' => 'web']);

    $this->seed(RackTypeSeeder::class);
});

function rackTypePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mini Rack',
        'capacity' => '10U',
    ], $overrides);
}

test('guests are redirected from the rack type list', function () {
    $this->get(route('rack.racklist'))->assertRedirect(route('login'));
});

test('a user without rack permissions cannot view the rack type list', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('rack.racklist'))->assertForbidden();
});

test('a user with only the view permission sees the rack type list read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view rack management');

    $this->actingAs($user);

    $this->get(route('rack.racklist'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/rack/racklist', false)
            ->where('canManage', false)
            ->has('rackTypes.data')
        );
});

test('the rack type list shows the seeded types with rack counts', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');
    Rack::factory()->create(['type' => 'U Rack', 'capacity' => '1U']);

    $this->actingAs($superadmin);

    $this->get(route('rack.racklist'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/rack/racklist', false)
            ->has('rackTypes.data', 4)
            ->where('rackTypes.data.0.name', 'Full Rack Network')
            ->where('filters.search_column', 'all')
            ->where('filters.per_page', 10)
        );
});

test('the rack type list paginates and counts racks per type', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');
    Rack::factory()->create(['type' => 'U Rack', 'capacity' => '1U']);
    Rack::factory()->create(['type' => 'U Rack', 'capacity' => '1U']);

    $this->actingAs($superadmin);

    $this->get(route('rack.racklist', ['search' => 'U Rack', 'search_column' => 'name']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rackTypes.data', 1)
            ->where('rackTypes.data.0.name', 'U Rack')
            ->where('rackTypes.data.0.racks_count', 2)
            ->where('filters.search', 'U Rack')
        );
});

test('a rack type can be created', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.racklist.store'), rackTypePayload())
        ->assertRedirect(route('rack.racklist'));

    $this->assertDatabaseHas('rack_types', [
        'name' => 'Mini Rack',
        'capacity' => '10U',
    ]);
});

test('creating a rack type requires a name and capacity', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.racklist.store'), ['name' => '', 'capacity' => ''])
        ->assertSessionHasErrors(['name', 'capacity']);
});

test('the rack type name must be unique', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');

    $this->actingAs($superadmin);

    $this->post(route('rack.racklist.store'), rackTypePayload(['name' => 'U Rack']))
        ->assertSessionHasErrors('name');
});

test('a user without the manage permission cannot create a rack type', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view rack management');

    $this->actingAs($user);

    $this->post(route('rack.racklist.store'), rackTypePayload())->assertForbidden();

    $this->assertDatabaseMissing('rack_types', ['name' => 'Mini Rack']);
});

test('renaming a rack type cascades the new name to its racks', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create(['type' => 'Sub Rack', 'capacity' => '15U']);
    $subRack = RackType::query()->where('name', 'Sub Rack')->firstOrFail();

    $this->actingAs($superadmin);

    $this->patch(route('rack.racklist.update', $subRack), rackTypePayload([
        'name' => 'Compact Rack',
        'capacity' => '15U',
    ]))->assertRedirect(route('rack.racklist'));

    $this->assertDatabaseHas('racks', [
        'id' => $rack->id,
        'type' => 'Compact Rack',
        'capacity' => '15U',
    ]);
    $this->assertDatabaseHas('rack_types', [
        'id' => $subRack->id,
        'name' => 'Compact Rack',
    ]);
});

test('changing a rack type capacity cascades to its racks', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $rack = Rack::factory()->create(['type' => 'U Rack', 'capacity' => '1U']);
    $uRack = RackType::query()->where('name', 'U Rack')->firstOrFail();

    $this->actingAs($superadmin);

    $this->patch(route('rack.racklist.update', $uRack), rackTypePayload([
        'name' => 'U Rack',
        'capacity' => '2U',
    ]))->assertRedirect(route('rack.racklist'));

    $this->assertDatabaseHas('racks', [
        'id' => $rack->id,
        'capacity' => '2U',
    ]);
});

test('a rack type can be updated to its own name', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $subRack = RackType::query()->where('name', 'Sub Rack')->firstOrFail();

    $this->actingAs($superadmin);

    $this->patch(route('rack.racklist.update', $subRack), rackTypePayload([
        'name' => 'Sub Rack',
        'capacity' => '16U',
    ]))->assertRedirect(route('rack.racklist'));

    $this->assertDatabaseHas('rack_types', [
        'id' => $subRack->id,
        'capacity' => '16U',
    ]);
});

test('deleting a rack type is blocked while racks still use it', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    Rack::factory()->create(['type' => 'Full Rack Server', 'capacity' => '42U']);
    $fullServer = RackType::query()->where('name', 'Full Rack Server')->firstOrFail();

    $this->actingAs($superadmin);

    $this->delete(route('rack.racklist.destroy', $fullServer))
        ->assertRedirect(route('rack.racklist'))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('rack_types', ['id' => $fullServer->id]);
});

test('an unused rack type can be deleted', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage rack management');
    $fullNetwork = RackType::query()->where('name', 'Full Rack Network')->firstOrFail();

    $this->actingAs($superadmin);

    $this->delete(route('rack.racklist.destroy', $fullNetwork))
        ->assertRedirect(route('rack.racklist'));

    $this->assertDatabaseMissing('rack_types', ['id' => $fullNetwork->id]);
});

test('an unknown rack type search column falls back to searching all columns', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('view rack management');

    $this->actingAs($superadmin);

    $this->get(route('rack.racklist', ['search' => '15U', 'search_column' => 'DROP TABLE']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search_column', 'all')
            ->has('rackTypes.data', 1)
            ->where('rackTypes.data.0.name', 'Sub Rack')
        );
});
