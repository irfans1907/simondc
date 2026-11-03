---
paths:
  - 'resources/js/pages/dynamic/rack/**'
  - 'app/Http/Controllers/RackController.php'
  - 'app/Http/Controllers/RackTypeController.php'
  - 'app/Models/Rack.php'
  - 'app/Models/RackType.php'
---

# Rack

## Rack List is the rack-TYPE CRUD page; Rack Management is the inventory table
/rack/racklist (rack.racklist -> RackTypeController::index) lists rows from the **rack_types** table (name + capacity + racks_count), NOT rack instances. CRUD mutations live at rack.racklist.store/update/destroy (manage-rack-management group). Rename or capacity changes on a type CASCADE to racks rows via `Rack::where('type', ...)` (racks.type is a plain string, no FK); destroy is blocked with an error flash (`type: 'error'`, session key `inertia.flash_data`) while `racks_count > 0`.

Rack types are DB-backed: `Rack::TYPES` / `Rack::CAPACITY_BY_TYPE` no longer exist. RackRequest validates `Rule::in(RackType::pluck('name'))`; RackController derives rack capacity with `RackType::where('name', ...)->value('capacity')`; RackFactory picks a random existing RackType (creating one only when the table is empty). `RackTypeSeeder` seeds the four defaults idempotently by name (updateOrCreate) — feature tests that touch rack validation must `$this->seed(RackTypeSeeder::class)` in beforeEach.

Rack Management (/rack/overview) is a SINGLE self-contained page: its inventory table, dialogs, search and pagination all live inline in `resources/js/pages/dynamic/rack/overview/index.tsx` (there is NO rack-panel.tsx component anymore — it was inlined back). Rack ID (rack_code) is a required manual field on create AND update (`Rack::nextCode()` / resolveCode auto-generation was removed). Tipe select options + auto-fill capacity come from the `rackTypes` server prop (`RackType::orderBy('name')->get(['name','capacity'])` via renderIndex) — never hardcode type lists in the frontend.

Both rack pages (overview + racklist) follow the Asset page footer pattern: the `Pagination` component RENDERS `PerPageSelector` inside one flex row (per-page left, "Showing of {total} total ..." center, links right, `justify-end gap-4`) — do not render PerPageSelector as a separate row above it. Search/per-page navigate via Wayfinder `.url()` strings (rackOverview.url() / racklist.url()) — a bare `rackOverview()` is a RouteDefinition, not a string (TS2322).

## Rack status + Nama Customer column are driven by the customer module
`racks.status` is NOT user input: `RackController::store` forces `Rack::DEFAULT_STATUS` ('Ready For Sale') and RackRequest has no `status` rule (update never touches it). `CustomerController` drives it: store → `Rack::OCCUPIED_STATUS` ('Occupied'); update that moves racks → old rack back to `DEFAULT_STATUS` (guarded with `whereDoesntHave('customers')`) + new rack `OCCUPIED_STATUS`; destroy → `DEFAULT_STATUS`. Pre-existing assignments need a one-off manual backfill (tinker/SQL) — there is no seeder for it.

The Rack Management table shows a **Nama Customer** column right after Status, read LIVE from the eager-loaded relation (`filteredQuery()` → `with('customers:id,rack_id,customer_name')`), never a stored column: cell = `rack.customers?.[0]?.customer_name ?? '-'`. Search value `customer_name` is a `SEARCH_COLUMNS` whitelist entry resolved through `whereHas('customers', ...)` (and folded into `all` via `orWhereHas`). The CSV export has its OWN header/row arrays — whenever `tableHeaders` gains or reorders a column, update `RackController::export()` in the same change. The status Badge colour comes from `statusBadgeClassFor(status)` in the page (green = `Ready For Sale`, rose = `Occupied`, slate fallback): a brand-new status renders slate until you add a branch.
