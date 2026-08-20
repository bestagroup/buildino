# Buildino UI Theme Migration

## Scope

The supplied RTL Materio dashboard assets are integrated as a presentation layer only.
No domain, route, controller, form field contract, DataTables endpoint, chart data source,
or authorization rule is changed by this migration.

## Strategy

1. Keep existing Management and Portal Blade views and their operational identifiers.
2. Load the supplied `core.css` and `theme-default.css` assets locally.
3. Apply Materio shell classes to existing Management and Portal layouts.
4. Use `buildino-template.css` as a compatibility layer for Buildino-specific components.
5. Use `buildino-template.js` only for presentation behavior: sidebar, theme switch,
   popovers, and command palette shell.
6. Preserve all existing Buildino operational JS references.
7. Do not copy views from the source theme project because they belong to a different domain.
8. Do not bundle font binary files. The project keeps a font-family reference/fallback only.

## Invariants

- Form `action` and `method`: unchanged.
- Field `name` and `id`: unchanged.
- CRUD `data-*` contracts: unchanged.
- Yajra/DataTables server-side endpoints: unchanged.
- Chart/dashboard data values: unchanged.
- Management/Resident/Provider authorization: unchanged.
- Existing Buildino JavaScript remains loaded after the visual template.

## Upgrade boundary

Theme assets live under `public/assets/vendor/css/rtl/` while Buildino-specific adaptation
lives under `public/css/buildino-template.css` and `public/js/buildino-template.js`.
This separation prevents future theme upgrades from overwriting application behavior.
