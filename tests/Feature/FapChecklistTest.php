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
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);

    $this->seed(AssetCategorySeeder::class);
    $this->seed(ChecklistSeeder::class);
    $this->seed(ChecklistTemplateSeeder::class);
});

function fapChecklistCategory(string $slug): ChecklistCategory
{
    return ChecklistCategory::query()->where('slug', $slug)->firstOrFail();
}

function fapFssPanelCategory(): Category
{
    return Category::query()
        ->whereNull('parent_id')
        ->where('name', 'FSS Panel')
        ->firstOrFail();
}

test('the FAP categories keep their config items active', function () {
    $nn100 = fapChecklistCategory('fap-nn100-lt1')
        ->items()
        ->where('is_active', true)
        ->get();

    expect($nn100)->toHaveCount(4)
        ->and($nn100->pluck('label')->all())->toBe([
            'Status Control Panel',
            'Pilot',
            'Storage',
            'Temp',
        ])
        ->and($nn100->first()->input_type)->toBe('status')
        ->and($nn100[1]->group)->toBe('Kondisi Tabung')
        ->and($nn100[1]->unit)->toBe('MPa')
        ->and((float) $nn100[1]->min_value)->toBe(25.0)
        ->and((float) $nn100[1]->max_value)->toBe(30.0);

    $ig55 = fapChecklistCategory('fap-ig55-lt2')
        ->items()
        ->where('is_active', true)
        ->get();

    expect($ig55)->toHaveCount(3)
        ->and($ig55[1]->label)->toBe('Storage')
        ->and($ig55[1]->unit)->toBe('bar')
        ->and((float) $ig55[1]->min_value)->toBe(240.0)
        ->and((float) $ig55[1]->max_value)->toBe(370.0);

    $fm200 = fapChecklistCategory('fap-fm200')
        ->items()
        ->where('is_active', true)
        ->get();

    expect($fm200)->toHaveCount(3)
        ->and($fm200[1]->label)->toBe('Kondisi Tabung')
        ->and($fm200[1]->input_type)->toBe('status');
});

test('the daily check shows the FAP category items without asset duplicates', function () {
    Permission::firstOrCreate(['name' => 'view checklist', 'guard_name' => 'web']);
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view checklist');

    $asset = Asset::factory()->create([
        'category_id' => fapFssPanelCategory()->id,
        'name' => 'FM100',
        'in_checklist' => true,
        'checklist_category_id' => fapChecklistCategory('fap-nn100-lt1')->id,
    ]);

    $this->actingAs($user);

    $this->get(route('checklist.dailycheck', [
        'date' => today()->toDateString(),
        'category' => fapChecklistCategory('fap-nn100-lt1')->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 13)
            ->where('categories.7.slug', 'fap-nn100-lt1')
            ->has('categories.7.items', 4)
            ->where('categories.7.items.1.group', 'Kondisi Tabung')
            ->has('categories.8.items', 0)
            ->has('categories.9.items', 0)
            ->has('categories.10.items', 0)
        );

    expect(ChecklistItem::query()
        ->where('asset_id', $asset->id)
        ->where('is_active', true)
        ->count())->toBe(0);
});

test('unassigned FAP tabs stay visible with zero items across the checklist pages', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view checklist');
    $user->givePermissionTo('manage checklist');

    $nn100 = fapChecklistCategory('fap-nn100-lt1');
    $ig55 = fapChecklistCategory('fap-ig55-lt2');

    Asset::factory()->create([
        'category_id' => fapFssPanelCategory()->id,
        'name' => 'FM100',
        'in_checklist' => true,
        'checklist_category_id' => $nn100->id,
    ]);

    $this->actingAs($user);

    $fapSlugs = ['fap-nn100-lt1', 'fap-ig55-lt2', 'fap-nn100-lt4', 'fap-fm200'];

    $this->get(route('checklist.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', function ($categories) use ($fapSlugs): bool {
                $totals = collect($categories)
                    ->whereIn('slug', $fapSlugs)
                    ->mapWithKeys(fn (array $category): array => [$category['slug'] => $category['total_items']])
                    ->all();

                return $totals === [
                    'fap-nn100-lt1' => 4,
                    'fap-ig55-lt2' => 0,
                    'fap-nn100-lt4' => 0,
                    'fap-fm200' => 0,
                ];
            })
        );

    $this->get(route('checklist.dailycheck', ['date' => today()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', function ($categories) use ($fapSlugs): bool {
                $items = collect($categories)
                    ->whereIn('slug', $fapSlugs)
                    ->mapWithKeys(fn (array $category): array => [$category['slug'] => count($category['items'])])
                    ->all();

                return $items === [
                    'fap-nn100-lt1' => 4,
                    'fap-ig55-lt2' => 0,
                    'fap-nn100-lt4' => 0,
                    'fap-fm200' => 0,
                ];
            })
        );

    Asset::factory()->create([
        'category_id' => fapFssPanelCategory()->id,
        'name' => 'FM200',
        'in_checklist' => true,
        'checklist_category_id' => $ig55->id,
    ]);

    $this->get(route('checklist.dailycheck', ['date' => today()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories.8.items', 3)
            ->where('categories.8.slug', 'fap-ig55-lt2')
        );
});

test('scanning an FAP panel falls back to the category checklist', function () {
    $asset = Asset::factory()->create([
        'category_id' => fapFssPanelCategory()->id,
        'name' => 'FM100',
        'in_checklist' => true,
        'checklist_category_id' => fapChecklistCategory('fap-nn100-lt4')->id,
    ]);

    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.scan.show', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('checklist.items', 4)
            ->where('checklist.category.name', 'FAP NN100 Lt.4')
            ->where('checklist.items.0.label', 'Status Control Panel')
            ->where('checklist.items.1.label', 'Pilot')
        );
});

test('syncing an FAP asset does not create per-asset checklist items', function () {
    $asset = Asset::factory()->create([
        'category_id' => fapFssPanelCategory()->id,
        'name' => 'FM100',
        'in_checklist' => true,
        'checklist_category_id' => fapChecklistCategory('fap-nn100-lt4')->id,
    ]);

    $this->artisan('checklist:sync-assets')->assertSuccessful();

    expect(ChecklistItem::query()
        ->where('asset_id', $asset->id)
        ->where('is_active', true)
        ->count())->toBe(0)
        ->and(ChecklistItem::query()
            ->where('checklist_category_id', $asset->checklist_category_id)
            ->where('scope', 'infrastructure')
            ->where('is_active', true)
            ->count())->toBe(4);
});
