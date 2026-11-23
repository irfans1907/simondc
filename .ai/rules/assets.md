---
paths:
  - 'resources/js/pages/dynamic/assets/**'
---

# Assets

## Asset List is a header-only per-sheet preset
Asset List (/assets/list, AssetListController) has no rows yet. config/asset_list_sheets.php defines 7 workbook sheet presets (FAP, EMS, CCTV, APAR, RAK DAN PDU, Akses Pintu, UPS & BATT; Sheet1 excluded). The ?sheet= query picks the preset, unknown values fall back to the default 'rak-dan-pdu', and the page only swaps the table header. Overview (/assets/overview) is summary cards only: summary values are null until the summary phase, so do not re-add the asset table there.
