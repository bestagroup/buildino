# Wallet separation and building user import

Existing architecture retained: creation observers provision distinct User, Unit and Building wallets. A unit wallet remains with the unit after ownership/occupancy ends. Managers require scoped building-wallet.view; building permissions do not expose others' personal wallets. PortalAccessService enumerates all currently linked units across buildings.

## Updated 15-column building workbook

Operations center → users/access → ورود اطلاعات کاربران از طریق فایل اکسل, also available at `/management/user-import`. Auto-select one authorized building, otherwise require selection. Template has exactly the 15 Persian headers defined in BuildingWorkbookService, prefilled building title/code, floor and unit numbers, area, bedrooms, declared parking count (falling back to current parking assignments) and resident count. Each unit appears once; blank owner/tenant rows are skipped. No editable unit IDs, relationship enum, or date columns.

The building code/title and floor/unit tuple must match the selected building. Ambiguous duplicate floor/unit combinations across blocks are rejected rather than selecting a unit silently. First sheet only; 2 MiB upload, 20 MiB expanded ZIP, 500 data rows. Iranian phone numbers and localized digits are normalized.

Owner required; tenant optional but complete when present. With a tenant, occupancy belongs to tenant. Without a tenant, positive count means owner occupancy; zero means vacant. New relationships and resident-count changes take effect on the preview date (preview expires after 20 minutes). Accounts must match existing names, be active and unblocked; credentials/verification are never overwritten. Existing conflicting relationships are rejected and must be resolved in the regular forms. Exact repeated imports preserve active relationships. No automatic SMS.

Unit area/bedrooms and nullable units.parking_count are updated atomically with users, ownership, occupancy and resident-count history. parking_count is a declared quantity, not allocation of particular parking spaces. This workbook does not alter parking assignments or wallet balances.

Preview is stored server-side in the authenticated session for 20 minutes, with a random token, actor and building binding. Confirmation checks authorization and database validity again, locks the building and units, and commits atomically. User mobile uniqueness and locked duplicate checks prevent duplicate records. All public actions use the existing authenticated management middleware and CSRF protection. Permissions required: users.create, units.update, unit-ownerships.create and unit-occupancies.create in that building.

Deploy: composer install --no-dev --optimize-autoloader (OpenSpout 4.28.5; PHP 8.2–8.4, ZIP/XMLReader/DOM extensions), php artisan optimize:clear. Run php artisan migrate --force for 2026_11_07_160000_add_declared_parking_count_to_units. No frontend rebuild; the existing users navigation receives its new link from the controller. For legacy missing wallets: php artisan wallets:provision (idempotent; does not change balances). This command has NOT been run on production.

Focused automated checks cover wallet provisioning/backfill/access, multi-building ownership, manager personal/building/unit separation, revocation, actual XLSX preview and confirmation, foreign-unit rejection, account mismatch, replay rejection, unchanged existing credentials, repeated import, and rollback on assignment failure. Browser visual QA and production import have not been executed.

Updated-format verification: 9 focused import tests, 63 assertions pass, including exact XLSX headers, structure, operation-center card, multiple-building selection, separate tenant/owner, repeated import, invalid/foreign rows and conflict rejection. No production data import or browser visual QA performed.
