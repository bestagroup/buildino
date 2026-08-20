# Buildino Materio UI Integration — Phase 2

## Scope

This phase changes presentation only. Domain services, controllers, routes, models, migrations, financial rules, authorization rules, form field contracts and server-side DataTable endpoints are not changed.

## Compatibility recovery

The v3.3 layouts referenced the following operational assets, but the files were absent from the snapshot:

- `buildino-foundation.css/js`
- `buildino-management.css/js`
- `buildino-portal.css/js`
- `buildino-crud.css/js`
- `buildino-datatables.css/js`

The operational Blade views in v3.3 were verified to match the compatible v2.8 operational view contracts. The missing operational assets were therefore restored unchanged from that compatible snapshot before applying visual overrides.

## Presentation layering

The effective CSS order is:

1. Bootstrap 5
2. Materio RTL core/theme
3. Buildino operational CSS
4. `buildino-template.css`
5. page-specific `@stack('styles')`
6. `buildino-materio-phase2.css` (final visual override)

The final phase-2 stylesheet intentionally loads after page-specific styles so the theme can change appearance without modifying business-oriented markup or JavaScript contracts.

## Areas polished

- Management dashboard hero, role workspace, KPI/stat cards, modules, operations, financial/health widgets
- CRUD header, context selectors, toolbar, table, pagination, drawer and workflow modal
- Server-side DataTables search/length/paging/table shell
- Resident portal hero, stats, units, wallet/activity cards and Bootstrap modals
- Provider portal hero, job cards, settlement/wallet cards and operational states
- Detail facts, timeline, status badges and empty states
- Responsive and dark-mode behavior

## Regression protection

`MaterioUiPhase2IntegrationTest` verifies:

- required operational and theme assets exist;
- every literal local `asset()` referenced by Blade exists on disk;
- final Materio CSS loads after page-specific styles;
- CRUD IDs/data attributes and DataTables contract markers remain present.
