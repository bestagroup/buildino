# Buildino v3.8 — Management Materialize Visual QA

## Scope

This phase refines the Management Dashboard and Operations/CRUD pages against the supplied Materialize/Pixinvent RTL reference.

The existing Materialize runtime remains canonical:

- `assets/vendor/css/rtl/core.css`
- `assets/vendor/css/rtl/theme-default.css`
- `css/buildino-materialize.css`

No additional theme layer is introduced.

## Changes

- Dashboard overview and filter composition aligned with Materialize card patterns.
- Role workspace compacted and aligned with dashboard action chips.
- Wallet/notification/role/date glance cards normalized.
- KPI, module, financial and operation cards normalized.
- Operations landing page aligned to Materialize card/grid language.
- CRUD resource header, context, toolbar, table, drawer and modal refined.
- Static CRUD inputs use native `form-control` / `form-select`.
- Dynamically generated CRUD fields receive `form-control`, `form-select`, or `form-check-input`.
- Runtime field names, values, IDs, `data-*` contracts and request payload mapping remain unchanged.

## Non-goals

No changes were made to:

- Routes
- Controllers
- Services
- Models
- Policies
- Migrations
- Financial/domain rules
- DataTables endpoints or response contracts

## Runtime validation

Runtime tests must be executed on the target environment. This snapshot does not bundle `vendor/`.
