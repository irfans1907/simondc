<?php

use App\Events\MaintenanceChanged;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\Troubleshoot;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

/**
 * Build a trouble ticket payload for the given asset.
 */
function troubleshootPayload(Asset $asset, array $overrides = []): array
{
    return array_merge([
        'asset_id' => $asset->id,
        'troubleshoot_date' => '2026-10-21',
        'result' => 'Masih Bermasalah',
    ], $overrides);
}

test('guests are redirected from the troubleshoot page', function () {
    $this->get(route('assets.troubleshoot'))->assertRedirect(route('login'));
});

test('a user without view or manage permission cannot view the troubleshoot page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot'))->assertForbidden();
});

test('a user with only the view permission sees the troubleshoot page read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/troubleshoot', false)
            ->where('canManage', false)
        );
});

test('a user without the manage permission cannot create a ticket', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset))
        ->assertForbidden();
});

test('storing a trouble ticket requires the mandatory fields', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), [
        'asset_id' => '',
        'troubleshoot_date' => '',
        'result' => '',
    ])->assertSessionHasErrors(['asset_id', 'troubleshoot_date', 'result']);
});

test('the trouble ticket result must come from the allowed list', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset, [
        'result' => 'Selesai Total',
    ]))->assertSessionHasErrors('result');
});

test('storing a manual ticket keeps the maintenance link empty', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset))
        ->assertRedirect(route('assets.troubleshoot'));

    $this->assertDatabaseHas('troubleshoots', [
        'maintenance_id' => null,
        'asset_id' => $asset->id,
        'troubleshoot_date' => '2026-10-21',
        'result' => 'Masih Bermasalah',
    ]);
});

test('creating a ticket stamps its auto-generated ticket number', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset))
        ->assertRedirect(route('assets.troubleshoot'));

    $ticket = Troubleshoot::firstOrFail();

    expect($ticket->ticket_number)
        ->toBe('TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT));
});

test('the list can search tickets by their ticket number', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset));
    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset));

    $this->get(route('assets.troubleshoot', [
        'search' => 'TKT-00002',
        'search_column' => 'ticket_number',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('troubleshoots.data', 1)
            ->where('troubleshoots.data.0.ticket_number', 'TKT-00002')
        );
});

test('storing a manual ticket broadcasts a change', function () {
    Event::fake([MaintenanceChanged::class]);

    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.troubleshoot.store'), troubleshootPayload($asset));

    Event::assertDispatched(MaintenanceChanged::class);
});

test('updating a trouble ticket changes its result and dates', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'result' => 'Masih Bermasalah',
    ]);

    $this->actingAs($user);

    $this->patch(route('assets.troubleshoot.update', $ticket), troubleshootPayload($asset, [
        'troubleshoot_date' => '2026-10-25',
        'result' => 'Normal',
        'started_date' => '2026-10-24',
        'closed_date' => '2026-10-25',
    ]))->assertRedirect(route('assets.troubleshoot'));

    $this->assertDatabaseHas('troubleshoots', [
        'id' => $ticket->id,
        'troubleshoot_date' => '2026-10-25',
        'result' => 'Normal',
        'started_date' => '2026-10-24',
        'closed_date' => '2026-10-25',
    ]);
});

test('closing a solved ticket requires the work dates', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'result' => 'Masih Bermasalah',
    ]);

    $this->actingAs($user);

    $this->patch(route('assets.troubleshoot.update', $ticket), troubleshootPayload($asset, [
        'result' => 'Normal',
    ]))->assertSessionHasErrors(['started_date', 'closed_date']);

    $this->assertDatabaseHas('troubleshoots', [
        'id' => $ticket->id,
        'result' => 'Masih Bermasalah',
    ]);
});

test('a close date cannot precede the start date', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'result' => 'Masih Bermasalah',
    ]);

    $this->actingAs($user);

    $this->patch(route('assets.troubleshoot.update', $ticket), troubleshootPayload($asset, [
        'result' => 'Normal',
        'started_date' => '2026-10-24',
        'closed_date' => '2026-10-23',
    ]))->assertSessionHasErrors('closed_date');
});

test('closing a solved ticket turns its maintenance result to normal and done', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    $maintenance = Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]);
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_id' => $maintenance->id,
        'result' => 'Masih Bermasalah',
    ]);

    $this->actingAs($user);

    $this->patch(route('assets.troubleshoot.update', $ticket), troubleshootPayload($asset, [
        'result' => 'Normal',
        'started_date' => '2026-10-22',
        'closed_date' => '2026-10-23',
    ]))->assertRedirect(route('assets.troubleshoot'));

    $this->assertDatabaseHas('troubleshoots', [
        'id' => $ticket->id,
        'result' => 'Normal',
        'closed_date' => '2026-10-23',
    ]);
    $this->assertDatabaseHas('maintenances', [
        'id' => $maintenance->id,
        'result' => 'Normal',
        'status' => 'Done',
    ]);
});

test('switching a solved ticket back to unresolved clears the close date', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'result' => 'Normal',
        'started_date' => '2026-10-22',
        'closed_date' => '2026-10-23',
    ]);

    $this->actingAs($user);

    $this->patch(route('assets.troubleshoot.update', $ticket), troubleshootPayload($asset, [
        'result' => 'Masih Bermasalah',
        'started_date' => '2026-10-22',
    ]))->assertRedirect(route('assets.troubleshoot'));

    $this->assertDatabaseHas('troubleshoots', [
        'id' => $ticket->id,
        'result' => 'Masih Bermasalah',
        'started_date' => '2026-10-22',
        'closed_date' => null,
    ]);
});

test('deleting a trouble ticket removes the record', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $ticket = Troubleshoot::factory()->create();

    $this->actingAs($user);

    $this->delete(route('assets.troubleshoot.destroy', $ticket))
        ->assertRedirect(route('assets.troubleshoot'));

    $this->assertDatabaseMissing('troubleshoots', ['id' => $ticket->id]);
});

test('searching tickets by asset name matches the related asset', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $server = Asset::factory()->create(['name' => 'Server Gamma']);
    $switch = Asset::factory()->create(['name' => 'Switch Delta']);
    Troubleshoot::factory()->create(['asset_id' => $server->id]);
    Troubleshoot::factory()->create(['asset_id' => $switch->id]);

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot', [
        'search' => 'Gamma',
        'search_column' => 'asset_name',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('troubleshoots.data', 1)
            ->where('troubleshoots.data.0.asset.name', 'Server Gamma')
            ->where('filters.search_column', 'asset_name')
        );
});

test('an asset with a trouble ticket cannot be deleted', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $ticket = Troubleshoot::factory()->create();

    $this->actingAs($user);

    $this->delete(route('assets.destroy', $ticket->asset))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('assets', ['id' => $ticket->asset_id]);
});

test('the troubleshoot export returns a csv download honoring the date filter', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $ups = Asset::factory()->create(['name' => 'UPS-A']);
    $chiller = Asset::factory()->create(['name' => 'Chiller-B']);
    Troubleshoot::factory()->create(['asset_id' => $ups->id, 'troubleshoot_date' => '2026-10-21']);
    Troubleshoot::factory()->create(['asset_id' => $chiller->id, 'troubleshoot_date' => '2026-10-22']);

    $this->actingAs($superadmin);

    $response = $this->get(route('assets.troubleshoot.export'));

    $response->assertOk()
        ->assertDownload('troubleshoots-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('No,"No Tiket","Nama Perangkat",Tanggal,Mulai,Selesai,Hasil')
        ->toContain('UPS-A')
        ->toContain('Chiller-B');

    $filtered = $this->get(route('assets.troubleshoot.export', [
        'date' => '2026-10-21',
    ]))->streamedContent();

    expect($filtered)->toContain('UPS-A')
        ->not->toContain('Chiller-B');
});

test('a user without the manage permission cannot export trouble tickets', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot.export'))->assertForbidden();
});

test('the troubleshoot page returns null history without the history_asset param', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/troubleshoot', false)
            ->where('history', null)
        );
});

test('the troubleshoot page returns the per-asset ticket and maintenance history', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $asset = Asset::factory()->create(['name' => 'UPS-A']);
    $other = Asset::factory()->create();
    $ticket = Troubleshoot::factory()->create(['asset_id' => $asset->id, 'troubleshoot_date' => '2026-10-21']);
    Troubleshoot::factory()->create(['asset_id' => $other->id]);
    Maintenance::factory()->create(['asset_id' => $asset->id, 'maintenance_date' => '2026-10-20']);
    Maintenance::factory()->create(['asset_id' => $other->id]);

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot', ['history_asset' => $asset->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/troubleshoot', false)
            ->where('history.asset.name', 'UPS-A')
            ->count('history.tickets', 1)
            ->where('history.tickets.0.id', $ticket->id)
            ->count('history.maintenances', 1)
        );
});

test('an unknown history_asset id returns a null history', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot', ['history_asset' => 999999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('history', null));
});

test('guests are redirected from the history export', function () {
    $this->get(route('assets.troubleshoot.history.export', ['history_asset' => 1]))
        ->assertRedirect(route('login'));
});

test('a user without the manage permission cannot export the history', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot.history.export', ['history_asset' => $asset->id]))
        ->assertForbidden();
});

test('the history export returns a two-section csv for the asset', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create(['name' => 'UPS-A']);
    $maintenance = Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_date' => '2026-10-20',
    ]);
    Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_id' => $maintenance->id,
        'troubleshoot_date' => '2026-10-21',
        'result' => 'Masih Bermasalah',
    ]);
    Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_id' => null,
        'troubleshoot_date' => '2026-10-22',
        'result' => 'Normal',
    ]);

    $this->actingAs($superadmin);

    $response = $this->get(route('assets.troubleshoot.history.export', [
        'history_asset' => $asset->id,
    ]));

    $response->assertOk()
        ->assertDownload('riwayat-gangguan-ups-a-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Riwayat Gangguan - UPS-A')
        ->toContain('No,"No Tiket",Tanggal,Mulai,Selesai,Hasil,Asal,"Foto Eviden",Dokumen')
        ->toContain('Maintenance 2026-10-20')
        ->toContain('Manual')
        ->toContain('Riwayat Maintenance')
        ->toContain('No,"Tanggal Mulai","Tanggal Beres",Status,Hasil');
});

test('the history export returns a 404 for an unknown asset', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');

    $this->actingAs($superadmin);

    $this->get(route('assets.troubleshoot.history.export', ['history_asset' => 999999]))
        ->assertNotFound();
});

test('the history panel opens for an asset that never had a ticket', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $asset = Asset::factory()->create(['name' => 'Switch-Baru']);

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot', ['history_asset' => $asset->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/troubleshoot', false)
            ->where('history.asset.name', 'Switch-Baru')
            ->count('history.tickets', 0)
            ->count('history.maintenances', 0)
        );
});

test('searching solved or unresolved matches the stored results', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    Troubleshoot::factory()->create(['result' => 'Normal']);
    Troubleshoot::factory()->create(['result' => 'Masih Bermasalah']);

    $this->actingAs($user);

    $this->get(route('assets.troubleshoot', ['search' => 'Solved', 'search_column' => 'result']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('troubleshoots.data', 1)
            ->where('troubleshoots.data.0.result', 'Normal')
        );

    $this->get(route('assets.troubleshoot', ['search' => 'unresolved', 'search_column' => 'result']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('troubleshoots.data', 1)
            ->where('troubleshoots.data.0.result', 'Masih Bermasalah')
        );
});

test('the troubleshoot export shows the solved label', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create(['name' => 'UPS-A']);
    Troubleshoot::factory()->create(['asset_id' => $asset->id, 'result' => 'Normal']);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.troubleshoot.export'))->streamedContent();

    expect($csv)->toContain('Solved')
        ->not->toContain('Normal');
});

test('the history export shows the solved label for its tickets', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create(['name' => 'UPS-A']);
    $maintenance = Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => Maintenance::STATUS_DONE,
        'result' => 'Normal',
    ]);
    Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_id' => $maintenance->id,
        'result' => 'Normal',
    ]);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.troubleshoot.history.export', [
        'history_asset' => $asset->id,
    ]))->streamedContent();

    expect($csv)->toContain('Solved');
});
