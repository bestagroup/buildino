# Buildino v3.11 — Payout Financial RC Hardening

This phase freezes the UI and hardens building/provider payout workflows for production.

## Invariants

- A payout retry with the same caller-scoped idempotency key returns the same request.
- Reusing the same idempotency key for different payout semantics is rejected.
- Wallet balance locking and payout request creation are committed or rolled back together.
- Building payout approve/reject/paid transitions use row-level locking and are idempotent where retries are safe.
- Provider payout creation now consumes the idempotency key already emitted by the provider portal.
- Management building payout creation uses the existing CRUD idempotency-key facility.
- Bank accounts are reloaded under a database lock before a new payout is created.

## Database

An additive nullable `idempotency_key` is added to both payout request tables. Uniqueness is scoped to `requested_by`, avoiding collisions across independent users while preserving retry semantics per caller.

## Compatibility

Existing callers may omit `idempotency_key`; the API remains backward compatible. New and updated UI flows send it automatically.
