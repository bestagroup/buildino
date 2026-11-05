# Unit credit notes and financial navigation

Base: `384e0e0eaf507ae610a297afee0f8c078a74aec5`.

## Accounting behavior

- Manual invoice rows accept signed integer amounts, including normalized Persian/Arabic digits in the UI. A negative net subtotal creates an independent unit credit note. Negative credit notes cannot carry discounts, penalties or invoice adjustments.
- Saving a draft has no account effect. Issuing the credit note applies it to open debts of the same unit and building in invoice-ID order. This is a **unit-level** credit and may reduce the unit's invoices with different resident/owner payer assignments. It never crosses units/buildings.
- Set-off has an immutable source/target relationship in `invoice_credit_allocations`; `credit_applied_amount` reduces a debit invoice's final total and outstanding amount. `paid_amount` continues to represent actual payments only. No wallet funds, payments or payment allocations are fabricated.
- The credit note retains its original negative face value. `credit_remaining_amount` carries unused credit to later issued invoices. Its own outstanding amount is zero, so a credit note is never offered as a payment or installment target. A fully consumed note is settled (`paid`) without claiming a cash payment.
- Credit application is atomic under the unit and invoice row locks, with deadlock retries. Simultaneous credit issuance cannot overconsume debt. Draft editing/deletion rechecks status under locks so a stale draft cannot change an issued note.
- An active installment plan or pending/processing payment on a target debt blocks set-off and rolls the issuance back with an explicit message. Resolve the payment or cancel/recreate the installment plan before issuing the credit note. Already paid cash is never reduced.
- Issued credit notes cannot be edited or adjusted; corrections use an opposite signed invoice, preserving the original audit trail.
- The existing wallet-charge issuance path also applies credit before automatic wallet collection.
- Manual positive invoices already participate in resident payable totals **after issuance**. The period preview now also lists prior issued balances/remaining credit, separately from new period expenses. Historical balances are not copied into new charge rows, which would duplicate debt. Drafts remain non-payable.
- Resident and manager summaries display remaining credit; financial reporting restores applied set-off to gross debit invoice face values when summing signed credit notes, preventing double subtraction.

## Navigation

The charge workflow uses these permission-filtered tabs, preserving selected building:

1. Expense categories
2. Charge formulas
3. Building expenses
4. Charge periods
5. Unit invoices

Both Blade and Vue use `ManagementPageCatalog::CHARGE_TABS`. Existing other finance groups remain available.

## Deployment

Deploy backend, Blade templates and the committed `public/build` assets/manifest together, then before accepting financial requests run:

```sh
php artisan migrate --force
php artisan view:clear
```

The migration changes invoice item amounts and invoice subtotal/total to signed BIGINT, adds applied/remaining credit fields and the allocation table. Existing positive values are preserved; no old draft is automatically issued and no financial data is deleted. The rollback intentionally refuses to discard existing credit notes/allocations. No application dependency was added.

## Focused validation

Only this change and direct dependencies were tested, not the full project.

- PHP 8.4 / SQLite: **37 tests, 176 assertions** across InvoiceCreditServiceTest, InvoiceServiceTest, InvoiceAdjustmentServiceTest, InvoicePaymentReservationTest, BuildingAtGlanceTest, ChargeFinanceNavigationTest, ChargePeriodPreviewTest and ChargePeriodIntegrityTest.
- Existing wallet collection scenario: **1 test, 10 assertions**.
- MariaDB 11.8.6 / InnoDB independent processes: **5 tests, 49 assertions**, including concurrent credit notes and the existing preview/calculation/issuance locking scenarios. This also exercised the signed schema migration. A too-long generated unique index name was found and replaced with `invoice_credit_pair_unique` before the successful run.
- Vue workflow DOM test: **1 passed** (`tests/Frontend/invoice-credit-ui.test.mjs`), including negative Persian input `−۲۰۰٬۰۰۰`, negative numeric API payload, credit issuance action, remaining-credit display, navigation order, building context and permission-filtered tabs.
- `npm run typecheck`, `npm run build`, `git diff --check` passed. Production assets rebuilt.

UI test command in this workspace:

```sh
JSDOM_MODULE=/workspace/scratch/92f10c4747b7/test-tools/node_modules/jsdom/lib/api.js PATH=/workspace/scratch/92f10c4747b7/php-runtime:$PATH node --test tests/Frontend/invoice-credit-ui.test.mjs
```

JSDOM was installed outside the project solely for test execution. Alternatively set `CHROME_BIN` for the browser fixture. Chromium execution here was blocked by the environment's UNIX socket restriction; **visual layout, real-browser responsive behavior and production deployment were not verified**. The DOM path intentionally does not claim layout validation. MySQL production version was not tested; real concurrency ran on MariaDB.
