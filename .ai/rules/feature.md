---
paths:
  - 'tests/Feature/**'
---

# Feature

## Override factory FKs to null in raw() payloads
Calling `Model::factory()->raw()` EXPANDS nested factory attributes and CREATES their rows (e.g. AssetFactory's `category_id => Category::factory()` inserts a stray Category). When building HTTP payloads from raw(), always override FK keys to null: `Asset::factory()->raw(['category_id' => null])`, then unset/replace as needed. Otherwise tests that assert exact table counts fail mysteriously.

## Pin searchable factory columns when a test asserts exclusion
AssetFactory room picks from ['Ruang Server','Ruang UPS',...] and RackFactory room from ['NOC',...,'Ruang UPS']; export/index search matches room/brand/capacity/category too. A test that creates a row via factory and asserts it is NOT returned for an ambiguous search term (e.g. 'UPS', 'NOC') must pin those fixture columns (room, category, brand) or it flakes ~20% per run. Deterministic fix: override the fixture column, do not narrow the search.

## Do not hardcode auto-increment ids in tests
RefreshDatabase wraps each test in a MySQL transaction, so AUTO_INCREMENT is never reset: ids (and derived values like Troubleshoot ticket_number TKT-00001) continue across tests. Read the created model (e.g. Troubleshoot::latest('id')->firstOrFail()) instead of asserting a literal TKT-0000N. Order-sensitive assertions must also be collation independent: MySQL utf8mb4_unicode_ci sorts case-insensitively ('Cisco' before 'CRS3xx'), so option lists are sorted in PHP (mb_strtolower) rather than by SQL orderBy.
