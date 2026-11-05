# Unit invoice charge components

- Posted building expenses in the period are allocated using the active category formula.
- Every active fixed formula contributes its configured amount per active unit each period, without needing a building expense. These two paths already exist in ChargePeriodService and are preserved.
- Unit invoices now support a signed `adjustment_amount`, separate from penalties and discounts. The final total includes this value. Zero removes the adjustment; PUT replaces rather than accumulates it. The adjustment is specific to this invoice, not recurring.
- The UI exposes the amount and mandatory reason under unit invoice details, and previews the result before saving. Building-scoped `invoices.adjust` authorization applies.
- Writes lock the invoice and check the previous adjustment. FinancialAuditLog retains the actor, old/new values and reason. Credits cannot reduce the total below zero or confirmed payments. Pending payments and active installment plans must be resolved first. Settled invoices can be reopened by a positive adjustment.
- Fixed/variable charges may have separate invoices for different payer responsibilities; adjustment applies only to the selected invoice.

## Deployment

Upload code and public/build from main, then run:

```sh
php artisan migrate --force
php artisan optimize:clear
```

No automatic recalculation or mutation of existing production invoices is performed.

## Validation

- `npm run typecheck`: passed.
- `npm run build`: passed; compiled assets included.
- `git diff --check`: passed.
- Added focused InvoiceAdjustmentServiceTest coverage for signed replacement, retry, independent invoices, recalculation, settlement, paid-amount protection and stale edits.
- PHP is not installed in the editing environment; PHP tests were not executed. Run only the focused test on a test database before production deployment:
  `php artisan test tests/Unit/Domain/InvoiceAdjustmentServiceTest.php`
- No whole-project test suite was run.
