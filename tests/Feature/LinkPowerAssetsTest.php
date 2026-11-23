<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PanelMcb;
use App\Models\PowerLoadRow;
use App\Models\PowerPanel;
use App\Models\PowerPdu;
use App\Models\Rack;

function panelPowerAsset(array $attributes = []): Asset
{
    $category = Category::firstOrCreate(['name' => 'Panel Power', 'parent_id' => null]);

    return Asset::factory()->create(array_merge([
        'category_id' => $category->id,
    ], $attributes));
}

test('the command links pdus panels and load rows to their records', function () {
    $pduAsset = panelPowerAsset(['name' => 'PDU.2-A.1']);
    $panelAsset = panelPowerAsset(['name' => 'MDP L1.A']);
    $rack = Rack::factory()->create(['rack_code' => 'NLBL2026000']);
    $mcb = PanelMcb::factory()->create([
        'asset_id' => $panelAsset->id,
        'mcb_number' => 'MCB-01',
    ]);
    $customer = Customer::factory()->create([
        'rack_id' => $rack->id,
        'customer_name' => 'TELIN - FACEBOOK',
        'panel_asset_id' => $panelAsset->id,
        'panel_mcb_id' => $mcb->id,
    ]);

    $pdu = PowerPdu::factory()->create(['code' => 'PDU.2-A.1', 'name' => 'PDU 2 A 1']);
    $panel = PowerPanel::factory()->create(['name' => 'MDP L1.A']);
    $row = PowerLoadRow::factory()->create([
        'power_pdu_id' => $pdu->id,
        'rack' => 'NLBL2026000',
        'customer' => 'Telin Facebook',
        'mcb' => 'MCB-01',
    ]);

    $this->artisan('power:link-assets')->assertSuccessful();

    expect($pdu->fresh()->asset_id)->toBe($pduAsset->id)
        ->and($panel->fresh()->asset_id)->toBe($panelAsset->id)
        ->and($row->fresh()->rack_id)->toBe($rack->id)
        ->and($row->fresh()->customer_id)->toBe($customer->id)
        ->and($row->fresh()->panel_mcb_id)->toBe($mcb->id);
});

test('the dry run option reports matches without writing them', function () {
    $asset = panelPowerAsset(['name' => 'PDU.2-A.1']);
    $pdu = PowerPdu::factory()->create(['code' => 'PDU.2-A.1']);

    $this->artisan('power:link-assets', ['--dry-run' => true])->assertSuccessful();

    expect($asset->exists)->toBeTrue()
        ->and($pdu->fresh()->asset_id)->toBeNull();
});

test('duplicate names are reported as ambiguous and left unlinked', function () {
    panelPowerAsset(['name' => 'PDU.2-A.1']);
    panelPowerAsset(['name' => 'PDU.2-A.1']);
    $pdu = PowerPdu::factory()->create(['code' => 'PDU.2-A.1']);

    $this->artisan('power:link-assets')->assertSuccessful();

    expect($pdu->fresh()->asset_id)->toBeNull();
});

test('a numeric mcb falls back to the trailing number of the slot label', function () {
    $panelAsset = panelPowerAsset(['name' => 'MDP L1.A']);
    $mcb = PanelMcb::factory()->create([
        'asset_id' => $panelAsset->id,
        'mcb_number' => 'MCB-07',
    ]);
    $rack = Rack::factory()->create(['rack_code' => 'NLBL2026000']);
    $customer = Customer::factory()->create([
        'rack_id' => $rack->id,
        'customer_name' => 'TELIN - FACEBOOK',
        'panel_asset_id' => $panelAsset->id,
    ]);
    $pdu = PowerPdu::factory()->create();
    $row = PowerLoadRow::factory()->create([
        'power_pdu_id' => $pdu->id,
        'rack' => 'NLBL2026000',
        'customer' => 'Telin Facebook',
        'mcb' => '7',
    ]);

    $this->artisan('power:link-assets')->assertSuccessful();

    expect($row->fresh()->panel_mcb_id)->toBe($mcb->id);
});
