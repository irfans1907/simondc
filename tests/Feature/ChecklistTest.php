<?php

use App\Models\ChecklistCategory;
use App\Models\ChecklistDayNote;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSlotNote;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view checklist', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage checklist', 'guard_name' => 'web']);

    $this->seed(ChecklistSeeder::class);
});

function checklistUser(bool $manage = false): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->givePermissionTo($manage ? 'manage checklist' : 'view checklist');

    return $user;
}

function checklistEntryPayload(ChecklistCategory $category, array $entries, array $overrides = []): array
{
    return array_merge([
        'category_id' => $category->id,
        'entry_date' => '2026-11-07',
        'risk_level' => 'LOW',
        'note' => null,
        'entries' => $entries,
    ], $overrides);
}

test('the seeder creates the thirteen infrastructure categories', function () {
    expect(ChecklistCategory::query()->count())->toBe(13);
    expect(ChecklistCategory::query()->where('slug', 'pac-ac')->value('name'))->toBe('PAC & AC');
});

test('guests are redirected from the checklist overview', function () {
    $this->get(route('checklist.overview'))->assertRedirect(route('login'));
});

test('a user without checklist permissions cannot open the checklist pages', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('checklist.overview'))->assertForbidden();
    $this->get(route('checklist.dailycheck'))->assertForbidden();
    $this->get(route('checklist.monthlycheck'))->assertForbidden();
});

test('the module root redirects to the overview', function () {
    $user = checklistUser();

    $this->actingAs($user)
        ->get('/checklist')
        ->assertRedirect(route('checklist.overview'));
});

test('a user with the view permission sees the overview read-only', function () {
    $user = checklistUser();

    $this->actingAs($user);

    $this->get(route('checklist.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/checklist/overview', false)
            ->where('canManage', false)
            ->has('categories', 13)
            ->has('totals')
            ->has('recentNotes')
        );
});

test('the overview description follows the selected month', function () {
    $user = checklistUser();

    $this->actingAs($user);

    $this->get(route('checklist.overview', ['month' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/checklist/overview', false)
            ->where('month', '2026-09')
            ->where('monthName', 'September 2026')
        );
});

test('the daily page loads the visible categories and the selected date', function () {
    $user = checklistUser();

    $this->actingAs($user);

    $this->get(route('checklist.dailycheck', ['date' => '2026-11-07']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic/checklist/dailycheck', false)
            ->where('date', '2026-11-07')
            ->where('canManage', false)
            ->has('categories', 13)
            ->has('entries', 0)
            ->has('dayNotes', 0)
        );
});

test('an invalid date falls back to today', function () {
    $user = checklistUser();

    $this->actingAs($user);

    $this->get(route('checklist.dailycheck', ['date' => '2026-02-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', today()->toDateString())
        );
});

test('a user with only the view permission cannot store entries', function () {
    $user = checklistUser();
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $item->id, 'time_slot' => '09:00', 'value' => null, 'status' => 'OK', 'note' => null],
    ]))->assertForbidden();
});

test('storing entries records the values, the note, and the filling user', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'pac-ac')->firstOrFail();
    $numberItem = $category->items()->where('input_type', 'number')->firstOrFail();
    $statusItem = $category->items()->where('input_type', 'status')->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $numberItem->id, 'time_slot' => '09:00', 'value' => '24.5', 'status' => null, 'note' => null],
        ['item_id' => $statusItem->id, 'time_slot' => '14:00', 'value' => null, 'status' => 'OK', 'note' => null],
    ], [
        'risk_level' => 'MEDIUM',
        'note' => 'Suhu ruang naik',
    ]))->assertRedirect(route('checklist.dailycheck', [
        'date' => '2026-11-07',
        'category' => $category->id,
    ]));

    $this->assertDatabaseHas('checklist_entries', [
        'checklist_item_id' => $numberItem->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'value' => 24.5,
        'user_id' => $user->id,
        'source' => 'manual',
    ]);

    $this->assertDatabaseHas('checklist_entries', [
        'checklist_item_id' => $statusItem->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '14:00',
        'status' => 'OK',
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('checklist_day_notes', [
        'checklist_category_id' => $category->id,
        'risk_level' => 'MEDIUM',
        'note' => 'Suhu ruang naik',
        'user_id' => $user->id,
    ]);
});

test('submitting an empty cell clears the stored entry', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    ChecklistEntry::factory()->create([
        'checklist_item_id' => $item->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'value' => null,
        'status' => 'OK',
    ]);

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $item->id, 'time_slot' => '09:00', 'value' => null, 'status' => null, 'note' => null],
    ]))->assertRedirect();

    $this->assertDatabaseCount('checklist_entries', 0);
});

test('an item from another category is rejected', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $foreignItem = ChecklistCategory::query()
        ->where('slug', 'ruangan')
        ->firstOrFail()
        ->items()
        ->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $foreignItem->id, 'time_slot' => '09:00', 'value' => null, 'status' => 'OK', 'note' => null],
    ]))->assertSessionHasErrors('entries.0.item_id');
});

test('a time slot outside the category definition is rejected', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $item->id, 'time_slot' => '14:00', 'value' => null, 'status' => 'OK', 'note' => null],
    ]))->assertSessionHasErrors('entries.0.time_slot');
});

test('a numeric item requires a value', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'ruangan')->firstOrFail();
    $item = $category->items()->where('input_type', 'number')->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $item->id, 'time_slot' => '09:00', 'value' => null, 'status' => null, 'note' => 'Catatan saja'],
    ]))->assertSessionHasErrors('entries.0.value');
});

test('a status item requires a status', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $item->id, 'time_slot' => '09:00', 'value' => null, 'status' => null, 'note' => 'Catatan saja'],
    ]))->assertSessionHasErrors('entries.0.status');
});

test('the monthly export streams every category as one csv', function () {
    $user = checklistUser();
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    ChecklistEntry::factory()->create([
        'checklist_item_id' => $item->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'value' => null,
        'status' => 'OK',
    ]);

    ChecklistDayNote::factory()->create([
        'checklist_category_id' => $category->id,
        'entry_date' => '2026-11-07',
        'risk_level' => 'MEDIUM',
        'note' => 'Cek rutin',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('checklist.export', ['month' => '2026-11']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertDownload('checklist-infrastruktur-2026-11.csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Kategori,Section,Group,Item,Unit,Tanggal,Jam,Nilai,Status,"Risk Level",Keterangan')
        ->toContain('CCTV,-,CCTV,CAMERA,-,07/11/2026,09:00,,OK,MEDIUM,"Cek rutin"');
});

test('the daily page loads the risk level of each time slot', function () {
    $user = checklistUser();
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();

    ChecklistSlotNote::factory()->create([
        'checklist_category_id' => $category->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'risk_level' => 'HIGH',
        'note' => 'Suhu tinggi',
    ]);

    $this->actingAs($user);

    $this->get(route('checklist.dailycheck', [
        'date' => '2026-11-07',
        'category' => $category->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('slotNotes', 1)
            ->where('slotNotes.0.category_id', $category->id)
            ->where('slotNotes.0.time_slot', '09:00')
            ->where('slotNotes.0.risk_level', 'HIGH')
            ->where('slotNotes.0.note', 'Suhu tinggi')
        );
});

test('storing entries records the risk level of each time slot', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [
        ['item_id' => $item->id, 'time_slot' => '09:00', 'value' => null, 'status' => 'OK', 'note' => null],
    ], [
        'slot_notes' => [
            ['time_slot' => '09:00', 'risk_level' => 'HIGH', 'note' => 'Suhu tinggi'],
            ['time_slot' => '21:00', 'risk_level' => 'MEDIUM', 'note' => null],
        ],
    ]))->assertRedirect();

    $this->assertDatabaseHas('checklist_slot_notes', [
        'checklist_category_id' => $category->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'risk_level' => 'HIGH',
        'note' => 'Suhu tinggi',
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('checklist_slot_notes', [
        'checklist_category_id' => $category->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '21:00',
        'risk_level' => 'MEDIUM',
        'note' => null,
    ]);
});

test('a slot risk outside the category time slots is rejected', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();

    $this->actingAs($user);

    $this->post(route('checklist.entries.store'), checklistEntryPayload($category, [], [
        'slot_notes' => [
            ['time_slot' => '10:00', 'risk_level' => 'LOW', 'note' => null],
        ],
    ]))->assertSessionHasErrors('slot_notes.0.time_slot');
});

test('the monthly export uses the risk level of the matching time slot', function () {
    $user = checklistUser(manage: true);
    $category = ChecklistCategory::query()->where('slug', 'cctv')->firstOrFail();
    $item = $category->items()->firstOrFail();

    ChecklistEntry::factory()->create([
        'checklist_item_id' => $item->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'value' => null,
        'status' => 'OK',
    ]);

    ChecklistSlotNote::factory()->create([
        'checklist_category_id' => $category->id,
        'entry_date' => '2026-11-07',
        'time_slot' => '09:00',
        'risk_level' => 'HIGH',
        'note' => 'Suhu tinggi',
    ]);

    $this->actingAs($user);

    $content = $this
        ->get(route('checklist.export', ['month' => '2026-11']))
        ->streamedContent();

    expect($content)->toContain('CCTV,-,CCTV,CAMERA,-,07/11/2026,09:00,,OK,HIGH,"Suhu tinggi"');
});
