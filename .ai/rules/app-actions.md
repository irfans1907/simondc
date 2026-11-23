---
paths:
  - 'app/Actions/**'
---

# App Actions

## Asset checklist items are materialised, never deleted
SyncAssetChecklistAction turns in_checklist assets into checklist_items with scope='asset'. Templates live in checklist_item_templates keyed by the TOP-LEVEL asset category (subcategories inherit the parent). AssetChecklistObserver calls it on every Asset save and deactivates items on delete. Never delete checklist_items: checklist_entries cascades on delete, so retire items with is_active=false. `checklist:sync-assets --deactivate-legacy` syncs all assets and retires the config-seeded infrastructure items.

## Assets pick their checklist category explicitly
assets.checklist_category_id is chosen per asset in Asset Settings. Null means 'belum dipilih' and generates no items (no mapping fallback). SyncAssetChecklistAction resolves the template from the ROOT asset category via Category::root(); e.g. Modul 1 -> UPS Frame -> UPS uses the 20-item UPS template. Changing the checklist category updates existing checklist_items in place (key excludes checklist_category_id) so checklist_entries history survives.

## UPS checklist items are shared per asset name
Assets in the UPS sheet are grouped by name, so SyncAssetChecklistAction materialises one item set per group (owner = lowest id) instead of one per member. Do not key grouped items by asset_id or the daily check will show duplicate readings.
