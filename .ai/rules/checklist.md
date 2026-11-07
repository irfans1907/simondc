---
paths:
  - 'resources/js/pages/dynamic/checklist/**'
---

# Checklist

## Checklist daily page loads all categories; monthly is power/PUE report
Daily Check receives all 13 categories and every entry for one date, then switches category tabs client-side. Initialize cell/note state from props instead of a sync effect: Inertia remounts on date navigation, and an effect would fight the user's edits. Monthly Check is the power-consumption / PUE report (3 tabs: Lantai 2, Lantai 4, PUE & Beban DC) served by MonthlyCheckController; it no longer uses checklist categories or entries. Breadcrumbs use showChildCrumbs ?? menuBreadcrumbs with the exact /checklist/monthlycheck URL.
