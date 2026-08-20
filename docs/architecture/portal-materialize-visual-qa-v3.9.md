# Buildino v3.9 - Portal Materialize Visual QA

This phase performs a presentation-only visual QA pass for the Resident and Provider portals.

## Scope

- Resident dashboard
- Provider dashboard
- Portal operation lists and server-side DataTables
- Operation detail pages and timelines
- Wallet, unit, job, settlement and activity cards
- Portal modal forms and validation-compatible controls
- Responsive and dark presentation

## Constraints preserved

- No route changes
- No controller/service/model changes
- No migration changes
- No financial or authorization rule changes
- Form action/method/name/id/type/value/data-* contracts are preserved
- Existing portal JavaScript behavior is preserved
- `buildino-materialize.css` remains the only final Materialize adapter layer

## UI architecture

Materialize RTL Core -> Materialize Theme -> Buildino operational CSS -> page-specific CSS -> `buildino-materialize.css`.
