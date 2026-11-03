<?php

use App\Models\Asset;
use App\Models\Category;
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
