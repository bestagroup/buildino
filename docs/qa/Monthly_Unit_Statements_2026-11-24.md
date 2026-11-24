# Monthly unit statements and documented deposits

## Behavior
- At-a-glance invoice tab and resident invoice list use a common period/unit statement projection. Existing invoice IDs, payer attribution and ledger records remain intact. Unissued drafts and installment source plans are excluded; legacy invoices without a period remain separate.
- Residents retain the existing payer boundary: shared unit invoices and their own personal invoices. Managers see all invoices within their authorized building. A period grouping is not permission to reveal another person's invoices.
- Detail rows show title, quantity, unit amount and line total. Discounts, penalties, adjustments and credit offsets reconcile the summary. Consumed credit notes are not subtracted twice.
- Resident settlement uses only the unit wallet, transferring to the building wallet in one transaction. Unit/invoice/wallet locks, transfer idempotency and settlement records prevent duplicate debits. Insufficient funds abort the whole statement. Pending external payments block settlement until resolved.
- Existing legacy invoice payment buttons now settle their authorized period group through the same service.
- Resident portal top-ups and manager deposits explicitly opt out of automatic debt retry. Existing integrations retain their existing configured retry behavior unless `collect_outstanding=false` is supplied.
- Manager deposits require a PDF/PNG/JPEG receipt (max 5MB), amount and bank reference. Files use existing private/scanned managed storage. A building-scoped bank reference prevents duplicate credit, including retries with a new form UUID; reuse with another unit or amount is rejected.
- Deposit payment, verifier, wallet credit and receipt relation are persisted atomically. Receipts remain private and use existing file authorization. Building wallet is credited only when the resident settles, not when the unit wallet is topped up.

## Deployment
Run `php artisan migrate --force` and `php artisan optimize:clear` after updating code. Migration adds only the dedicated `unit-wallet-deposits.create` permission for existing superadmin/building_manager/finance_manager roles without synchronizing other permissions. Standard role seeding also preserves the permission. No financial-data migration or frontend build is needed (Blade and direct public JavaScript).

## Validation
Targeted tests cover monthly aggregation, payer isolation, views, exact transfer and replay, insufficient funds rollback, pending-payment exclusion, credit-note offsets, document-required deposits, duplicate reference, and no automatic debit on manual deposit. Existing wallet top-up, portal detail/workspace and at-a-glance dependency tests are exercised. Production funds were not changed; browser/mobile visual verification is not performed.

Final targeted run: 30 tests, 201 assertions passed. Also verifies changed-amount receipt replay rejection, infected-document rollback and no orphaned file. JavaScript syntax and diff whitespace checks pass. Actual concurrent MySQL worker execution and a production financial transaction were not performed; lock ordering and idempotent replays were verified in code/transactional tests.
