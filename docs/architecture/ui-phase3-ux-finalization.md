# Buildino UI Phase 3 — UX Finalization

This phase is presentation/interaction hardening only. It does not change routes,
controllers, domain services, models, migrations, financial rules, authorization,
or form field contracts.

## Shared UI foundation

`public/js/buildino-foundation.js` is the canonical shared UX namespace. It owns:

- confirmation dialogs;
- toast notifications;
- button busy state;
- inline validation rendering;
- validation cleanup;
- accessible `aria-invalid` and `aria-busy` state.

`buildino-management.js` augments this namespace with formatting helpers instead
of replacing it.

## Validation contract

Laravel/API HTTP 422 errors are rendered beside the matching field when a field
can be resolved. The original error payload remains authoritative; no business
validation is duplicated in JavaScript.

## DataTables

Server-side DataTables expose `aria-busy` while data is loading or filters are
reloaded. Ajax failures are routed through the shared notification foundation.

## Portal forms

Resident/provider Ajax forms use the same loading and 422 validation contract as
management CRUD forms. Existing endpoint payloads and form `name/id/data-*`
contracts are unchanged.
