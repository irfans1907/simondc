<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistEntry;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemTemplate;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ChecklistTemplateSeeder;

beforeEach(function () {
    $this->seed(AssetCategorySeeder::class);
    $this->seed(ChecklistSeeder::class);
    $this->seed(ChecklistTemplateSeeder::class);
});

function rootCategory(string $name): Category
{
    return Category::query()
        ->whereNull('parent_id')
        ->where('name', $name)
        ->firstOrFail();
}

function checklistCategoryId(string $slug): int
{
    return ChecklistCategory::query()->where('slug', $slug)->value('id');
}

test('the template seeder defines one UPS template and maps it to UPS 2', function () {
    $ups = rootCategory('UPS');
    $ups2 = ChecklistCategory::query()->where('slug', 'ups-2')->firstOrFail();

    expect(ChecklistItemTemplate::query()->where('category_id', $ups->id)->count())->toBe(20)
        ->and($ups2->assetCategories()->pluck('categories.id')->all())->toContain($ups->id);
});

test('the legacy UPS categories are nested under the single UPS category', function () {
    $ups = rootCategory('UPS');

    foreach (['UPS Frame', 'UPS Modul', 'UPS Electronic', 'Battery'] as $name) {
        expect(Category::query()->where('name', $name)->value('parent_id'))->toBe($ups->id);
    }
});

test('the template seeder fills templates and maps asset categories', function () {
    $pac = rootCategory('PAC');
    $pacAc = ChecklistCategory::query()->where('slug', 'pac-ac')->firstOrFail();

    expect(ChecklistItemTemplate::query()->where('category_id', $pac->id)->count())->toBe(4)
        ->and($pacAc->assetCategories()->pluck('categories.id')->all())->toContain($pac->id);
});

test('a fresh asset model defaults to the checklist and maintenance modules', function () {
    $asset = new Asset;

    expect($asset->in_checklist)->toBeTrue()
        ->and($asset->in_maintenance)->toBeTrue();
});

test('saving an in-checklist asset materialises its checklist items', function () {
    $pac = rootCategory('PAC');
    $pacAcId = checklistCategoryId('pac-ac');

    $asset = Asset::factory()->create([
        'category_id' => $pac->id,
        'name' => 'PAC-3.1',
        'floor' => '3',
        'room' => 'Data Hall A',
        'in_checklist' => true,
        'checklist_category_id' => $pacAcId,
    ]);

    $items = ChecklistItem::query()->where('asset_id', $asset->id)->get();

    expect($items)->toHaveCount(4)
        ->and($items->every(fn (ChecklistItem $item): bool => $item->scope === 'asset'))->toBeTrue()
        ->and($items->every(fn (ChecklistItem $item): bool => $item->is_active))->toBeTrue()
        ->and($items->first()->section)->toBe('Lantai 3 - Data Hall A')
        ->and($items->first()->group)->toBe('PAC-3.1')
        ->and($items->pluck('checklist_category_id')->unique()->all())->toBe([$pacAcId]);
});

test('an asset without a checklist category generates no items', function () {
    $asset = Asset::factory()->create([
        'category_id' => rootCategory('PAC')->id,
        'in_checklist' => true,
        'checklist_category_id' => null,
    ]);

    expect(ChecklistItem::query()
        ->where('asset_id', $asset->id)
        ->where('is_active', true)
        ->count())->toBe(0);
});

test('turning in_checklist off deactivates the generated items', function () {
    $asset = Asset::factory()->create([
        'category_id' => rootCategory('PAC')->id,
        'in_checklist' => true,
        'checklist_category_id' => checklistCategoryId('pac-ac'),
    ]);

    $asset->update(['in_checklist' => false]);

    expect(ChecklistItem::query()
        ->where('asset_id', $asset->id)
        ->where('is_active', true)
        ->count())->toBe(0);
});

test('a nested subcategory uses the root template', function () {
    $ups = rootCategory('UPS');
    $upsFrame = Category::query()->where('name', 'UPS Frame')->firstOrFail();
    $modul = Category::factory()->create([
        'name' => 'Modul 9',
        'parent_id' => $upsFrame->id,
    ]);

    $asset = Asset::factory()->create([
        'category_id' => $modul->id,
        'in_checklist' => true,
        'checklist_category_id' => checklistCategoryId('ups-2'),
    ]);

    expect($asset->category->root()->is($ups))->toBeTrue()
        ->and(ChecklistItem::query()
            ->where('asset_id', $asset->id)
            ->where('is_active', true)
            ->count())->toBe(20);
});

test('changing the checklist category moves the same items and keeps their entries', function () {
    $asset = Asset::factory()->create([
        'category_id' => rootCategory('PAC')->id,
        'in_checklist' => true,
        'checklist_category_id' => checklistCategoryId('pac-ac'),
    ]);

    $item = ChecklistItem::query()->where('asset_id', $asset->id)->firstOrFail();
    $entry = ChecklistEntry::factory()->create([
        'checklist_item_id' => $item->id,
        'entry_date' => '2026-11-09',
        'time_slot' => '09:00',
        'value' => 21,
    ]);

    $asset->update(['checklist_category_id' => checklistCategoryId('ruangan')]);

    expect($item->fresh()->checklist_category_id)->toBe(checklistCategoryId('ruangan'))
        ->and(ChecklistEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

test('the sync command materialises items and can retire the legacy infrastructure items', function () {
    $asset = Asset::factory()->create([
        'category_id' => rootCategory('Panel Power')->id,
        'name' => 'PDU-LINK',
        'in_checklist' => true,
        'checklist_category_id' => checklistCategoryId('load-dc'),
    ]);

    $this->artisan('checklist:sync-assets', ['--deactivate-legacy' => true])
        ->assertSuccessful();

    $scopedCategoryIds = ChecklistCategory::query()
        ->whereIn('slug', ChecklistCategory::categoryScopedSlugs())
        ->pluck('id');

    $scopedItemCount = collect(config('checklist.categories', []))
        ->filter(fn (array $category): bool => ($category['asset_items'] ?? true) === false)
        ->sum(fn (array $category): int => count($category['items']));

    expect(ChecklistItem::query()
        ->where('scope', 'infrastructure')
        ->where('is_active', true)
        ->whereNotIn('checklist_category_id', $scopedCategoryIds)
        ->count())->toBe(0)
        ->and(ChecklistItem::query()
            ->where('scope', 'infrastructure')
            ->where('is_active', true)
            ->whereIn('checklist_category_id', $scopedCategoryIds)
            ->count())->toBe($scopedItemCount)
        ->and(ChecklistItem::query()
            ->where('asset_id', $asset->id)
            ->where('scope', 'asset')
            ->where('is_active', true)
            ->count())->toBe(3);
});

test('UPS assets that share a name share one checklist item set', function () {
    $frame = Category::query()->where('name', 'UPS Frame')->firstOrFail();
    $battery = Category::query()->where('name', 'Battery')->firstOrFail();
    $ups2 = checklistCategoryId('ups-2');

    $first = Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-2',
        'floor' => '1',
        'in_checklist' => true,
        'checklist_category_id' => $ups2,
    ]);

    $second = Asset::factory()->create([
        'category_id' => $battery->id,
        'name' => 'UPS-2',
        'floor' => '1',
        'in_checklist' => true,
        'checklist_category_id' => $ups2,
    ]);

    expect(ChecklistItem::query()
        ->where('checklist_category_id', $ups2)
        ->where('scope', 'asset')
        ->where('is_active', true)
        ->count())->toBe(20)
        ->and(ChecklistItem::query()
            ->where('asset_id', $first->id)
            ->where('is_active', true)
            ->count())->toBe(20)
        ->and(ChecklistItem::query()
            ->where('asset_id', $second->id)
            ->count())->toBe(0);
});

test('a grouped checklist moves to the next member when the owner leaves', function () {
    $frame = Category::query()->where('name', 'UPS Frame')->firstOrFail();
    $battery = Category::query()->where('name', 'Battery')->firstOrFail();
    $ups2 = checklistCategoryId('ups-2');

    $first = Asset::factory()->create([
        'category_id' => $frame->id,
        'name' => 'UPS-A',
        'floor' => '1',
        'in_checklist' => true,
        'checklist_category_id' => $ups2,
    ]);

    $second = Asset::factory()->create([
        'category_id' => $battery->id,
        'name' => 'UPS-A',
        'floor' => '1',
        'in_checklist' => true,
        'checklist_category_id' => $ups2,
    ]);

    $first->update(['in_checklist' => false]);

    expect(ChecklistItem::query()
        ->where('checklist_category_id', $ups2)
        ->where('scope', 'asset')
        ->where('is_active', true)
        ->count())->toBe(20)
        ->and(ChecklistItem::query()
            ->where('asset_id', $second->id)
            ->where('is_active', true)
            ->count())->toBe(20);
});
