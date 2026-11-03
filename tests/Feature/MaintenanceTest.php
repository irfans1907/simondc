<?php

use App\Events\MaintenanceChanged;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\Troubleshoot;
use App\Models\User;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view asset management', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage asset management', 'guard_name' => 'web']);
});

/**
 * Build a maintenance payload for the given asset.
 */
function maintenancePayload(Asset $asset, array $overrides = []): array
{
    return array_merge([
        'asset_id' => $asset->id,
        'maintenance_date' => '2026-10-21',
        'status' => 'Scheduled',
        'result' => 'Normal',
    ], $overrides);
}

test('guests are redirected from the maintenance page', function () {
    $this->get(route('assets.maintenance'))->assertRedirect(route('login'));
});

test('a user without view or manage permission cannot view the maintenance page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('assets.maintenance'))->assertForbidden();
});

test('a user with only the view permission sees the maintenance page read-only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.maintenance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/assets/maintenance', false)
            ->where('canManage', false)
            ->has('assetOptions')
        );
});

test('a user without the manage permission cannot create a maintenance', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset))
        ->assertForbidden();
});

test('storing a maintenance requires the mandatory fields', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), [
        'asset_id' => '',
        'maintenance_date' => '',
        'status' => '',
        'result' => '',
    ])->assertSessionHasErrors(['asset_id', 'maintenance_date', 'status']);
});

test('storing a scheduled maintenance without a result is allowed', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'result' => '',
    ]))->assertRedirect(route('assets.maintenance'));

    $maintenance = Maintenance::query()->firstOrFail();

    expect($maintenance->result)->toBeNull();
    $this->assertDatabaseCount('troubleshoots', 0);
});

test('storing a done maintenance without a result is rejected', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'Done',
        'result' => '',
    ]))->assertSessionHasErrors('result');

    $this->assertDatabaseCount('maintenances', 0);
});

test('storing a maintenance broadcasts a change', function () {
    Event::fake([MaintenanceChanged::class]);

    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset));

    Event::assertDispatched(MaintenanceChanged::class);
});

test('the maintenance status must come from the allowed list', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'Pending',
    ]))->assertSessionHasErrors('status');
});

test('storing a normal maintenance records the row without a trouble ticket', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'Done',
        'end_date' => '2026-10-22',
    ]))->assertRedirect(route('assets.maintenance'));

    $this->assertDatabaseHas('maintenances', [
        'asset_id' => $asset->id,
        'maintenance_date' => '2026-10-21',
        'end_date' => '2026-10-22',
        'status' => 'Done',
        'result' => 'Normal',
    ]);
    $this->assertDatabaseCount('troubleshoots', 0);
});

test('a normal result lands the maintenance on done automatically', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Normal',
    ]))->assertRedirect(route('assets.maintenance'));

    $this->assertDatabaseHas('maintenances', [
        'asset_id' => $asset->id,
        'status' => 'Done',
        'result' => 'Normal',
    ]);
});

test('the end date cannot precede the start date', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'end_date' => '2026-10-20',
    ]))->assertSessionHasErrors('end_date');

    $this->assertDatabaseCount('maintenances', 0);
});

test('storing a maintenance with an evidence photo stores the file', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'evidence_photo' => UploadedFile::fake()->image('eviden.jpg'),
    ]))->assertRedirect(route('assets.maintenance'));

    $photo = Maintenance::query()->value('evidence_photo');

    expect($photo)->not->toBeNull();
    Storage::disk('public')->assertExists($photo);
});

test('storing a bermasalah maintenance opens a trouble ticket automatically', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Bermasalah',
        'end_date' => '2026-10-22',
    ]))->assertRedirect(route('assets.maintenance'));

    $maintenance = Maintenance::query()->firstOrFail();

    // A Bermasalah outcome keeps the record On Progress until its
    // ticket is closed, and the ticket inherits the work dates.
    expect($maintenance->status)->toBe('On Progress');

    $this->assertDatabaseHas('troubleshoots', [
        'maintenance_id' => $maintenance->id,
        'asset_id' => $asset->id,
        'troubleshoot_date' => '2026-10-22',
        'result' => 'Masih Bermasalah',
    ]);
});

test('an unknown asset cannot be used for a maintenance', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'asset_id' => 999999,
    ]))->assertSessionHasErrors('asset_id');
});

test('the maintenance result must come from the allowed list', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'result' => 'Rusak Berat',
    ]))->assertSessionHasErrors('result');
});

test('the maintenance result cannot change while its ticket is unresolved', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]));

    $maintenance = Maintenance::query()->firstOrFail();
    $maintenance->troubleshoot->update(['evidence_photo' => 'troubleshoots/eviden.jpg']);

    // Closing only happens on the Troubleshoot page — flipping the
    // result from here is rejected and nothing moves.
    $this->patch(route('assets.maintenance.update', $maintenance), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Normal',
    ]))->assertSessionHasErrors('result');

    $this->assertDatabaseHas('maintenances', [
        'id' => $maintenance->id,
        'result' => 'Bermasalah',
        'status' => 'On Progress',
    ]);
    $this->assertDatabaseHas('troubleshoots', [
        'maintenance_id' => $maintenance->id,
        'result' => 'Masih Bermasalah',
        'evidence_photo' => 'troubleshoots/eviden.jpg',
    ]);
    $this->assertDatabaseCount('troubleshoots', 1);
});

test('a cosmetic edit of a bermasalah maintenance leaves its ticket untouched', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]));

    $maintenance = Maintenance::query()->firstOrFail();

    $this->patch(route('assets.maintenance.update', $maintenance), maintenancePayload($asset, [
        'maintenance_date' => '2026-10-25',
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]))->assertRedirect(route('assets.maintenance'));

    $this->assertDatabaseHas('maintenances', [
        'id' => $maintenance->id,
        'maintenance_date' => '2026-10-25',
        'status' => 'On Progress',
    ]);
    $this->assertDatabaseHas('troubleshoots', [
        'maintenance_id' => $maintenance->id,
        'troubleshoot_date' => '2026-10-21',
        'result' => 'Masih Bermasalah',
    ]);
    $this->assertDatabaseCount('troubleshoots', 1);
});

test('editing a normal maintenance to bermasalah reopens its resolved ticket', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]));

    $maintenance = Maintenance::query()->firstOrFail();

    // The ticket gets closed on the Troubleshoot page, which flips this
    // record to result Normal + status Done.
    $maintenance->troubleshoot()->update([
        'result' => 'Normal',
        'started_date' => '2026-10-22',
        'closed_date' => '2026-10-23',
    ]);
    $maintenance->update(['result' => 'Normal', 'status' => Maintenance::STATUS_DONE]);

    // A relapse re-opens the same ticket row and clears its close date.
    $this->patch(route('assets.maintenance.update', $maintenance), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]))->assertRedirect(route('assets.maintenance'));

    $this->assertDatabaseHas('troubleshoots', [
        'maintenance_id' => $maintenance->id,
        'result' => 'Masih Bermasalah',
        'closed_date' => null,
    ]);
    $this->assertDatabaseHas('maintenances', [
        'id' => $maintenance->id,
        'status' => 'On Progress',
    ]);
    $this->assertDatabaseCount('troubleshoots', 1);
});

test('deleting a maintenance keeps its ticket as a manual entry', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();

    $this->actingAs($user);

    $this->post(route('assets.maintenance.store'), maintenancePayload($asset, [
        'status' => 'On Progress',
        'result' => 'Bermasalah',
    ]));

    $maintenance = Maintenance::query()->firstOrFail();

    $this->delete(route('assets.maintenance.destroy', $maintenance))
        ->assertRedirect(route('assets.maintenance'));

    $this->assertDatabaseCount('maintenances', 0);
    $this->assertDatabaseHas('troubleshoots', [
        'maintenance_id' => null,
        'asset_id' => $asset->id,
    ]);
});

test('a scheduled maintenance becomes on progress once its date has arrived', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_date' => today()->toDateString(),
        'status' => 'Scheduled',
        'result' => 'Normal',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.maintenance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('maintenances.data.0.status', 'On Progress')
        );
});

test('a scheduled maintenance with a future date stays scheduled', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_date' => today()->addDay()->toDateString(),
        'status' => 'Scheduled',
        'result' => 'Normal',
    ]);

    $this->actingAs($user);

    $this->get(route('assets.maintenance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('maintenances.data.0.status', 'Scheduled')
        );
});

test('the advance-status command moves due scheduled maintenances only', function () {
    $due = Maintenance::factory()->create([
        'maintenance_date' => today()->subDay()->toDateString(),
        'status' => 'Scheduled',
    ]);
    $future = Maintenance::factory()->create([
        'maintenance_date' => today()->addDay()->toDateString(),
        'status' => 'Scheduled',
    ]);
    $inProgress = Maintenance::factory()->create([
        'maintenance_date' => today()->subDay()->toDateString(),
        'status' => 'On Progress',
    ]);

    Artisan::call('maintenance:advance-status');

    $this->assertDatabaseHas('maintenances', ['id' => $due->id, 'status' => 'On Progress']);
    $this->assertDatabaseHas('maintenances', ['id' => $future->id, 'status' => 'Scheduled']);
    $this->assertDatabaseHas('maintenances', ['id' => $inProgress->id, 'status' => 'On Progress']);
});

test('searching maintenance by asset name matches the related asset', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $server = Asset::factory()->create(['name' => 'Server Alpha']);
    $switch = Asset::factory()->create(['name' => 'Switch Beta']);
    Maintenance::factory()->create(['asset_id' => $server->id]);
    Maintenance::factory()->create(['asset_id' => $switch->id]);

    $this->actingAs($user);

    $this->get(route('assets.maintenance', [
        'search' => 'Alpha',
        'search_column' => 'asset_name',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenances.data', 1)
            ->where('maintenances.data.0.asset.name', 'Server Alpha')
            ->where('filters.search_column', 'asset_name')
        );
});

test('searching maintenance by status matches the status column', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create();
    Maintenance::factory()->create(['asset_id' => $asset->id, 'status' => 'Done']);
    Maintenance::factory()->create(['asset_id' => $asset->id, 'status' => 'Scheduled']);

    $this->actingAs($user);

    $this->get(route('assets.maintenance', [
        'search' => 'Done',
        'search_column' => 'status',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenances.data', 1)
            ->where('maintenances.data.0.status', 'Done')
        );
});

test('an unknown maintenance search column falls back to searching all columns', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create(['name' => 'UPS Utama']);
    Maintenance::factory()->create(['asset_id' => $asset->id, 'result' => 'Normal']);

    $this->actingAs($user);

    $this->get(route('assets.maintenance', [
        'search' => 'UPS',
        'search_column' => 'DROP TABLE',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search_column', 'all')
            ->has('maintenances.data', 1)
        );
});

test('an asset with maintenance records cannot be deleted', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('manage asset management');
    $maintenance = Maintenance::factory()->create();

    $this->actingAs($user);

    $this->delete(route('assets.destroy', $maintenance->asset))
        ->assertSessionHas('inertia.flash_data');

    $this->assertDatabaseHas('assets', ['id' => $maintenance->asset_id]);
});

test('the maintenance export returns a csv download honoring the search filter', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $ups = Asset::factory()->create(['name' => 'UPS-A']);
    $chiller = Asset::factory()->create(['name' => 'Chiller-B']);
    Maintenance::factory()->create([
        'asset_id' => $ups->id,
        'maintenance_date' => '2026-10-20',
        'status' => Maintenance::STATUS_DONE,
        'result' => 'Normal',
    ]);
    Maintenance::factory()->create(['asset_id' => $chiller->id]);

    $this->actingAs($superadmin);

    $response = $this->get(route('assets.maintenance.export'));

    $response->assertOk()
        ->assertDownload('maintenances-'.now()->format('Y-m-d').'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('No,"Nama Perangkat","Tanggal Mulai","Tanggal Beres",Status,Hasil')
        ->toContain('UPS-A')
        ->toContain('Chiller-B');

    $filtered = $this->get(route('assets.maintenance.export', [
        'search' => 'UPS',
        'search_column' => 'asset_name',
    ]))->streamedContent();

    expect($filtered)->toContain('UPS-A')
        ->not->toContain('Chiller-B');
});

test('a user without the manage permission cannot export maintenance records', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    $this->get(route('assets.maintenance.export'))->assertForbidden();
});

test('the maintenance export shows the solved label for the trouble ticket', function () {
    $superadmin = superadminUser();
    $superadmin->givePermissionTo('manage asset management');
    $asset = Asset::factory()->create(['name' => 'UPS-A']);
    $maintenance = Maintenance::factory()->create(['asset_id' => $asset->id]);
    Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_id' => $maintenance->id,
        'result' => 'Normal',
    ]);

    $this->actingAs($superadmin);

    $csv = $this->get(route('assets.maintenance.export'))->streamedContent();

    expect($csv)->toContain('Solved');
});

test('the page renders when the vite dev server is not running', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo('view asset management');

    $this->actingAs($user);

    // Point the Vite singleton at a hot file that does not exist so the
    // request resolves entrypoints from the build manifest instead, the
    // same mode production runs in without a dev server.
    app(Vite::class)->useHotFile(sys_get_temp_dir().'/simondc-no-hot-file');

    try {
        $this->get(route('assets.maintenance'))->assertOk();
    } finally {
        app(Vite::class)->useHotFile(null);
    }
});
