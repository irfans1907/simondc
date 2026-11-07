---
paths:
  - 'resources/js/pages/dynamic/checklist/**'
---

# Checklist

## Checklist daily page loads all categories; monthly loads one
Daily Check receives all 13 categories and every entry for one date, then switches category tabs client-side. Initialize cell/note state from props instead of a sync effect: Inertia remounts on date navigation, and an effect would fight the user's edits. Monthly Check loads only the selected category/month via ?category= to keep the payload small. Breadcrumbs use showChildCrumbs ?? menuBreadcrumbs with the exact /checklist/{child} URL.
