---
paths:
  - 'resources/js/pages/dynamic/assets/list/**'
---

# List

## Preset autofill for row_group or explicit autofill sheets; keep form fields controlled
Asset presets (assetPresets prop) carry `values` + `category_path` for sheets with row_group (CCTV, EMS, FAP) or an explicit `autofill` list; the payload copies row_group.identity or the configured autofill keys (skipping computed columns, storage name/notes, and multi_row keys) using the first non-empty value across the name group, so per-row fields like kapasitas_hd and FAP system/zone stay blank. The name field is resolved from the column whose storage is `name` (e.g. `nama_perangkat` on EMS, `panel` on FAP), not a hardcoded `nama` key; it becomes a ComboboxSelect when that key is `nama` or any preset has `values`. On the EMS form the name column renders before `lantai_perangkat` so it sits next to Kategori, while the table/export keep the config order. handleNameChange merges preset.values into formValues (falling back to brand/capacity for UPS) and sets the category path when its root exists in the sheet tree. Every Input/textarea in AssetFields must stay controlled (`value={formValues[key] ?? ''}` + updateValue); with defaultValue the autofilled values never render.
