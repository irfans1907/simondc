<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use App\Models\User;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ChecklistTemplateSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view checklist', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage checklist', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);

    $this->seed(AssetCategorySeeder::class);
    $this->seed(ChecklistSeeder::class);
    $this->seed(ChecklistTemplateSeeder::class);
});

function cctvChecklistCategory(): ChecklistCategory
{
    return ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
}

function cctvAssetCategory(string $name): Category
{
    return Category::query()
        ->whereNull('parent_id')
        ->where('name', $name)
        ->firstOrFail();
}

/**
 * @return array{0: Asset, 1: Asset}
 */
function makeCctvOneChannels(): array
{
    $category = cctvChecklistCategory();
    $dvr = cctvAssetCategory('DVR');

    $first = Asset::factory()->create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-1',
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ]);

    $second = Asset::factory()->create([
        'category_id' => $dvr->id,
        'name' => 'CCTV-1',
        'in_checklist' => true,
        'checklist_category_id' => $category->id,
    ]);

    return [$first, $second];
}

test('CCTV devices get one status row per name and category', function () {
    makeCctvOneChannels();

    Asset::factory()->create([
        'category_id' => cctvAssetCategory('NVR')->id,
        'name' => 'CCTV-2',
        'in_checklist' => true,
        'checklist_category_id' => cctvChecklistCategory()->id,
    ]);

    $items = ChecklistItem::query()
        ->where('checklist_category_id', cctvChecklistCategory()->id)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('label')->all())->toBe([
            'CCTV-1 DVR',
            'CCTV-2 NVR',
        ])
        ->and($items->every(fn (ChecklistItem $item): bool => $item->input_type === 'status'))->toBeTrue()
        ->and($items->every(fn (ChecklistItem $item): bool => $item->unit === null))->toBeTrue()
        ->and($items->every(fn (ChecklistItem $item): bool => $item->section === 'CCTV'))->toBeTrue();
});

test('the daily check lists one row per assigned CCTV device', function () {
    makeCctvOneChannels();

    Asset::factory()->create([
        'category_id' => cctvAssetCategory('NVR')->id,
        'name' => 'CCTV-2',
        'in_checklist' => true,
        'checklist_category_id' => cctvChecklistCategory()->id,
    ]);

    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view checklist');

    $this->actingAs($user);

    $this->get(route('checklist.dailycheck', [
        'date' => today()->toDateString(),
        'category' => cctvChecklistCategory()->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.11.slug', 'cctv')
            ->has('categories.11.items', 2)
            ->where('categories.11.items.0.label', 'CCTV-1 DVR')
            ->where('categories.11.items.1.label', 'CCTV-2 NVR')
        );
});

test('asset settings shows one row per CCTV device', function () {
    makeCctvOneChannels();

    Asset::factory()->create([
        'category_id' => cctvAssetCategory('NVR')->id,
        'name' => 'CCTV-2',
        'in_checklist' => true,
        'checklist_category_id' => cctvChecklistCategory()->id,
    ]);

    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 2)
            ->where('assets.data.0.name', 'CCTV-1 DVR')
            ->has('assets.data.0.members', 2)
            ->where('assets.data.0.scan_group.name', 'CCTV-1')
            ->where('assets.data.0.scan_group.category_id', cctvAssetCategory('DVR')->id)
            ->where('assets.data.1.name', 'CCTV-2 NVR')
            ->has('assets.data.1.members', 1)
        );
});

test('the grouped CCTV sticker opens the device checklist', function () {
    makeCctvOneChannels();

    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.scan.group', [
        'name' => 'CCTV-1',
        'category' => cctvAssetCategory('DVR')->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.display_name', 'CCTV-1 DVR')
            ->has('members', 2)
            ->has('checklist.items', 1)
            ->where('checklist.items.0.label', 'CCTV-1 DVR')
        );
});

test('scanning one CCTV channel shows its device checklist', function () {
    [$asset] = makeCctvOneChannels();

    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('asset.display_name', 'CCTV-1 DVR')
            ->has('members', 2)
            ->has('checklist.items', 1)
            ->where('checklist.items.0.label', 'CCTV-1 DVR')
        );
});

test('removing one CCTV channel keeps the shared device checklist', function () {
    [$first, $second] = makeCctvOneChannels();

    $first->delete();

    expect(ChecklistItem::query()
        ->where('checklist_category_id', cctvChecklistCategory()->id)
        ->where('label', 'CCTV-1 DVR')
        ->where('is_active', true)
        ->count())->toBe(1);

    $second->update(['in_checklist' => false]);

    expect(ChecklistItem::query()
        ->where('checklist_category_id', cctvChecklistCategory()->id)
        ->where('label', 'CCTV-1 DVR')
        ->where('is_active', true)
        ->count())->toBe(0);
});
