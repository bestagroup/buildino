# Protected building workbook editing — 2026-11-13

- Export has the requested 17 Persian headers in order, B Nazanin formatting, RTL, colored locked identity columns and text mobile/unit identifiers.
- A:D and the header are locked; only existing E:Q data cells are unlocked, including blank owner/resident cells. Worksheet row/column insertion/deletion and sorting are disabled; workbook structure and the hidden identity sheet are protected. Excel protection is an editing convenience, not the server security boundary.
- Server accepts authenticated version-2 exports only. Every encrypted token binds actor, building, unit identity, workbook UUID, ordinal and exported row count. Missing/added rows, extra columns, copied metadata and incompatible files fail before persistence. Modified A:D cell values never update database identity fields.
- Unit number changes use the original unit ID, validate uniqueness within the existing floor (including soft-deleted rows) and preserve ownership, occupancy and wallet relationships. Renumbering directly to an already occupied number, including swaps, is rejected; use an unused intermediate number and a fresh export.
- Added nullable `units.storage_count` as a declared count, matching the existing parking-count approach. When not declared, export counts current storage assignments. No physical storage assignment is created or deleted by this field.
- Unit usage accepts مسکونی / تجاری / اداری / سایر, or their existing canonical enum values. Existing unit fields, dates, roles and mobile-normalization rules remain in place.
- Upload/preview session version is now 4; stale previews and old 15-column exports must be replaced with a fresh download.

## Validation

`BuildingUserImportTest`: 28 tests, 234 assertions passed. Includes XLSX XML protection checks, blank editable cells, exact headers, read-only identity tampering, rename persistence, duplicate numbers, invalid usage/count, stale preview, extra rows/columns/metadata, account permissions, rollback and relationship preservation. `git diff --check` passed. Only the changed import flow and direct dependencies were tested; no whole-project test run.

Excel desktop visual interaction was not performed. Protection/formatting were inspected from the actual generated XLSX XML and round-trip imports were exercised with OpenSpout.

## Deployment

Deploy the commit, run `php artisan migrate --force`, then rebuild Laravel caches using the project's normal deployment flow. No frontend bundle change is required. Download a new workbook after deployment. The migration adds a nullable column and does not delete existing data. Production deployment/database migration was not executed in this task.
