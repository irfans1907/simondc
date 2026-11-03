---
paths:
  - 'resources/js/pages/dynamic/assets/maintenance/**'
  - 'app/Http/Controllers/MaintenanceController.php'
  - 'app/Console/Commands/AdvanceMaintenanceStatus.php'
  - 'app/Http/Requests/MaintenanceRequest.php'
  - 'app/Models/Maintenance.php'
  - 'database/migrations/*maintenances_table.php'
  - 'routes/console.php'
  - 'tests/Feature/MaintenanceTest.php'
---

# Maintenance

## Child of Asset Management: permission is borrowed, never new
Menu id 33 `/assets/maintenance` (parent 21 Asset Management) ships **no own permission** — routes reuse the parent's: GET `assets.maintenance` behind `permission:view-asset-management|manage-asset-management`, mutations behind `permission:manage-asset-management` (same shape as `/assets/panelpowerlist`). The controller passes `canManage`, which only hides the create/edit/delete buttons; a view-only user still sees the table. Permission names must match the DB menu wording exactly or Spatie throws `PermissionDoesNotExist` (500).

## The ticket is opened automatically; the dialog never shows it
`maintenances` (asset_id FK `restrictOnDelete`, `maintenance_date` = start date, nullable `end_date` = finish date validated `after_or_equal:maintenance_date`, `status`, `result` in `Maintenance::RESULTS = ['Normal','Bermasalah']`, `evidence_photo`/`document` nullable paths). A `Bermasalah` result creates a **Troubleshoot** row in the same transaction — `asset_id` inherited, `troubleshoot_date` inherited from `end_date ?? maintenance_date` (the gangguan date is when the maintenance work finished), result starts `Masih Bermasalah`, no files (evidence is completed later on the Troubleshoot page). The maintenance dialog has NO ticket fieldset — do not reintroduce one. 1 maintenance owns at most 1 ticket (`troubleshoots.maintenance_id` unique nullable).

## Status follows the result: Done is NEVER set by hand
`status` in `Maintenance::STATUSES = ['Scheduled','On Progress','Done']` (default `Scheduled`). Two mechanisms:
1. Scheduled→On Progress stays automatic when `maintenance_date <= today` — on-read `advanceDueStatuses()` at the top of `index()` AND the hourly `maintenance:advance-status` command (`AdvanceMaintenanceStatus`, `routes/console.php`; **prod Linux crontab `* * * * * php artisan schedule:run`**, Windows dev on-read is enough).
2. **`MaintenanceController::statusForResult()` forces the stage from the result on every store/update**: `Normal` → **Done**, `Bermasalah` → **On Progress** (until its ticket closes), `null` → the requested Scheduled/On Progress (a `Done` request without a result is rejected by `MaintenanceRequest::after()`). The dialog mirrors this: the Status select only offers Scheduled/On Progress and is `disabled` the moment a result is picked; the hidden input always submits `statusForResult(result, status)` (same helper name on the frontend, keep both in sync).

## Result is nullable, drives the status, and is locked while the ticket is open
`maintenances.result` is **nullable** — a maintenance still Scheduled/On Progress has no outcome yet. The Hasil select is `disabled` while the status is Scheduled ("Diisi setelah pengerjaan dimulai") and **while its ticket is Unresolved** ("Tutup tiket di Troubleshoot") — the server enforces the latter in `MaintenanceRequest::after()`: changing the result of a record whose ticket is `Masih Bermasalah` fails validation on `result`. Closing the ticket on the Troubleshoot page is what turns the record Normal + Done (one-way sync, see below). The table shows `-` for a null result. A null result is "not Bermasalah", so it never opens a ticket.

## Real-time: broadcast, don't poll
Every mutation in `MaintenanceController` **and** `TroubleshootController` (store/update/destroy) dispatches `MaintenanceChanged` (public channel `maintenance`, event `.maintenance.changed` — same shape as `FilesChanged`). Both pages listen with `useEchoPublic('maintenance', '.maintenance.changed', () => router.reload({ only: [...] }))`, so a ticket opened from Maintenance appears on the Troubleshoot tab instantly and vice versa. This is the project-wide convention (Files, Users, Roles, Menus, Permissions, Activity Log) — **do not add `usePoll` or refetch intervals here**; if real-time stops working, check that the Reverb server is running (`php artisan reverb:start`), the app degrades gracefully without it. UI-side Echo wiring is not covered by Pest; the dispatch side is (`Event::fake([MaintenanceChanged::class])` + `assertDispatched`, see `ActivityLogTest` pattern).

## validated() omits absent file keys — always `?? null`
Laravel's `validated()` only contains keys that were present in the request. A form submitted **without** a file input has no `evidence_photo` key at all, so `$data['evidence_photo']?->store(...)` throws `Undefined array key`. Every file access is written `($data['evidence_photo'] ?? null)?->store('maintenances', 'public')` (same for `document`; the Troubleshoot module follows the same rule for `troubleshoots/`). Photos `image|max:4096`, documents `max:10240`, both on the `public` disk.

## Lifecycle rules you must not silently change
- **Closing a ticket only ever happens on the Troubleshoot page** (`TroubleshootController::update` with result `Normal`): it demands the work dates and flips the parent maintenance to `result = Normal` + `status = Done` in one transaction. `MaintenanceController::update()` no longer resolves anything — while a ticket is Unresolved the request cannot change the result at all (guard in `MaintenanceRequest::after()`); the ticket row and its evidence files are **never deleted automatically**: the ticket *is* the asset's trouble history.
- A maintenance flip **Normal → Bermasalah re-opens** its resolved ticket (same row back to `Masih Bermasalah`, `closed_date` reset to NULL, no duplicate). A ticket can only be Solved at that point because the close sync always keeps the pair aligned; a still-Bermasalah cosmetic edit (dates/evidence) never touches the ticket.
- **Deleting the maintenance keeps the ticket**: `maintenance_id` FK is `nullOnDelete`, so it survives as a manual entry (it owns asset + dates). Only the maintenance's own files are removed on destroy.
- The only way a ticket disappears is an **explicit Delete on the Troubleshoot page** (manage permission).
- `AssetController::destroy` refuses (flash toast, `back()`, never a 500) when the asset has any maintenance **or** troubleshoot row.

## Frontend shape
`dynamic/assets/maintenance/index.tsx`: dialog field order is `Nama Perangkat | Tanggal Mulai / Tanggal Beres | Status | Hasil / Foto Eviden | Upload Dokumen`. The Update dialog passes `lockAsset` — the asset Select is `disabled` there (the hidden input still submits the existing id); Create keeps it open. Search columns `all | asset_name | status | result` (asset name via `whereHas`, unknown column falls back to `all`). Dates are NOT a text-search column: filtering by date uses the `DateFilter` popover (Calendar `captionLayout="dropdown"`, copied from Activity Log) with a `date=YYYY-MM-DD` query param honoured by `whereDate()` on `maintenance_date`; the `date` param must be carried by EVERY param-sending control (search debounce, column select, per-page, export link). Table order puts Tanggal Beres after Tanggal Mulai, then Status, Hasil; `statusBadgeClassFor` slate/amber/green (Scheduled/On Progress/Done), `resultBadgeClassFor` green/rose/slate. Tanggal cells render `formatDate()` = `dd/mm/yyyy` (en-GB, parse with `${value}T00:00:00` so date-only strings never shift a day) — same convention as Activity Log/Files; a null `end_date` shows `-`. The action column is **Update (renamed from Edit, same dialog + endpoint) + Delete** — there is NO separate Edit button anymore. The Tiket Gangguan badge shows the ticket's **Solved/Unresolved** label (`ticketResultLabel`, mirrors `Troubleshoot::RESULT_LABELS`) while the Hasil column keeps the maintenance's own Normal/Bermasalah result.

## Export CSV
`GET assets/maintenance/export` (name `assets.maintenance.export`) sits in the `permission:manage-asset-management` group — same as `rack/export`, so only `canManage` sees the green Export button (`MaintenanceController.exportMethod.url({ query: { search, search_column, date } })`). `export()` reuses `filteredQuery()` (active search + date filters apply), streams BOM + `fputcsv(escape: '\\')`, and its header list stays in sync with `tableHeaders` minus `Aksi` (`Tanggal Mulai,Tanggal Beres` included; the ticket column shows the Solved/Unresolved label). Date values are written raw `Y-m-d` (`end_date` falls back to `-` when null — project convention: exports carry raw values).
