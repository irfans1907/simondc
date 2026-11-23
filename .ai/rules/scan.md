---
paths:
  - 'resources/js/pages/dynamic/assets/scan/**'
---

# Scan

## Scan checklist card posts only edited cells
scan/checklist-card.tsx mirrors the Daily Check grid but keeps dirty cells only, so untouched time slots are never cleared. UPS group member details come from members.*.identity (AssetSheetHelper::assetDetailFields); base model/serial_number are empty for UPS rows.
