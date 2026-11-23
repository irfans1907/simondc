<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItemTemplate;
use App\Models\Customer;
use App\Models\PanelMcb;
use App\Models\PowerLoadRow;
use App\Models\PowerPanel;
use App\Models\PowerPdu;
use App\Models\Rack;

test('item templates belong to an asset category', function () {
    $category = Category::factory()->create(['name' => 'PAC']);

    $template = ChecklistItemTemplate::factory()->create([
        'category_id' => $category->id,
        'label' => 'Temperature',
        'unit' => '°C',
    ]);

    expect($template->category->is($category))->toBeTrue()
        ->and($category->checklistItemTemplates()->count())->toBe(1);
});

test('a checklist category maps to many asset categories', function () {
    $checklistCategory = ChecklistCategory::factory()->create();
    $pac = Category::factory()->create(['name' => 'PAC']);
    $ac = Category::factory()->create(['name' => 'AC']);

    $checklistCategory->assetCategories()->attach([$pac->id, $ac->id]);

    expect($checklistCategory->assetCategories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing([$pac->id, $ac->id])
        ->and($pac->checklistCategories->pluck('id')->all())
        ->toContain($checklistCategory->id);
});

test('power pdus and panels link to an asset', function () {
    $asset = Asset::factory()->create();

    $pdu = PowerPdu::factory()->create(['asset_id' => $asset->id]);
    $panel = PowerPanel::factory()->create(['asset_id' => $asset->id]);

    expect($pdu->asset->is($asset))->toBeTrue()
        ->and($panel->asset->is($asset))->toBeTrue();
});

test('power load rows link to a rack customer and panel mcb', function () {
    $rack = Rack::factory()->create();
    $customer = Customer::factory()->create(['rack_id' => $rack->id]);
    $panel = Asset::factory()->create();
    $mcb = PanelMcb::factory()->create(['asset_id' => $panel->id]);

    $row = PowerLoadRow::factory()->create([
        'rack_id' => $rack->id,
        'customer_id' => $customer->id,
        'panel_mcb_id' => $mcb->id,
    ]);

    expect($row->linkedRack->is($rack))->toBeTrue()
        ->and($row->linkedCustomer->is($customer))->toBeTrue()
        ->and($row->panelMcb->is($mcb))->toBeTrue();
});

test('deleting an asset clears the power links', function () {
    $asset = Asset::factory()->create();
    $pdu = PowerPdu::factory()->create(['asset_id' => $asset->id]);
    $panel = PowerPanel::factory()->create(['asset_id' => $asset->id]);

    $asset->delete();

    expect($pdu->fresh()->asset_id)->toBeNull()
        ->and($panel->fresh()->asset_id)->toBeNull();
});
