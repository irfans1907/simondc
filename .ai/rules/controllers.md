---
paths:
  - app/Http/Controllers/FileController.php
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/PageController.php
  - app/Http/Controllers/MenuController.php
  - app/Http/Controllers/ChecklistController.php
  - app/Http/Controllers/AssetSettingsController.php
  - app/Http/Controllers/AssetListController.php
---

# Controllers

## Files are strictly per-user with recursive deletion
Every File belongs to one user; all reads/writes must scope by user_id (foreign folders → 404 on index/store, 403 on update/destroy/download). Deleting a folder must recursively delete disk files of all descendants before the DB delete. Folder name uniqueness is scoped within the same parent (Rule::unique ignores self on update).

## ZIP downloads need php_zip + artisan serve restart
Folder/bulk downloads use ZipArchive. Requires the zip PHP extension enabled (uncomment extension=zip in C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.ini). WARNING: the app runs on `php artisan serve` on port 8000 (not Apache), and the php -S process keeps its config from startup — a Laragon restart does NOT stop it. After changing php.ini, kill the listener on :8000 and restart php -S with WorkingDirectory = project\public (router server.php uses getcwd()).

## Empty ZipArchive writes no file - always add entry
On Windows, ZipArchive::close() does NOT create the file when zero entries were added (addToZip on an empty folder). readfile() in the streamDownload closure then fails with "No such file or directory". Always ensure at least one entry: if $zip->numFiles === 0, call $zip->addEmptyDir(rtrim($zipName, '.zip')) before close().

## files.move is a DB-only parent_id update
Moving files/folders only updates parent_id in the DB — physical paths stay flat under storage/app/private/files/{user_id} and never change. Guard against moving a folder into itself/descendants via descendantIds(). The move dialog + drag&drop both POST to files.move with ids[] and an empty folder_id meaning root.

## Module index routes use view|manage guard; mutations manage-only
New module (e.g. Customer) permission wiring, mirroring assets/rack/category:
- routes/web.php: `GET {module}` (index) goes in `permission:view-{module}|manage-{module}` group (EnsurePermission supports `|` = any-of; prevents view-only users getting 403 on the list page and on Overview-children redirects).
- Export + store/patch/delete stay in `permission:manage-{module}` group only.
- Controller `index()` passes `'canManage' => $request->user()->hasPermissionTo('manage {module}')`.
- Frontend page in resources/js/pages/dynamic/** accepts `canManage` and hides Export/Tambah/Edit/Delete (incl. action column) behind `{canManage && ...}`.
- Permission names derive from parent menu name: 'view ' / 'manage ' + strtolower(name) with dashes->spaces.

## showChild fallback chain renders child page files, never crashes parents
showChild order: (1) overview child + Route::has('{parent}.index') + no dedicated overview route -> redirect to that index; (2) file resources/js/pages/dynamic/{parent}/{child}(.tsx or /index.tsx) exists -> render 'dynamic/{parent}/{child}' with props menu=PARENT menu, activeChild, canManage (never data props); (3) parent has index route -> redirect there; (4) else render 'dynamic/{parent}' with activeChild (monitoring tabs). Component paths derive from the stored menu URL, not the request. Catch-all routes use [a-zA-Z] constraints so uppercase menu URLs reach showChild instead of a 405 from assets/{asset}/rack/{rack} PATCH-DELETE wildcards. Real routes (assets/overview, rack/overview, assets/category) are registered before the catch-all and always win.

## Menu tree stays 1:1 with URLs and dynamic page files
Every child menu URL /parent/child must have resources/js/pages/dynamic/{parent}/{child}/index.tsx (guard test enforces it). Overview children use /parent/overview URLs. Where a child shares the parent's content, the file is a one-line wrapper `export { default } from '../index'` (monitoring, assets/overview, rack/overview) so props and .layout carry over. generatePageFile creates the file on menus.store with no component params (template must stay eslint-clean). Seeder children are keyed [name, parent_id] because multiple Overview rows exist; DB menu URL/name edits must run BEFORE re-seeding or updateOrCreate creates duplicate rows.

## childMenuProps() is the single source of menu/activeChild props
Base Controller::childMenuProps(?Menu $parent, ?Menu $child) returns the shared menu props contract (menu={id,name,group,url,icon}, activeChild={id,name,url}) or [] when a row is missing. PageController::showChild spreads it with canManage; AssetController::overview, RackController::overview and AssetCategoryController::index look up their child row via Menu::where('url', '/'.$request->path()) then spread childMenuProps($childMenu?->parent, $childMenu). Do not inline the array elsewhere — breadcrumb layouts depend on this exact shape. Extra 2 queries per render are accepted; menu rows may be absent in tests (RefreshDatabase does not seed menus — create them inline if asserting these props).

## generatePageFile emits the shared breadcrumb layout contract
MenuController::generatePageFile picks a template by SEGMENT COUNT (route semantics): 2+ segments = child template with the full chain `showChildCrumbs(props) ?? menuBreadcrumbs(props.menus, '<menu url via var_export>') ?? [{ title: props.menu.name, href: props.menu.url }]` (imports menuBreadcrumbs/showChildCrumbs + type MenuRow, layout props menus?/menu{name,url}/activeChild?); single segment = parent template with single crumb `href: props.menu.url` (never '#' — every breadcrumb crumb must be a clickable link). Placeholder __MENU_URL__ is replaced with var_export($menu->url, true). file_exists guard means EXISTING page files are never regenerated — hand-fix old files if the contract changes. Tests that trigger menus.store with a new URL must delete the generated resources/js/pages/dynamic/<slug> files afterwards (see MenuManagementTest try/finally).

## Checklist module: real routes before catch-all; empty cells delete entries
The infrastructure checklist has 13 categories from config/checklist.php. Real routes are registered before the /{parent}/{child} catch-all; /checklist redirects to checklist.overview. Daily save posts the full item x slot matrix and an entry with null value/status/note deletes its row so the form can clear a cell. Entries store source=manual plus user_id; checklist_items.asset_id is the future scan-page hook. Monthly export is CSV only (no PhpSpreadsheet).

## Asset Settings collapses same-name UPS assets
Assets in the UPS category subtree that share a name (case-insensitive, trimmed) are shown as one settings row; every other asset keeps its own row. The first member drives the displayed flags. PATCH assets/settings/bulk receives asset_ids[] and applies checklist/maintenance changes to all members. The QR dialog lists a QR for every member. Search and pagination run on grouped rows, not raw assets.

## Asset List split sheets paginate each table separately
AssetListController returns `tables` (null for single-table sheets) containing `ups.groups` and `battery.groups`. UPS groups paginate with page name `ups_page` via AssetSheetHelper::search(); each UPS group is `{ key, rows }` where rows share the values of `tables.ups.identity` (name, brand, capacity) so those columns render once with rowSpan across all frames, while the frontend sub-groups the rows by `tables.ups.shared` (frame number/year/SN) for the frame rowSpan. Battery groups use a manually built LengthAwarePaginator with page name `battery_page` where each group is the parent UPS (shared name/brand/capacity) plus its `rows`. Search for battery groups goes through groupMatchesSearch() so the parent's shared values match too. `upsOptions` feeds the parent select and is deduplicated by identity (name, brand, capacity) because every UPS module row is its own asset; the lowest id represents the UPS. The edit form normalizes a stored parent id that is no longer listed back to the representative option using the battery's copied name/brand/capacity. `assetPresets` feeds the Nama combobox (search + create) and autofills brand/capacity plus the frame year/SN, and `assets` is null on split sheets. The battery table reuses the same `per_page` and hides the per-page selector in the UI.

## Grouped sheets paginate by group, bulk create per camera row
When a sheet has row_group, AssetListController::groupedPaginator() groups assets by the identity values and paginates groups (page name `{sheet}_page`) so rowSpan never splits across pages; the frontend receives `groupedTable`. The create form for multi_row sheets posts to assets.store-bulk (AssetController::storeBulk with AssetBulkRequest), which creates one asset per non-empty camera row inside a DB transaction, copies the shared device fields, maps `catatan` to `notes`, and still creates one asset when every camera row is empty. Edit stays single-asset per row.

## Preset autofill copies configured identity columns only, first non-empty value
assetPresets() builds `values` from AssetSheetHelper::autofill(), which uses an explicit `autofill` list or falls back to `row_group.identity` (CCTV/EMS/FAP), skipping computed columns, storage name/notes and multi_row keys. Per-row device fields (alias_perangkat, kapasitas_hd, tahun_manufaktur) are intentionally not copied, because they can differ between the cameras of one device. Each value is the first non-empty value across the name group, so a blank `type` on the first row does not leave the form's Type empty.

## Update assets one model at a time so checklist items sync
Mass updates (Asset::whereIn(...)->update()) skip the AssetChecklistObserver, so generated checklist items are never materialised. Update each Asset model (or run SyncAssetChecklistAction) after bulk changes such as Asset Settings.

## Asset scan page shows the real asset checklist
AssetScanController resolves checklist items with scope='asset' and group = asset name (one set per UPS group) and sends today's entries plus the existing day note. Saving from the scan page posts 'redirect' starting with /assets/scan so the user returns to the scan page; daily check keeps its own redirect.

## Unassigned FAP tabs show zero items, not hidden
ChecklistController::categoriesWithItems() keeps every active category visible, but category-scoped FAP categories with no assigned asset report empty items and items_count 0, so Daily Check and Overview show 0/0 instead of template rows. Assignment is detected from assets.checklist_category_id + in_checklist.

## Checklist risk levels are stored per time slot
checklist_slot_notes holds one risk_level per category/date/time_slot. storeEntries upserts slot_notes and also writes the worst slot risk to checklist_day_notes so overview totals keep working. Daily Check and the scan checklist card render a Risk Level row with one Select per slot; export prefers slot risk and falls back to the day note.

## CCTV camera channels are assets in the Kamera category
A DVR/NVR device is one asset that owns the checklist row and QR sticker. Each filled camera row becomes its own asset in the `Kamera` category (config asset_list_sheets.cctv.camera_category) linked through parent_asset_id, named after alias_kamera (fallback camera_no, then device name), with in_checklist=false and no capacity. Creating a device with an existing identity (name, category, brand, ip, serial, model) updates that device and merges HDD capacity instead of inserting a duplicate; `cctv:merge-duplicate-devices` and `cctv:merge-capacity` clean legacy data. Asset List renders two tables (tables.device + tables.camera); the device table collapses duplicate identities into one row. Edit dialogs are split: device edit shows device columns only, camera edit shows camera columns + parent (pass multiRow to AssetFields and showCameraRows=false). Never call SyncAssetChecklistAction::syncCctv for camera assets.

## Create with identical fields updates the asset
AssetController::store compares every submitted base + detail field (same category and name) and updates the existing asset instead of inserting a duplicate; a differing field creates a new asset. CCTV devices keep their six-field identity with merged HDD capacity, and camera creates upsert on a full field match. Capacity is entered one slot per line in the UI and stored as '; ' via AssetSheetHelper::normalizeCapacityValue; displays split it back into lines.
