# Buildino v3.7 — Materialize UI Migration

## Source UI
The visual system is derived only from the user-supplied `public(1).zip` reference.
The reference uses the Materialize/Pixinvent vertical RTL dashboard contract:

- `layout-wrapper`
- `layout-content-navbar`
- `layout-container`
- `layout-menu menu-vertical menu bg-menu-theme`
- `app-brand`
- `menu-inner / menu-item / menu-link`
- `layout-page`
- `layout-navbar navbar-detached bg-navbar-theme`
- `content-wrapper`
- `container-xxl`
- Materialize `core.css` and `theme-default.css`

## Migration strategy
Buildino business views were not replaced with the source project's Blade files.
Only the visual language and shell contract were migrated.

The operational CSS/JS remains available for Buildino-specific workflows, while one final
`buildino-materialize.css` adapter owns the visual presentation.

Legacy theme layers are no longer referenced by Blade:

- `buildino-template.css/js`
- `buildino-materio-phase2.css`
- `buildino-materio-phase3.css`
- `buildino-materio-shell-recovery.css/js`

## Runtime CSS order
1. Buildino font reference
2. Materialize RTL core
3. Materialize theme
4. Materialize demo/layout helpers
5. SweetAlert/DataTables vendor CSS
6. Buildino operational CSS
7. Page-specific stack
8. `buildino-materialize.css` final adapter

## Runtime JS
Buildino operational JavaScript remains unchanged:

- `buildino-foundation.js`
- `buildino-management.js`
- `buildino-portal.js`
- `buildino-crud.js`
- `buildino-datatables.js`

Only shell/theme state is handled by `buildino-materialize.js`.

## Contract preservation
The migration must not change:

- routes
- controllers/services/models/policies
- migrations/schema
- form action/method
- field name/id/type/value
- `data-*` operational attributes
- DataTables endpoints/column definitions
- chart datasets
