<?php

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\SpatiePermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view monitoring', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage monitoring', 'guard_name' => 'web']);

    $monitoring = Menu::firstOrCreate(
        ['name' => 'Monitoring'],
        ['group' => 'Main Menu', 'url' => '/monitoring', 'icon' => 'Monitor', 'order' => 1, 'is_active' => true],
    );

    Menu::firstOrCreate(
        ['name' => 'Overview'],
        ['group' => 'Main Menu', 'parent_id' => $monitoring->id, 'url' => '/monitoring/overview', 'icon' => '', 'order' => 0, 'is_active' => true],
    );

    Menu::firstOrCreate(
        ['name' => 'Sensors'],
        ['group' => 'Main Menu', 'parent_id' => $monitoring->id, 'url' => '/monitoring/sensors', 'icon' => '', 'order' => 1, 'is_active' => true],
    );

    Menu::firstOrCreate(
        ['name' => 'CCTV'],
        ['group' => 'Main Menu', 'parent_id' => $monitoring->id, 'url' => '/monitoring/cctv', 'icon' => '', 'order' => 2, 'is_active' => true],
    );

    Menu::firstOrCreate(
        ['name' => 'Alarms'],
        ['group' => 'Main Menu', 'parent_id' => $monitoring->id, 'url' => '/monitoring/alarms', 'icon' => '', 'order' => 3, 'is_active' => true],
    );
});

function monitoringUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view monitoring']);

    return $user;
}

test('guests are redirected from the monitoring page', function () {
    $this->get('/monitoring')->assertRedirect(route('login'));
});

test('the monitoring root redirects to the monitoring overview page', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring')->assertRedirect('/monitoring/overview');
});

test('a user without permission gets a 403 on the monitoring page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/monitoring')->assertForbidden();
});

test('a legacy monitoring tab query still redirects to the overview page', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring?tab=cctv')->assertRedirect('/monitoring/overview');
});

test('a user can visit the monitoring cctv child page', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring/cctv')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dynamic/monitoring/cctv', false));
});

test('a user can visit the monitoring alarms child page', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring/alarms')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dynamic/monitoring/alarms', false));
});

test('a user can visit a monitoring child page by path', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring/overview')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dynamic/monitoring/overview', false)
            ->has('menus')
            ->where('menu.url', '/monitoring')
            ->where('activeChild.url', '/monitoring/overview'));
});

test('a user can visit the monitoring sensors child page by path', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring/sensors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dynamic/monitoring/sensors', false));
});

test('a user without permission gets a 403 on a monitoring child page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/monitoring/overview')->assertForbidden();
});

test('a monitoring child page 404s when no child menu matches the path', function () {
    $this->actingAs(monitoringUser());

    $this->get('/monitoring/unknown-section')->assertNotFound();
});

test('seeding creates monitoring child menus linked to the parent', function () {
    $this->seed(SpatiePermissionSeeder::class);

    $monitoring = Menu::where('name', 'Monitoring')->firstOrFail();
    $children = Menu::where('parent_id', $monitoring->id)
        ->orderBy('order')
        ->get(['name', 'url']);

    expect($children->pluck('name')->all())->toBe(['Overview', 'Sensors', 'CCTV', 'Alarms']);
    expect($children->pluck('url')->all())->toBe([
        '/monitoring/overview',
        '/monitoring/sensors',
        '/monitoring/cctv',
        '/monitoring/alarms',
    ]);
});
