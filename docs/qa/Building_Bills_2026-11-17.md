# Unified building bills — 2026-11-17

## Behaviour
- `/management/operations/expenses` is now **صورتحساب ساختمان**. One document contains title/date, owner/resident payer, selected units (one/many/all in building/block scope), signed quantity × unit-price lines, a starting open charge period and 1–5 installments.
- Amount means the **total document**, not an amount repeated for every unit. Category-based expenses retain their active non-fixed charge formula. Direct debt/credit uses equal distribution. Each period receives its installment, then allocates it to the eligible units using that period's inputs. The last installment carries the division remainder.
- New schedules are independent of expense date. Legacy unscheduled expenses retain date-based inclusion. Existing invoice schedules, generated invoices, payments and credits are retained without conversion or duplicate debt creation.
- Only draft/calculated periods can be selected. Server validation rejects a closed/issued/intermediate non-open period, foreign units/buildings/blocks, missing future periods, and invalid/empty amounts. Building mutex serializes schedule writes against calculation/issuance; editing schedules invalidates affected calculations and stale preview tokens.
- Documents cannot be edited/deleted after any assigned period leaves draft/calculated or an existing allocation belongs to an issued/closed period. Backend revalidates even if browser selections are stale.
- The independent invoice menu is removed. Its old URL redirects to the invoice-history view within building bills. Invoice permissions still govern history, edits to old schedules, payments and installment operations. At-a-glance embedded unit history remains supported. The separate `Invoices.vue` module remains necessary for these existing financial operations.
- Details retain original line items and per-period amounts; issued allocation metadata snapshots the source lines. Existing unit-level credit set-off remains intact.

## Storage and deployment
Migration `2026_11_17_100000_add_building_bill_schedules.php` adds schedule/line/target-unit fields to `building_expenses`, creates `expense_period_allocations`, and makes expense/allocation amounts signed. No historical data is deleted. Financial migration down is deliberately non-destructive.

After deploying the committed source and Vite assets, run:

```sh
php artisan migrate --force
php artisan optimize:clear
```

Do not serve the new release before its migration completes. Re-preview/recalculate previously calculated, unissued periods after deployment because source fingerprints changed. Already issued invoices are not recalculated.

## Targeted verification
Passed:
- `BuildingBillScheduleTest`: six scenarios / 61 assertions: selected-unit and all-unit distribution, five installments and rounding, no duplicate calculation, foreign/closed/missing periods, backend scope checks, stale preview rejection, immutable issued documents, signed credits, payer identity and existing unit credit set-off.
- `ExpenseFormPersistenceTest`, `InvoicePeriodScheduleTest`.
- `FinanceWorkspaceWebTest`: scoped selectors/navigation, invoice-history permissions and embedded unit view.
- `ChargePeriodPreviewTest`, `ChargePeriodIntegrityTest`, `InvoiceCreditServiceTest`, `ChargePeriodBuildingScopeTest`: 21 tests / 119 assertions.
- `expense-form.test.mjs`: JSDOM real form flow, Persian amount normalization, selected-unit payload, exclusion of closed periods, legacy editing and building/block scope behaviour.
- `npm run typecheck`, production Vite build and changed PHP syntax checks.

Baseline limitation: the five tests in `SharedExpenseResponsibilityFlowTest` already fail on untouched main commit `832e208d` (verified in an isolated checkout). They use outdated category/required-field/dashboard-access expectations. This change does not claim that file or the full test suite passes.

Not verified: production deployment, visual browser QA, MySQL migration execution or live concurrent requests. Tests above used SQLite and JSDOM; no live financial records were written.
