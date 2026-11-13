# Building expense hierarchy

Implemented two-level expense categories in financial_categories with parent_id, building_id, stable default_key and normalized title_key. Roots have no parent; only same-building roots may be selected as parents. Existing categories can be renamed or activated/deactivated; reparenting and deletion are unavailable. Unique title keys protect duplicate sibling/root creation, and writes lock the building.

11 default roots and their requested children are configured in config/expense_categories.php. ExpenseCategorySeeder is idempotent and preserves manager edits. Newly created buildings receive independent copies through an observer.

Data migration keeps global originals for historical JSON references. Each building receives independent legacy copies under دسته‌های پیشین, with expense/income/allocation-rule and formula-item references remapped without merging same-title histories. Existing scoped category IDs are retained. Migrations intentionally do not reverse this data transformation.

The management page is building-scoped. Expense entry chooses a parent then an active child with an active non-fixed formula, retaining the existing allocation behavior. Formula lookup and server validation reject global, foreign-building, root or inactive categories. Disabling a parent prevents new selections without deleting historical records.

Validation: 7 focused PHP tests / 321 assertions passed (ExpenseCategoryHierarchyTest and FinanceWorkspaceWebTest), TypeScript check passed after its reported type error was fixed, production Vite build passed. No live deployment, production migration, desktop visual review or full-project regression run was performed.

Deployment: back up the production database; deploy code and built assets; run php artisan migrate --force in the standard maintenance deployment flow. Existing buildings are backfilled by the data migration. php artisan db:seed --class=ExpenseCategorySeeder --force can safely provision missing defaults later. Test the migration on a copy of production data before release; the historical remapping has not been exercised against production data.
