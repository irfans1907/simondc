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

test('guests are redirected from the asset category page', function () {
    $this->get(route('asset-categories.index'))->assertRedirect(route('login'));
});

test('the category page is served from the categorylist url', function () {
    expect(route('asset-categories.index', absolute: false))->toBe('/assets/categorylist');
});

test('a user without the view asset management permission is forbidden', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('asset-categories.index'))->assertForbidden();
});

test('a user with only the view permission can see the page but cannot manage', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $assetMenu = Menu::create([
        'name' => 'Asset Management',
        'group' => 'Main Menu',
        'url' => '/assets',
        'icon' => 'Boxes',
        'order' => 3,
        'is_active' => true,
    ]);

    Menu::create([
        'name' => 'Category List',
        'group' => 'Main Menu',
        'url' => '/assets/categorylist',
        'parent_id' => $assetMenu->id,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->get(route('asset-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dynamic/assets/categorylist', false)
            ->where('canManage', false)
            ->has('menu')
            ->has('activeChild')
            ->where('menu.url', '/assets')
            ->where('activeChild.url', '/assets/categorylist')
        );
});

test('a user without the manage permission cannot create a category', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->post(route('asset-categories.store'), ['name' => 'Server'])->assertForbidden();
});

test('the category page lists categories as a flat tree with counts', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $root = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Rack', 'parent_id' => $root->id]);
    Asset::factory()->create(['category_id' => $child->id]);

    $this->actingAs($superadmin);

    $this->get(route('asset-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dynamic/assets/categorylist', false)
            ->where('canManage', true)
            ->has('categories', 2)
            ->where('categories.0.name', 'Server')
            ->where('categories.0.depth', 0)
            ->where('categories.0.assets_count', 1)
            ->where('categories.1.name', 'Rack')
            ->where('categories.1.depth', 1)
            ->where('categories.1.assets_count', 1)
        );
});

test('a root category can be created', function () {
    $superadmin = superadminUser();

    $this->actingAs($superadmin);

    $this->post(route('asset-categories.store'), ['name' => 'Jaringan'])
        ->assertRedirect(route('asset-categories.index'));

    expect(Category::whereNull('parent_id')->where('name', 'Jaringan')->exists())->toBeTrue();
});

test('a sub-category can be created under a parent', function () {
    $superadmin = superadminUser();
    $root = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->post(route('asset-categories.store'), [
        'name' => 'Blade',
        'parent_id' => $root->id,
    ])->assertRedirect(route('asset-categories.index'));

    expect(Category::where('parent_id', $root->id)->where('name', 'Blade')->exists())->toBeTrue();
});

test('a duplicate name under the same parent is rejected even with different casing', function () {
    $superadmin = superadminUser();
    Category::factory()->create(['name' => 'Server', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->post(route('asset-categories.store'), ['name' => 'server'])
        ->assertSessionHasErrors('name');

    expect(Category::whereNull('parent_id')->count())->toBe(1);
});

test('the same name is allowed under a different parent', function () {
    $superadmin = superadminUser();
    Category::factory()->create(['name' => 'Server', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->post(route('asset-categories.store'), [
        'name' => 'Server',
        'parent_id' => Category::factory()->create(['parent_id' => null])->id,
    ])->assertSessionHasNoErrors();

    expect(Category::where('name', 'Server')->count())->toBe(2);
});

test('a category can be renamed', function () {
    $superadmin = superadminUser();
    $category = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->patch(route('asset-categories.update', $category), ['name' => 'Server Legacy'])
        ->assertRedirect(route('asset-categories.index'));

    expect($category->fresh()->name)->toBe('Server Legacy');
});

test('renaming to a sibling name is rejected', function () {
    $superadmin = superadminUser();
    Category::factory()->create(['name' => 'Server', 'parent_id' => null]);
    $other = Category::factory()->create(['name' => 'Switch', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->patch(route('asset-categories.update', $other), ['name' => 'SERVER'])
        ->assertSessionHasErrors('name');
});

test('a category with children cannot be deleted', function () {
    $superadmin = superadminUser();
    $root = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);
    Category::factory()->create(['name' => 'Rack', 'parent_id' => $root->id]);

    $this->actingAs($superadmin);

    $this->delete(route('asset-categories.destroy', $root))
        ->assertRedirect(route('asset-categories.index'))
        ->assertSessionHas('inertia.flash_data');

    expect(Category::find($root->id))->not->toBeNull();
});

test('a category used by assets cannot be deleted', function () {
    $superadmin = superadminUser();
    $category = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);
    Asset::factory()->create(['category_id' => $category->id]);

    $this->actingAs($superadmin);

    $this->delete(route('asset-categories.destroy', $category))
        ->assertRedirect(route('asset-categories.index'));

    expect(Category::find($category->id))->not->toBeNull();
});

test('an unused leaf category can be deleted', function () {
    $superadmin = superadminUser();
    $category = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->delete(route('asset-categories.destroy', $category))
        ->assertRedirect(route('asset-categories.index'));

    expect(Category::find($category->id))->toBeNull();
});

test('guests are redirected from bulk category deletion', function () {
    $this->delete(route('asset-categories.bulkDestroy'), ['ids' => [1]])
        ->assertRedirect(route('login'));
});

test('a user without the manage permission cannot bulk delete categories', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->delete(route('asset-categories.bulkDestroy'), ['ids' => [1]])->assertForbidden();
});

test('bulk deletion deletes leaf categories', function () {
    $superadmin = superadminUser();
    $first = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);
    $second = Category::factory()->create(['name' => 'Jaringan', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->delete(route('asset-categories.bulkDestroy'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect(route('asset-categories.index'));

    expect(Category::count())->toBe(0);
});

test('bulk deletion skips categories with children or assets', function () {
    $superadmin = superadminUser();
    $root = Category::factory()->create(['name' => 'Server', 'parent_id' => null]);
    Category::factory()->create(['name' => 'Rack', 'parent_id' => $root->id]);
    $used = Category::factory()->create(['name' => 'APAR', 'parent_id' => null]);
    Asset::factory()->create(['category_id' => $used->id]);
    $free = Category::factory()->create(['name' => 'Kabel', 'parent_id' => null]);

    $this->actingAs($superadmin);

    $this->delete(route('asset-categories.bulkDestroy'), [
        'ids' => [$root->id, $used->id, $free->id],
    ])->assertSessionHas('inertia.flash_data');

    expect(Category::find($root->id))->not->toBeNull();
    expect(Category::find($used->id))->not->toBeNull();
    expect(Category::find($free->id))->toBeNull();
});

test('bulk deletion requires at least one id', function () {
    $superadmin = superadminUser();

    $this->actingAs($superadmin);

    $this->delete(route('asset-categories.bulkDestroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
