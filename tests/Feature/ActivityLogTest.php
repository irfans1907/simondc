<?php

use App\Events\ActivityLogChanged;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\File;
use App\Models\Maintenance;
use App\Models\PanelMcb;
use App\Models\Rack;
use App\Models\RackType;
use App\Models\Troubleshoot;
use App\Models\User;
use App\Models\VlanTagRequest;
use App\Models\VlanTagRequestSwitch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('recording activity broadcasts an activity log changed event', function () {
    Event::fake([ActivityLogChanged::class]);

    $user = superadminUser();
    $this->actingAs($user);

    $this->post(route('roles.store'), [
        'name' => 'editor',
        'permissions' => [],
    ])->assertRedirect(route('roles.index'));

    Event::assertDispatched(ActivityLogChanged::class);
});

test('guests are redirected from the activity log page', function () {
    $this->get(route('activity-log.index'))->assertRedirect(route('login'));
});

test('a non-superadmin user is forbidden from the activity log page', function () {
    $user = adminUser();
    $this->actingAs($user);

    $this->get(route('activity-log.index'))->assertForbidden();
});

test('a regular user is forbidden from the activity log page', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $this->get(route('activity-log.index'))->assertForbidden();
});

test('a superadmin can visit the activity log page', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $this->get(route('activity-log.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('activity-log/index')->has('activities'));
});

test('the activity log page lists activity entries', function () {
    $user = superadminUser();
    $this->actingAs($user);

    Activity::query()->delete();

    Activity::create([
        'description' => "created user \"{$user->name}\"",
        'log_name' => 'user',
        'event' => 'created',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);

    $this->get(route('activity-log.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.description', "created user \"{$user->name}\"")
            ->where('activities.data.0.log_name', 'user')
            ->where('activities.data.0.event', 'created')
        );
});

test('the activity log search finds entries by user name', function () {
    $user = superadminUser();
    $this->actingAs($user);

    Activity::query()->delete();

    $causer = User::withoutEvents(fn () => User::factory()->create([
        'role' => 'user',
        'name' => 'Jane Smith',
    ]));

    Activity::create([
        'description' => 'updated user "Jane Smith"',
        'log_name' => 'user',
        'event' => 'updated',
        'causer_type' => User::class,
        'causer_id' => $causer->id,
    ]);

    Activity::create([
        'description' => 'created role "editor"',
        'log_name' => 'role',
        'event' => 'created',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);

    $this->get(route('activity-log.index', ['search' => 'Jane Smith']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.event', 'updated')
        );
});

test('the activity log can sort entries by time ascending', function () {
    $user = superadminUser();
    $this->actingAs($user);

    Activity::query()->delete();

    Activity::create([
        'description' => 'first entry',
        'log_name' => 'user',
        'event' => 'created',
        'created_at' => now()->subDay(),
    ]);

    Activity::create([
        'description' => 'second entry',
        'log_name' => 'user',
        'event' => 'created',
        'created_at' => now(),
    ]);

    $this->get(route('activity-log.index', ['sort' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities.data', 2)
            ->where('activities.data.0.description', 'first entry')
            ->where('activities.data.1.description', 'second entry')
            ->where('filters.sort', 'asc')
        );
});

test('the activity log can filter entries by date', function () {
    $user = superadminUser();
    $this->actingAs($user);

    Activity::query()->delete();

    Activity::create([
        'description' => 'today entry',
        'log_name' => 'user',
        'event' => 'created',
        'created_at' => now(),
    ]);

    Activity::create([
        'description' => 'yesterday entry',
        'log_name' => 'user',
        'event' => 'created',
        'created_at' => now()->subDay(),
    ]);

    $this->get(route('activity-log.index', ['date' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.description', 'today entry')
            ->where('filters.date', now()->toDateString())
        );
});

test('creating a role records an activity log entry', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $this->post(route('roles.store'), [
        'name' => 'editor',
        'permissions' => [],
    ])->assertRedirect(route('roles.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created role "editor"',
        'log_name' => 'role',
        'event' => 'created',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('creating a user records an activity log entry', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $created = User::factory()->create(['role' => 'user', 'name' => 'Jane Doe']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created user "Jane Doe"',
        'log_name' => 'user',
        'event' => 'created',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);

    $this->assertDatabaseHas('users', ['id' => $created->id]);
});

test('updating a user records an activity log entry', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $target = User::factory()->create(['role' => 'user', 'name' => 'Original Name']);

    $this->patch(route('users.update', $target), [
        'name' => 'Updated Name',
        'email' => $target->email,
        'role' => 'user',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated user "Updated Name": name from Original Name to Updated Name',
        'log_name' => 'user',
        'event' => 'updated',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('updating a user email and role records both changes in the description', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $target = User::factory()->create(['role' => 'user', 'name' => 'Jane Doe']);

    $this->patch(route('users.update', $target), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'role' => 'admin',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated user "Jane Doe": email from '.$target->email.' to jane@example.com, role from user to admin',
        'log_name' => 'user',
        'event' => 'updated',
    ]);
});

test('login records an activity log entry', function () {
    $user = superadminUser();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => '"'.$user->name.'" logged in',
        'log_name' => 'auth',
        'event' => 'login',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('logout does not record a user updated event', function () {
    $user = superadminUser();
    $this->actingAs($user);

    Activity::query()->delete();

    $this->post(route('logout'))->assertRedirect('/');

    $this->assertDatabaseHas('activity_log', [
        'description' => '"'.$user->name.'" logged out',
        'log_name' => 'auth',
        'event' => 'logout',
    ]);

    $this->assertDatabaseMissing('activity_log', [
        'log_name' => 'user',
        'event' => 'updated',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('renaming a file records a from-to description', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $file = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'old.pdf',
    ]);

    $this->patch(route('files.update', $file), ['name' => 'new.pdf'])
        ->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'renamed "old.pdf" to "new.pdf"',
        'log_name' => 'file',
        'event' => 'renamed',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('moving a file records a from-to description', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $folder = File::factory()->folder()->create([
        'user_id' => $user->id,
        'name' => 'Docs',
    ]);
    $file = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'report.pdf',
    ]);

    $this->post(route('files.move'), [
        'ids' => [$file->id],
        'folder_id' => $folder->id,
    ])->assertRedirect(route('files.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'moved "report.pdf" from "root" to "Docs"',
        'log_name' => 'file',
        'event' => 'moved',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('downloading a file records an activity log entry', function () {
    Storage::fake('local');

    $user = superadminUser();
    $this->actingAs($user);

    $file = File::factory()->create([
        'user_id' => $user->id,
        'name' => 'report.pdf',
        'path' => 'files/report.pdf',
    ]);

    Storage::disk('local')->put('files/report.pdf', 'contents');

    $this->get(route('files.download', $file))->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'downloaded file "report.pdf"',
        'log_name' => 'file',
        'event' => 'downloaded',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('bulk download records an activity log entry', function () {
    Storage::fake('local');

    $user = superadminUser();
    $this->actingAs($user);

    $first = File::factory()->create(['user_id' => $user->id, 'path' => 'files/one.txt']);
    $second = File::factory()->create(['user_id' => $user->id, 'path' => 'files/two.txt']);

    Storage::disk('local')->put('files/one.txt', 'one');
    Storage::disk('local')->put('files/two.txt', 'two');

    $this->get(route('files.bulk-download', ['ids' => [$first->id, $second->id]]))->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'downloaded 2 items',
        'log_name' => 'file',
        'event' => 'downloaded',
        'causer_type' => User::class,
        'causer_id' => $user->id,
    ]);
});

test('a newly registered user automatically receives the user role without activity conflict', function () {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $this->post('/register', [
        'name' => 'New Member',
        'email' => 'member@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'member@example.com',
        'role' => 'user',
    ]);
});

test('creating an asset records its name and category', function () {
    $this->actingAs(superadminUser());

    $category = Category::factory()->create(['name' => 'Panel Power']);
    Asset::factory()->create(['name' => 'PDU-A', 'category_id' => $category->id]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created asset "PDU-A" (category "Panel Power")',
        'log_name' => 'asset',
        'event' => 'created',
    ]);
});

test('updating an asset lists every changed field with its old and new value', function () {
    $this->actingAs(superadminUser());

    $category = Category::factory()->create(['name' => 'Panel Power']);
    $newCategory = Category::factory()->create(['name' => 'UPS Frame']);
    $asset = Asset::factory()->create([
        'name' => 'PDU-A',
        'brand' => '-',
        'floor' => '1',
        'category_id' => $category->id,
        'in_checklist' => true,
    ]);

    $asset->update([
        'brand' => 'ABB',
        'floor' => '2',
        'category_id' => $newCategory->id,
        'in_checklist' => false,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated asset "PDU-A": brand from - to ABB, category_id from Panel Power to UPS Frame, '
            .'floor from 1 to 2, in_checklist from Yes to No',
        'log_name' => 'asset',
        'event' => 'updated',
    ]);
});

test('deleting an asset records its name and category', function () {
    $this->actingAs(superadminUser());

    $category = Category::factory()->create(['name' => 'Panel Power']);
    $asset = Asset::factory()->create(['name' => 'PDU-A', 'category_id' => $category->id]);

    $asset->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted asset "PDU-A" (category "Panel Power")',
        'log_name' => 'asset',
        'event' => 'deleted',
    ]);
});

test('updating a category lists its changed fields', function () {
    $this->actingAs(superadminUser());

    $category = Category::factory()->create(['name' => 'UPS']);

    $category->update(['name' => 'UPS Frame']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated category "UPS Frame": name from UPS to UPS Frame',
        'log_name' => 'category',
        'event' => 'updated',
    ]);
});

test('updating a category resolves its parent id to the parent name', function () {
    $this->actingAs(superadminUser());

    $parent = Category::factory()->create(['name' => 'UPS']);
    $category = Category::factory()->create(['name' => 'Battery']);

    $category->update(['parent_id' => $parent->id]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated category "Battery" on "UPS": parent_id from none to UPS',
        'log_name' => 'category',
        'event' => 'updated',
    ]);
});

test('creating a top-level category omits the child counts', function () {
    $this->actingAs(superadminUser());

    Category::factory()->create(['name' => 'Modul 2']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created category "Modul 2"',
        'log_name' => 'category',
        'event' => 'created',
    ]);
});

test('creating a sub category names its parent', function () {
    $this->actingAs(superadminUser());

    $parent = Category::factory()->create(['name' => 'Modul 2']);
    Category::factory()->create(['name' => 'Modul 2a', 'parent_id' => $parent->id]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created category "Modul 2a" on "Modul 2"',
        'log_name' => 'category',
        'event' => 'created',
    ]);
});

test('updating a sub category names its parent', function () {
    $this->actingAs(superadminUser());

    $parent = Category::factory()->create(['name' => 'Modul 2']);
    $category = Category::factory()->create(['name' => 'Modul 2a', 'parent_id' => $parent->id]);

    $category->update(['name' => 'Modul 2b']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated category "Modul 2b" on "Modul 2": name from Modul 2a to Modul 2b',
        'log_name' => 'category',
        'event' => 'updated',
    ]);
});

test('deleting a sub category names its parent', function () {
    $this->actingAs(superadminUser());

    $parent = Category::factory()->create(['name' => 'Modul 2']);
    $category = Category::factory()->create(['name' => 'Modul 2a', 'parent_id' => $parent->id]);

    $category->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted category "Modul 2a" on "Modul 2"',
        'log_name' => 'category',
        'event' => 'deleted',
    ]);
});

test('saving a maintenance records only its own log entry', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    Activity::query()->delete();

    Maintenance::factory()->create(['asset_id' => $asset->id]);

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->log_name)->toBe('maintenance');
});

test('saving a trouble ticket records only its own log entry', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    Activity::query()->delete();

    $ticket = Troubleshoot::factory()->create(['asset_id' => $asset->id]);

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->log_name)->toBe('troubleshoot')
        ->and($ticket->fresh()->ticket_number)->toBe(sprintf('TKT-%05d', $ticket->id));
});

test('updating and deleting a trouble ticket record only their own log entries', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'result' => 'Normal',
    ]);

    Activity::query()->delete();

    $ticket->update(['result' => 'Masih Bermasalah']);
    $ticket->delete();

    expect(Activity::count())->toBe(2)
        ->and(Activity::pluck('log_name')->all())->toBe(['troubleshoot', 'troubleshoot']);
});

test('creating a panel mcb records its number and parent asset', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    PanelMcb::factory()->create(['asset_id' => $asset->id, 'mcb_number' => 'MCB-01']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created MCB "MCB-01" of asset "PDU-A"',
        'log_name' => 'panel_mcb',
        'event' => 'created',
    ]);
});

test('updating a panel mcb lists its changed fields', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    $mcb = PanelMcb::factory()->create(['asset_id' => $asset->id, 'mcb_number' => 'MCB-01']);

    $mcb->update(['mcb_number' => 'MCB-02']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated MCB "MCB-02" of asset "PDU-A": mcb_number from MCB-01 to MCB-02',
        'log_name' => 'panel_mcb',
        'event' => 'updated',
    ]);
});

test('creating a maintenance records its asset and date', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'maintenance_date' => '2026-10-24',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created maintenance for asset "PDU-A" (2026-10-24)',
        'log_name' => 'maintenance',
        'event' => 'created',
    ]);
});

test('updating a maintenance lists its changed fields with none for empty values', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    $maintenance = Maintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => Maintenance::STATUS_SCHEDULED,
        'result' => null,
    ]);

    $maintenance->update([
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'result' => 'Normal',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated maintenance for asset "PDU-A": result from none to Normal, '
            .'status from Scheduled to On Progress',
        'log_name' => 'maintenance',
        'event' => 'updated',
    ]);
});

test('creating a trouble ticket records its number and asset', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    $ticket = Troubleshoot::factory()->create(['asset_id' => $asset->id]);

    $this->assertDatabaseHas('activity_log', [
        'description' => sprintf('created trouble ticket TKT-%05d for asset "PDU-A"', $ticket->id),
        'log_name' => 'troubleshoot',
        'event' => 'created',
    ]);
});

test('updating a trouble ticket lists its changed fields', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    $ticket = Troubleshoot::factory()->create([
        'asset_id' => $asset->id,
        'result' => 'Normal',
        'closed_date' => null,
    ]);

    $ticket->update([
        'result' => 'Masih Bermasalah',
        'closed_date' => '2026-10-30',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => sprintf(
            'updated trouble ticket TKT-%05d: closed_date from none to 2026-10-30, result from Normal to Masih Bermasalah',
            $ticket->id,
        ),
        'log_name' => 'troubleshoot',
        'event' => 'updated',
    ]);
});

test('deleting a trouble ticket records its number and asset', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'PDU-A']);
    $ticket = Troubleshoot::factory()->create(['asset_id' => $asset->id]);

    $ticket->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => sprintf('deleted trouble ticket TKT-%05d for asset "PDU-A"', $ticket->id),
        'log_name' => 'troubleshoot',
        'event' => 'deleted',
    ]);
});

test('creating a rack records its code, type, floor and room', function () {
    $this->actingAs(superadminUser());

    Rack::factory()->create([
        'rack_code' => 'RACK-001',
        'type' => 'U Rack',
        'capacity' => '1U',
        'floor' => '1',
        'room' => 'NOC',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created rack "RACK-001" (type "U Rack", floor 1, room NOC)',
        'log_name' => 'rack',
        'event' => 'created',
    ]);
});

test('updating a rack lists every changed field with its old and new value', function () {
    $this->actingAs(superadminUser());

    $rack = Rack::factory()->create([
        'rack_code' => 'RACK-001',
        'brand' => 'APC',
        'floor' => '1',
    ]);

    $rack->update(['brand' => 'Rittal', 'floor' => '2']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated rack "RACK-001": brand from APC to Rittal, floor from 1 to 2',
        'log_name' => 'rack',
        'event' => 'updated',
    ]);
});

test('deleting a rack records its code, type, floor and room', function () {
    $this->actingAs(superadminUser());

    $rack = Rack::factory()->create([
        'rack_code' => 'RACK-001',
        'type' => 'U Rack',
        'capacity' => '1U',
        'floor' => '1',
        'room' => 'NOC',
    ]);

    $rack->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted rack "RACK-001" (type "U Rack", floor 1, room NOC)',
        'log_name' => 'rack',
        'event' => 'deleted',
    ]);
});

test('creating a rack type records its name and capacity', function () {
    $this->actingAs(superadminUser());

    RackType::factory()->create(['name' => 'U Rack', 'capacity' => '1U']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created rack type "U Rack" (capacity 1U)',
        'log_name' => 'rack_type',
        'event' => 'created',
    ]);
});

test('updating a rack type lists its changed fields', function () {
    $this->actingAs(superadminUser());

    $type = RackType::factory()->create(['name' => 'U Rack', 'capacity' => '1U']);

    $type->update(['name' => 'U Rack Pro', 'capacity' => '42U']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated rack type "U Rack Pro": capacity from 1U to 42U, name from U Rack to U Rack Pro',
        'log_name' => 'rack_type',
        'event' => 'updated',
    ]);
});

test('deleting a rack type records its name and capacity', function () {
    $this->actingAs(superadminUser());

    $type = RackType::factory()->create(['name' => 'U Rack', 'capacity' => '1U']);

    $type->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted rack type "U Rack" (capacity 1U)',
        'log_name' => 'rack_type',
        'event' => 'deleted',
    ]);
});

test('creating a customer records the rack status change in the activity log', function () {
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);

    $user = superadminUser();
    $user->givePermissionTo('manage customer list');

    $rack = Rack::factory()->create([
        'rack_code' => 'RACK-001',
        'status' => Rack::DEFAULT_STATUS,
    ]);

    $this->actingAs($user);

    $this->post(route('customer.store'), [
        'rack_id' => $rack->id,
        'city' => 'Jakarta',
        'location' => 'neuCentrIX Cikarang',
        'customer_name' => 'PT Contoh Nusantara',
    ])->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated rack "RACK-001": status from Ready For Sale to Occupied',
        'log_name' => 'rack',
        'event' => 'updated',
    ]);
});

test('deleting a customer records the rack status change in the activity log', function () {
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);

    $user = superadminUser();
    $user->givePermissionTo('manage customer list');

    $rack = Rack::factory()->create([
        'rack_code' => 'RACK-001',
        'status' => Rack::OCCUPIED_STATUS,
    ]);

    $customer = Customer::factory()->create(['rack_id' => $rack->id]);

    $this->actingAs($user);

    $this->delete(route('customer.destroy', $customer))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated rack "RACK-001": status from Occupied to Ready For Sale',
        'log_name' => 'rack',
        'event' => 'updated',
    ]);
});

test('renaming a rack type records the cascade on every affected rack', function () {
    Permission::firstOrCreate(['name' => 'manage rack management', 'guard_name' => 'web']);

    $user = superadminUser();
    $user->givePermissionTo('manage rack management');

    $type = RackType::factory()->create(['name' => 'U Rack', 'capacity' => '1U']);
    $rack = Rack::factory()->create([
        'rack_code' => 'RACK-001',
        'type' => 'U Rack',
        'capacity' => '1U',
    ]);

    $this->actingAs($user);

    $this->patch(route('rack.racklist.update', $type), [
        'name' => 'U Rack Pro',
        'capacity' => '42U',
    ])->assertRedirect(route('rack.racklist'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated rack "RACK-001": capacity from 1U to 42U, type from U Rack to U Rack Pro',
        'log_name' => 'rack',
        'event' => 'updated',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated rack type "U Rack Pro": capacity from 1U to 42U, name from U Rack to U Rack Pro',
        'log_name' => 'rack_type',
        'event' => 'updated',
    ]);
});

test('creating a customer records its name', function () {
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);

    $user = superadminUser();
    $user->givePermissionTo('manage customer list');

    $rack = Rack::factory()->create(['rack_code' => 'RACK-001']);

    $this->actingAs($user);

    $this->post(route('customer.store'), [
        'rack_id' => $rack->id,
        'city' => 'Jakarta',
        'location' => 'neuCentrIX Cikarang',
        'customer_name' => 'PT Maju Jaya',
    ])->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created customer "PT Maju Jaya"',
        'log_name' => 'customer',
        'event' => 'created',
    ]);
});

test('updating a customer lists every changed field with its old and new value', function () {
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);

    $user = superadminUser();
    $user->givePermissionTo('manage customer list');

    $customer = Customer::factory()->create([
        'customer_name' => 'PT Maju Jaya',
        'city' => 'Jakarta',
        'activation_date' => '2026-01-15',
    ]);

    $this->actingAs($user);

    $customer->update(['city' => 'Bandung', 'activation_date' => '2026-02-20']);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated customer "PT Maju Jaya": activation_date from 2026-01-15 to 2026-02-20, city from Jakarta to Bandung',
        'log_name' => 'customer',
        'event' => 'updated',
    ]);
});

test('deleting a customer records its name', function () {
    Permission::firstOrCreate(['name' => 'manage customer list', 'guard_name' => 'web']);

    $user = superadminUser();
    $user->givePermissionTo('manage customer list');

    $customer = Customer::factory()->create([
        'customer_name' => 'PT Maju Jaya',
    ]);

    $this->actingAs($user);

    $this->delete(route('customer.destroy', $customer))
        ->assertRedirect(route('customer.index'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted customer "PT Maju Jaya"',
        'log_name' => 'customer',
        'event' => 'deleted',
    ]);
});

test('creating a vlan tag request records its order number and customer', function () {
    $this->actingAs(superadminUser());

    $customer = Customer::factory()->create(['customer_name' => 'PT Maju Jaya']);

    VlanTagRequest::factory()->create([
        'order_number' => 'ORD-10001',
        'customer_id' => $customer->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created VLAN tag request for order number "ORD-10001" for customer "PT Maju Jaya"',
        'log_name' => 'vlan_tag_request',
        'event' => 'created',
    ]);
});

test('updating a vlan tag request lists every changed field with its old and new value', function () {
    $this->actingAs(superadminUser());

    $customer1 = Customer::factory()->create(['customer_name' => 'PT Maju Jaya']);
    $customer2 = Customer::factory()->create(['customer_name' => 'PT Abadi Jaya']);

    $request = VlanTagRequest::factory()->create([
        'order_number' => 'ORD-10001',
        'sid' => 'SID-10001',
        'service_name' => 'Internet Dedicated',
        'vlan' => '100',
        'mode' => 'TRUNK',
        'customer_id' => $customer1->id,
    ]);

    $request->update([
        'customer_id' => $customer2->id,
        'mode' => 'ACCESS',
        'vlan' => '200',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated VLAN tag request for order number "ORD-10001": customer from PT Maju Jaya to PT Abadi Jaya, mode from TRUNK to ACCESS, vlan from 100 to 200',
        'log_name' => 'vlan_tag_request',
        'event' => 'updated',
    ]);
});

test('deleting a vlan tag request records its order number and customer', function () {
    $this->actingAs(superadminUser());

    $customer = Customer::factory()->create(['customer_name' => 'PT Maju Jaya']);

    $request = VlanTagRequest::factory()->create([
        'order_number' => 'ORD-10001',
        'customer_id' => $customer->id,
    ]);

    $request->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted VLAN tag request for order number "ORD-10001" for customer "PT Maju Jaya"',
        'log_name' => 'vlan_tag_request',
        'event' => 'deleted',
    ]);
});

test('creating a vlan tag request switch records its asset and port', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'Cisco']);

    VlanTagRequestSwitch::factory()->create([
        'asset_id' => $asset->id,
        'port_number' => 'Gi0/24',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'created VLAN tag request switch on asset "Cisco" port Gi0/24',
        'log_name' => 'vlan_tag_request',
        'event' => 'created',
    ]);
});

test('updating a vlan tag request switch lists every changed field', function () {
    $this->actingAs(superadminUser());

    $asset1 = Asset::factory()->create(['name' => 'Cisco']);
    $asset2 = Asset::factory()->create(['name' => 'Arista']);

    $switch = VlanTagRequestSwitch::factory()->create([
        'asset_id' => $asset1->id,
        'port_number' => 'Gi0/1',
    ]);

    $switch->update([
        'asset_id' => $asset2->id,
        'port_number' => 'Gi0/24',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated VLAN tag request switch on asset "Arista": asset from Cisco to Arista, port from Gi0/1 to Gi0/24',
        'log_name' => 'vlan_tag_request',
        'event' => 'updated',
    ]);
});

test('deleting a vlan tag request switch records its asset and port', function () {
    $this->actingAs(superadminUser());

    $asset = Asset::factory()->create(['name' => 'Cisco']);

    $switch = VlanTagRequestSwitch::factory()->create([
        'asset_id' => $asset->id,
        'port_number' => 'Gi0/24',
    ]);

    $switch->delete();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'deleted VLAN tag request switch on asset "Cisco" port Gi0/24',
        'log_name' => 'vlan_tag_request',
        'event' => 'deleted',
    ]);
});

test('updating a vlan tag request via the controller updates the switch instead of recreating it', function () {
    $this->actingAs(superadminUser());

    Category::firstOrCreate(['name' => 'Network', 'parent_id' => null]);
    $network = Category::where('name', 'Network')->first();
    $asset = Asset::factory()->create(['category_id' => $network->id, 'name' => 'Cisco']);
    $customer = Customer::factory()->create();

    $request = VlanTagRequest::factory()->create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-10001',
        'sid' => 'SID-10001',
        'service_name' => 'Colocation',
        'vlan' => '300',
        'mode' => 'ACCESS',
    ]);
    VlanTagRequestSwitch::factory()->create([
        'vlan_tag_request_id' => $request->id,
        'asset_id' => $asset->id,
        'port_number' => 'Gi0/1',
    ]);

    $this->patch(route('customer.vlanreq.update', $request), [
        'customer_id' => $customer->id,
        'order_number' => 'ORD-10001',
        'sid' => 'SID-10001',
        'service_name' => 'Colocation',
        'vlan' => '300',
        'mode' => 'ACCESS',
        'switches' => [
            ['asset_id' => $asset->id, 'port_number' => 'Gi0/24'],
        ],
    ])->assertRedirect(route('customer.vlanreq'));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'updated VLAN tag request switch on asset "Cisco": port from Gi0/1 to Gi0/24',
        'log_name' => 'vlan_tag_request',
        'event' => 'updated',
    ]);
});
