---
paths:
  - 'resources/js/pages/dynamic/assets/panelpowerlist/**'
  - 'app/Http/Controllers/PanelPowerController.php'
  - 'app/Http/Controllers/AssetController.php'
  - 'app/Models/PanelMcb.php'
  - 'app/Http/Controllers/Controller.php'
  - 'tests/Feature/PanelPowerTest.php'
---

# Panel Power

## Panels ARE assets; this page only manages their MCB slots
The PDU panel master data lives in Asset Management (category `Panel Power`, id 25, + any descendant category — `categoryIdsByName('Panel Power')`). `/assets/panelpowerlist` (`PanelPowerController::index`, no pagination/search, ordered by name) only lists those assets and manages the child rows in `panel_mcbs` (asset_id FK `cascadeOnDelete`, `unique(asset_id, mcb_number)` — the same mcb_number IS allowed on a different panel). Editing/renaming a panel or its lantai/ruang/kapasitas happens on the Asset Management overview page, never here; renaming an MCB is an in-place update (`updateMcb`).

## Category recursion is shared, not copied
`Controller::categoryIdsByName(string $name)` (base controller) returns the ids of every category whose name matches exactly plus all descendants (mirrors `AssetController::matchingCategoryIds`). It is used by `CustomerController::switchCategoryIds()` (`'Switch'`) and `PanelPowerController::panelPowerAssets()`/`isPanelPowerAsset()` (`'Panel Power'`). Do NOT copy the recursion into another controller — extend the base helper. `CustomerController` no longer imports `Category`.

## MCB routes are scoped twice
`storeMcb`/`updateMcb`/`destroyMcb` `abort_unless($this->isPanelPowerAsset($asset), 404)` AND (update/destroy) `abort_unless($panelMcb->asset_id === $asset->id, 404)` — the asset must be in the Panel Power tree and the MCB must belong to THAT asset, otherwise 404 (never trust the URL pair). Permissions reuse the parent menu's: `view/manage asset management`; the controller ships `canManage` which hides the "Kelola MCB" buttons (read-only view users still see the table). Page + menu name is "Panel Power List" (child menu id 32, `/assets/panelpowerlist`, order 2).

## Batch MCB: numbers continue, never collide
`panel-mcbs.batch` (POST, `storeMcbs`) takes `count` (1–50) and creates that many slots in one transaction via `nextMcbNumbers()`: it finds the panel's highest trailing-digit number, keeps its prefix and padding (min 2 digits; empty panel → `MCB-01`), and continues from there — so the `unique(asset_id, mcb_number)` index can never be hit. Numbers not ending in digits (e.g. "spare") are ignored when finding the highest. **`str_pad` needs an explicit `STR_PAD_LEFT`** — the default pads right ("1"→"10"). The dialog's manual single add stays for special numbers.

## Customers claim MCBS; deletions are guarded
`customers.panel_asset_id/panel_mcb_id` (+ `_2` variants) FK `restrictOnDelete` into assets/panel_mcbs. Because the FKs would 500, both deletes are guarded with a flash-error `back()`: `PanelPowerController::destroyMcb` (MCB referenced by any customer column) and `AssetController::destroy` (asset referenced as panel_asset_id/_2). The customer dropdown's `taken_by` map in `CustomerController::panelOptions()` is built with a plain loop — `Collection::collapse()` renumbers integer keys, so `flatMap()` would destroy an `mcb_id => customer_id` map.

## Wayfinder multi-arg `.form()` takes ONE args object
`PanelPowerController.destroyMcb.form({ asset: panel.id, panelMcb: mcb.id })` — passing positional args `(panel.id, mcb.id)` is a TS error. Single-arg routes (`storeMcb.form(panel.id)`) accept the bare number. MCBs are rendered sorted server-side (`sortBy('mcb_number', SORT_NATURAL | SORT_FLAG_CASE)`); the dialog's add-input remounts via a `formKey` state bump on success so `resetOnSuccess` visibly clears it.

## Phase 2 is deferred on purpose
Linking customers to their panel/MCB (`customers.panel_asset_id` + `panel_mcb_id`, Dual-Source variants, MCB-consumed validation) is NOT implemented yet — the customer form's Panel PDU and Power Source fields remain free text until then.
