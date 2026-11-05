# Charge period review, calculation and issuance integrity

Base: `e2e8e91f8df2fb619d92b315bd9226c876f38b61`.

## Behavior

- `POST /api/v1/charge-periods/{id}/calculate` requires the 64-character `review_token` returned by the preview endpoint. The current frontend already sends it. Other API clients must obtain a new preview before confirming.
- Preview, calculation and issuance lock the period and its building's units within a transaction. Status is read again after obtaining the lock. Occupancy, ownership and resident-count services already lock the unit, so their edits cannot interleave with these operations.
- Locking the units uses a joined query. A `whereHas`/`EXISTS` query established an old InnoDB repeatable-read snapshot before waiting on a unit; the real concurrency regression test reproduced this and now passes with the joined lock.
- The fingerprint includes period inputs, formulas/items, expenses, units/floors, overlapping occupancy/ownership rows and resident-count history through period end. It is building-scoped and conservatively invalidates review on changes to these records.
- A monotonic calculation revision invalidates duplicate confirmations even within the same timestamp second. Calculation replacement, revision and status commit together; failure preserves the previous result.
- Before issuing, the current fingerprint must match the successful calculation. A changed source requires review and recalculation. Repeated issuance cannot create a second invoice from a stale period instance.
- Per-person allocation remains based on the first occupied day of the period. This change does not introduce person-day proration or modify issued invoices.

## Deployment

Run the new migration before serving the updated application:

```sh
php artisan migrate --force
```

The additive migration introduces nullable `calculation_source_hash` and `calculation_revision` (default 0). Previously calculated but unissued periods must be reviewed and recalculated once before issuance; no historical fingerprint is fabricated. Already issued periods and their invoices are unchanged. No frontend rebuild or new dependency is required.

## Focused validation performed

PHP 8.4.27, local isolated test databases; no production data or production server used.

| Scope | Result |
| --- | --- |
| ChargePeriodIntegrityTest + ChargePeriodPreviewTest | 11 tests, 45 assertions passed |
| Direct allocation, payer, occupancy and selected API flow dependencies | 11 tests, 111 assertions passed |
| Independent-process InnoDB concurrency on MariaDB 11.8.6 | 4 tests, 35 assertions passed |
| Total selected scope | 26 tests, 191 assertions passed |

Concurrency tests observe real `LOCK WAIT` state, then release the competing transaction: two calculation confirmations, two issuance requests, count edit during calculation, occupancy end during issuance. They are not sequential simulations. MariaDB is not evidence of testing the production MySQL version.

The person-count end-to-end test changes the split of a 300,000 expense from 200,000/100,000 to 150,000/150,000 and replaces the resident; issuance uses the replacement payer and never creates an invoice for the former resident.

Commands to reproduce only this scope:

```sh
php vendor/bin/phpunit tests/Unit/Domain/ChargePeriodIntegrityTest.php tests/Unit/Domain/ChargePeriodPreviewTest.php
php vendor/bin/phpunit tests/Unit/Domain/ChargeFormulaAllocationServiceTest.php tests/Unit/Domain/ExpensePayerPeriodTest.php tests/Unit/Domain/OccupancyServiceTest.php tests/Feature/Financial/SharedExpenseResponsibilityFlowTest.php tests/Feature/Security/FinancialCoreFlowTest.php --filter 'ChargeFormulaAllocationServiceTest|ExpensePayerPeriodTest|OccupancyServiceTest|test_posted_shared_expenses_are_allocated_and_invoiced|test_charge_period_uses_persistent_formulas|test_inactive_charge_formula_is_excluded'
```

Concurrency tests are opt-in. Configure `DB_CONNECTION=mysql`, connection credentials and an exclusively disposable database whose name ends in `_test`, then run:

```sh
CHARGE_CONCURRENCY_TESTS=1 php vendor/bin/phpunit tests/Feature/Financial/ChargePeriodConcurrencyTest.php
```

This test **runs migrate:fresh** in that test database. The test account also needs permission to inspect `information_schema.innodb_trx`. Child PHP processes must inherit the same PHP extension configuration.

## Existing unrelated failure

An initial run of the entire SharedExpenseResponsibilityFlowTest also reached `test_expense_page_lists_active_formulas_instead_of_unconfigured_categories`. With a test APP_KEY configured, it returns 302 instead of 200 because the fixture account lacks management dashboard access. The exact failure was reproduced after temporarily restoring the service, controller, model and test to the base commit; it is not introduced by this change. Its access policy/fixture was not changed here. The final selected scope excludes that unrelated UI test.

No whole-project suite, browser UI, gateway or live deployment verification was performed.
