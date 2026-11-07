<?php

use App\Models\PowerLoadReading;
use App\Models\PowerPanel;
use App\Models\PowerPanelReading;
use App\Models\PowerPdu;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\PowerConsumptionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate([
        'name' => 'view checklist',
        'guard_name' => 'web',
    ]);
    Permission::firstOrCreate([
        'name' => 'manage checklist',
        'guard_name' => 'web',
    ]);

    $this->seed(ChecklistSeeder::class);
    $this->seed(PowerConsumptionSeeder::class);
});

function monthlyCheckUser(bool $manage = false): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo($manage
        ? 'manage checklist'
        : 'view checklist');

    return $user;
}

test('guests are redirected from the monthly check page', function () {
    $this->get(route('checklist.monthlycheck'))->assertRedirect(route('login'));
});

test('a user without checklist permissions cannot open the monthly check page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('checklist.monthlycheck'))->assertForbidden();
    $this->post(route('checklist.monthlycheck.load'))->assertForbidden();
    $this->post(route('checklist.monthlycheck.panel'))->assertForbidden();
    $this->get(route('checklist.monthlycheck.export'))->assertForbidden();
});

test('the monthly page loads the power load tab by default', function () {
    $user = monthlyCheckUser();

    $this->actingAs($user);

    $this->get(route('checklist.monthlycheck'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/checklist/monthlycheck', false)
            ->where('tab', 'lantai2')
            ->where('year', now()->year)
            ->where('canManage', false)
        );

    expect(PowerPdu::where('floor', 2)->count())->toBe(8);
    expect(PowerPdu::where('floor', 4)->count())->toBe(12);
});

test('the pue tab loads panels and summary', function () {
    $user = monthlyCheckUser();

    $this->actingAs($user);

    $this->get(route('checklist.monthlycheck').'?tab=pue&year=2026&month=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/checklist/monthlycheck', false)
            ->where('tab', 'pue')
            ->where('period', '2026-10')
            ->where('canManage', false)
        );

    expect(PowerPanel::count())->toBe(55);
});

test('a manage user can save monthly ampere readings for a PDU', function () {
    $user = monthlyCheckUser(manage: true);

    $pdu = PowerPdu::query()->where('floor', 2)->firstOrFail();
    $row = $pdu->rows()->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.monthlycheck.load'), [
        'pdu_id' => $pdu->id,
        'readings' => [
            [
                'row_id' => $row->id,
                'period' => '2026-11',
                'ampere' => 12.5,
            ],
            [
                'row_id' => $row->id,
                'period' => '2026-12',
                'ampere' => null,
            ],
        ],
    ])->assertRedirect();

    expect((float) PowerLoadReading::query()
        ->where('power_load_row_id', $row->id)
        ->where('period', '2026-11')
        ->value('ampere'))->toBe(12.5);

    expect(PowerLoadReading::query()
        ->where('power_load_row_id', $row->id)
        ->where('period', '2026-12')
        ->exists())->toBeFalse();
});

test('a manage user can save monthly panel readings', function () {
    $user = monthlyCheckUser(manage: true);

    $panel = PowerPanel::query()->where('panel_group', 'MDP')->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.monthlycheck.panel'), [
        'panel_id' => $panel->id,
        'period' => '2026-10',
        'amp_r' => 100,
        'amp_s' => 110,
        'amp_t' => 105,
        'note' => 'Catatan uji',
    ])->assertRedirect();

    $reading = PowerPanelReading::query()
        ->where('power_panel_id', $panel->id)
        ->where('period', '2026-10')
        ->first();

    expect($reading->amp_r)->toBe('100.00');
    expect($reading->amp_s)->toBe('110.00');
    expect($reading->amp_t)->toBe('105.00');
    expect($reading->note)->toBe('Catatan uji');
});

test('clearing all panel readings deletes the record', function () {
    $user = monthlyCheckUser(manage: true);

    $panel = PowerPanel::query()->where('panel_group', 'MDP')->firstOrFail();

    PowerPanelReading::factory()->create([
        'power_panel_id' => $panel->id,
        'period' => '2026-10',
        'amp_r' => 50,
        'amp_s' => 50,
        'amp_t' => 50,
    ]);

    $this->actingAs($user);

    $this->post(route('checklist.monthlycheck.panel'), [
        'panel_id' => $panel->id,
        'period' => '2026-10',
        'amp_r' => null,
        'amp_s' => null,
        'amp_t' => null,
        'note' => '',
    ])->assertRedirect();

    expect(PowerPanelReading::query()
        ->where('power_panel_id', $panel->id)
        ->where('period', '2026-10')
        ->exists())->toBeFalse();
});

test('the monthly export returns CSV for the power load tab', function () {
    $user = monthlyCheckUser(manage: true);

    $this->actingAs($user);

    $response = $this->get(route('checklist.monthlycheck.export').'?tab=lantai2&year=2026&month=11')
        ->assertOk()
        ->assertHeaderContains('Content-Disposition', 'filename=power-consumption-lantai-2.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('No')
        ->toContain('Customer')
        ->toContain('Rack')
        ->toContain('PDU')
        ->toContain('Jan')
        ->toContain('Des');
});

test('the monthly export returns CSV for the PUE tab', function () {
    $user = monthlyCheckUser(manage: true);

    $this->actingAs($user);

    $response = $this->get(route('checklist.monthlycheck.export').'?tab=pue&year=2026&month=10')
        ->assertOk()
        ->assertHeaderContains('Content-Disposition', 'filename=power-consumption-pue-2026-10.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('No')
        ->toContain('Group')
        ->toContain('Nama Perangkat')
        ->toContain('kW')
        ->toContain('kVA');
});

test('store load validates the period format', function () {
    $user = monthlyCheckUser(manage: true);

    $pdu = PowerPdu::query()->where('floor', 2)->firstOrFail();
    $row = $pdu->rows()->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.monthlycheck.load'), [
        'pdu_id' => $pdu->id,
        'readings' => [
            [
                'row_id' => $row->id,
                'period' => 'not-a-month',
                'ampere' => 12.5,
            ],
        ],
    ])->assertSessionHasErrors('readings.0.period');
});
