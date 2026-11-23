---
paths:
  - 'app/Helpers/**'
  - app/Helpers/AssetSheetHelper.php
---

# Helpers

## Asset List CRUD is config-driven per workbook sheet
config/asset_list_sheets.php defines 8 sheet presets. Each column has a storage target: a common assets column (name, brand, floor, owner, ...) or 'details' for the assets.details JSON column. AssetSheetHelper resolves sheet key/default, category subtree filter, values, detail whitelist, and the restricted category tree. AssetSheetHelper::autofill() returns the explicit `autofill` column list or falls back to `row_group.identity` for preset form autofill. AssetRequest only requires name + category_path; all other asset columns are nullable (migration 2026_11_09_192603). Non-General sheets reject a root category that is not in their 'categories' list. Export follows the active sheet columns; assets.details is not searched.

## EMS/CCTV floors are stored as floor:count lists
details.lantai_sensor (EMS) and details.lantai_kamera (CCTV) are normalised 'floor:count, floor:count' strings. A bare floor means 1, duplicate floors are summed, and floors are sorted naturally. Use AssetSheetHelper::normalizeFloorCounts() on save and floorCounts() to parse. Invalid segments throw and AssetController converts them to details.<key> validation errors. The future Overview per floor counts Perangkat from assets.floor and Kamera/Sensor by summing these lists, so a DVR on floor 3 with 5 cameras on floor 2 shows Perangkat 1 on floor 3 and Kamera 5 on floor 2.

## UPS asset grouping lives in AssetSheetHelper
AssetSheetHelper::isGroupedAsset / assetGroupMembers / assetGroupMembersByName define the same-name UPS group used by Asset Settings rows, checklist sync, and the group QR scan route. Reuse them instead of re-querying the ups-batt categories.

## Asset display name appends details.system
AssetSheetHelper::displayName() appends the FAP system detail (e.g. FM100 + NN100.A) for Asset Settings rows, QR labels, and the scan page heading. The stored assets.name stays FM100 because the FAP panel column maps to name; use displayName for labels only. CCTV assets append their category (CCTV-1 DVR).

## Device identity is one helper for rows, QR, and counts
AssetSheetHelper::deviceGroupKey() is the single definition of "one device": UPS groups by name, CCTV by name + asset category, everything else is per asset. Asset Settings row grouping and the Asset Category page count both use it, so the category list shows 2 for two UPS units even when 7 records (module/battery) exist. Pass pre-resolved ups-batt/cctv category id lists when mapping many assets to avoid a category query per asset.
