# Building expense entry

- Amount entry groups thousands using commas, accepts Persian/Arabic digits, and sends the ungrouped integer as a decimal string without JavaScript floating-point conversion.
- The form selects an active, nonfixed charge formula. The existing category ID remains the internal association; the lookup displays formula titles and is scoped to accessible buildings. This preserves seasonal formula switching without pinning expenses to obsolete formula IDs.
- Allocation method and financial-category controls are no longer exposed. Formula-driven expenses resolve their rule on the server; a submitted allocation method cannot override the active formula. Period calculation continues to use the active formula for the category.
- Supplier payment is stored in the new nullable `building_expenses.payment_status` (`paid` / `unpaid`), separate from the accounting lifecycle. New form submissions are posted regardless of payment state and are included in charge calculation. This flag records a declared payment state; it does not execute a wallet transfer.
- Existing payment states remain unknown, not inferred from accounting approval. Editing requires an explicit paid/unpaid choice. Existing accounting lifecycle states are preserved. Legacy API clients without payment status retain their existing behavior.
- Invalid, missing, inactive, fixed or ambiguous formulas reject formula-driven writes transactionally. Fixed charge formulas remain handled as fixed per-unit charges, not expense allocation.

## Deployment

Back up the database and deploy the complete release, including public JavaScript files. Run:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Refresh the expense page after deployment. This release requires the migration before accepting expense writes. No historical payment status is backfilled.

## Verification

- Node amount formatter tests: grouping, Persian/Arabic paste, integers beyond JS safe-number precision, empty/invalid input, and caret position during mid-value editing.
- Financial API regression: paid and unpaid expenses both calculate correctly; client allocation override is ignored; invalid payment state, inactive/fixed/missing formulas and failed-edit rollback are covered.
- Form configuration regression: formula lookup, money field, two payment choices, and absence of allocation/accounting-status controls.
