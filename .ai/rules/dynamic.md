---
paths:
  - 'resources/js/pages/dynamic/**'
---

# Dynamic

## No wrapper pages: every child menu URL renders its own real page file; module roots redirect
Each child menu URL /parent/child maps 1:1 to a full page file dynamic/{parent}/{child}/index.tsx — no re-export wrappers (supersedes the earlier wrapper guidance). Module roots are redirects, not pages: /assets and /rack are controller index() redirects to {parent}.overview, /monitoring is an unnamed closure route redirecting to /monitoring/overview. CRAP: the monitoring redirect route must stay UNNAMED — naming it 'monitoring.index' makes showChild's overview fallback (Route::has(parent.index)) redirect /monitoring/overview into the root redirect, looping forever. Monitoring child pages share resources/js/components/monitoring-panel.tsx with a fixed `tab` prop; the sensors sub-tab keeps ?source= query. The category page is served at /assets/categorylist (route name asset-categories.index, mutations stay under /assets/category/...); moved pages must call assetsOverview()/rackOverview() (not the root index routes) for search/per-page navigation or queries are lost in the redirect.

## Child page breadcrumbs via menuBreadcrumbs
Every child-menu page's `.layout` must return `breadcrumbs: menuBreadcrumbs(props.menus, <ownUrl>) ?? [single-crumb fallback]` using the helper in resources/js/lib/menu-breadcrumbs.ts. `props.menus` is the globally shared active-menu array from HandleInertiaRequests; `<ownUrl>` must equal the DB menu url exactly (use Wayfinder `.url()` when the route is named; unnamed catch-all routes have no Wayfinder fn, so hardcode the path). Two crumbs: parent (href = parent.url, roots /assets /rack /monitoring redirect to overview) then current child. Fallback keeps the legacy single crumb when the URL is not a seeded menu. Top-level pages keep single crumbs.

## Child breadcrumb chain: showChildCrumbs > menuBreadcrumbs > single fallback
Every child-page .layout builds breadcrumbs as `showChildCrumbs(props) ?? menuBreadcrumbs(props.menus, <ownUrl>) ?? [single-crumb fallback]` (helpers in resources/js/lib/menu-breadcrumbs.ts). showChildCrumbs reads the server-guaranteed menu+activeChild props and is the PRIMARY source (clickable parent crumb never depends on the shared menus lookup); menuBreadcrumbs(props.menus, url) is the secondary source keyed by the exact DB menu url (Wayfinder .url() for named routes, literal path only for unnamed catch-alls); the single crumb is the last-resort fallback. Top-level pages keep single crumbs (current page = non-link by design).
