<?php

use App\Models\Asset;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

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
