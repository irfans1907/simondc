<?php

use App\Models\Menu;
use Database\Seeders\SpatiePermissionSeeder;

test('guests are redirected from the menus management page', function () {
    $this->get(route('menus.index'))->assertRedirect(route('login'));
});

test('a user without the manage menus permission is forbidden', function () {
    $this->actingAs(adminUser());

    $this->get(route('menus.index'))->assertForbidden();
});

test('a superadmin can visit the menus management page', function () {
    $this->actingAs(superadminUser());

    $this->get(route('menus.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('menus/index')->has('paginatedMenus.data'));
});

test('child menus are listed directly after their parent', function () {
    $this->seed(SpatiePermissionSeeder::class);
    $this->actingAs(superadminUser());

    $monitoring = Menu::where('name', 'Monitoring')->firstOrFail();
    $assets = Menu::where('name', 'Asset Management')->firstOrFail();
    $rack = Menu::where('name', 'Rack Management')->firstOrFail();

    $this->get(route('menus.index', ['per_page' => 20]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('menus/index')
            ->has('paginatedMenus.data', 16)
            ->where('paginatedMenus.data.0.name', 'Dashboard')
            ->where('paginatedMenus.data.1.name', 'Monitoring')
            ->where('paginatedMenus.data.2.name', 'Overview')
            ->where('paginatedMenus.data.2.parent_id', $monitoring->id)
            ->where('paginatedMenus.data.3.name', 'Sensors')
            ->where('paginatedMenus.data.4.name', 'CCTV')
            ->where('paginatedMenus.data.5.name', 'Alarms')
            ->where('paginatedMenus.data.6.name', 'Reports')
            ->where('paginatedMenus.data.7.name', 'Asset Management')
            ->where('paginatedMenus.data.8.name', 'Overview')
            ->where('paginatedMenus.data.8.parent_id', $assets->id)
            ->where('paginatedMenus.data.9.name', 'Category List')
            ->where('paginatedMenus.data.10.name', 'Panel Power List')
            ->where('paginatedMenus.data.11.name', 'Rack Management')
            ->where('paginatedMenus.data.12.name', 'Overview')
            ->where('paginatedMenus.data.12.parent_id', $rack->id)
            ->where('paginatedMenus.data.13.name', 'Rack List')
            ->where('paginatedMenus.data.14.name', 'My Files')
            ->where('paginatedMenus.data.15.name', 'Shared with Me'));
});

test('the same menu name is allowed under different parents', function () {
    $this->seed(SpatiePermissionSeeder::class);
    $this->actingAs(superadminUser());

    $parent = Menu::create([
        'name' => 'Reports Hub',
        'group' => 'Main Menu',
        'url' => '#',
        'icon' => '',
        'order' => 5,
        'is_active' => true,
    ]);

    $this->post(route('menus.store'), [
        'name' => 'Overview',
        'group' => 'Main Menu',
        'parent_id' => $parent->id,
        'url' => '/reports/overview',
        'order' => 0,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('menus', [
        'name' => 'Overview',
        'parent_id' => $parent->id,
        'url' => '/reports/overview',
    ]);
});

test('a menu name must be unique within the same parent', function () {
    $this->seed(SpatiePermissionSeeder::class);
    $this->actingAs(superadminUser());

    $monitoring = Menu::where('name', 'Monitoring')->firstOrFail();

    $this->post(route('menus.store'), [
        'name' => 'Overview',
        'group' => 'Main Menu',
        'parent_id' => $monitoring->id,
        'url' => '/monitoring/duplicate',
        'order' => 0,
    ])->assertSessionHasErrors('name');
});

test('a top-level menu name must be unique among top-level menus', function () {
    $this->seed(SpatiePermissionSeeder::class);
    $this->actingAs(superadminUser());

    $this->post(route('menus.store'), [
        'name' => 'Dashboard',
        'group' => 'Main Menu',
        'url' => '/dashboard-2',
        'order' => 5,
    ])->assertSessionHasErrors('name');
});

test('seeding creates the asset and rack menus with their children', function () {
    $this->seed(SpatiePermissionSeeder::class);

    $assets = Menu::where('name', 'Asset Management')->firstOrFail();
    expect($assets->url)->toBe('/assets');

    $assetChildren = Menu::where('parent_id', $assets->id)->orderBy('order')->get(['name', 'url']);
    expect($assetChildren->pluck('url')->all())->toBe(['/assets/overview', '/assets/categorylist', '/assets/panelpowerlist']);

    $rack = Menu::where('name', 'Rack Management')->firstOrFail();
    expect($rack->url)->toBe('/rack');

    $rackChildren = Menu::where('parent_id', $rack->id)->orderBy('order')->get(['name', 'url']);
    expect($rackChildren->pluck('url')->all())->toBe(['/rack/overview', '/rack/racklist']);
    expect($rackChildren->pluck('name')->all())->toBe(['Overview', 'Rack List']);
});

test('every seeded child menu has a matching dynamic page file', function () {
    $this->seed(SpatiePermissionSeeder::class);

    $missing = Menu::whereNotNull('parent_id')
        ->get()
        ->reject(fn (Menu $menu) => $menu->url === '#' || $menu->url === '')
        ->filter(function (Menu $menu) {
            $slug = trim(explode('?', $menu->url, 2)[0], '/');

            return ! is_dir(resource_path('js/pages/dynamic/'.$slug))
                && ! file_exists(resource_path('js/pages/dynamic/'.$slug.'.tsx'));
        })
        ->pluck('url')
        ->all();

    expect($missing)->toBeEmpty();
});

test('new parent and child menus generate pages with clickable breadcrumb layouts', function () {
    $this->seed(SpatiePermissionSeeder::class);
    $this->actingAs(superadminUser());

    $parentFile = resource_path('js/pages/dynamic/power-audit/index.tsx');
    $childFile = resource_path('js/pages/dynamic/power-audit/health/index.tsx');

    try {
        $this->post(route('menus.store'), [
            'name' => 'Power Audit',
            'group' => 'Main Menu',
            'url' => '/power-audit',
            'order' => 97,
        ])->assertSessionHasNoErrors();

        expect(file_get_contents($parentFile))
            ->toContain('href: props.menu.url')
            ->not->toContain("href: '#'");

        $parentMenu = Menu::where('url', '/power-audit')->firstOrFail();

        $this->post(route('menus.store'), [
            'name' => 'Health Check',
            'group' => 'Main Menu',
            'parent_id' => $parentMenu->id,
            'url' => '/power-audit/health',
            'order' => 0,
        ])->assertSessionHasNoErrors();

        expect(file_get_contents($childFile))
            ->toContain('showChildCrumbs')
            ->toContain("menuBreadcrumbs(props.menus, '/power-audit/health')")
            ->not->toContain("href: '#'");

        $this->get('/power-audit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dynamic/power-audit', false)
                ->where('menu.url', '/power-audit'));

        $this->get('/power-audit/health')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dynamic/power-audit/health', false)
                ->where('menu.name', 'Power Audit')
                ->where('activeChild.name', 'Health Check')
                ->where('activeChild.url', '/power-audit/health'));
    } finally {
        @unlink($childFile);
        @unlink($parentFile);
        @rmdir(dirname($childFile));
        @rmdir(dirname($parentFile));
    }
});
