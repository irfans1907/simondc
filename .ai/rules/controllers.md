---
paths:
  - app/Http/Controllers/FileController.php
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/PageController.php
  - app/Http/Controllers/MenuController.php
  - app/Http/Controllers/ChecklistController.php
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
