---
paths:
  - resources/views/app.blade.php
---

# Views

## Vite page entrypoints: flat file wins, folder/index fallback
@vite in app.blade.php picks the page entrypoint by filesystem: flat page.tsx first, then folder/index.tsx (jsx variants too), filtered via file_exists — the build manifest only contains the entry Vite resolves first, so a hardcoded flat path throws ViteException 500 in manifest mode (no dev server) whenever a page lives in a folder/index.tsx. Keep the @php $pageEntrypoints array_values(array_filter([...])) logic intact. Guarded by test 'the page renders when the vite dev server is not running' in tests/Feature/MaintenanceTest.php (forces a missing hot file).
