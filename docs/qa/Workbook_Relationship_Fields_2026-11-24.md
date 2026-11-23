# Workbook relationship fields

The signed workbook now has 20 columns. Existing columns retain their positions; ownership percentage, ownership start and occupancy start are appended. Usage J has a Stop-style dropdown containing مسکونی, اداری, تجاری. Existing legacy other usage can round-trip unchanged but cannot be newly selected/imported from another usage.

Dates export as real Excel dates formatted yyyy-mm-dd; reader accepts DateTime cells and validates ISO text. Existing values are exported. Empty added fields preserve current values/defaults; they do not delete relationships. Nonempty percentage must be greater than zero and at most 100. Future or malformed dates are rejected. Completed owner fields without an owner and occupancy dates without a resident are rejected.

Ownership data persists through UnitOwnershipService; explicit occupancy dates use OccupancyService with the registration-month default disabled for that import. Missing dates retain the registration-month policy. Updating dates/percentage on existing relationships requires their update permissions. Writes remain transactional and signed snapshots protect scope, identity and concurrency. Date changes record supplied resident count at the supplied start; unchanged dates do not rewrite historical counts.

Download a fresh workbook after deployment. Snapshot schema version is 3. No migrations or asset build required. Date/list styling is verified from generated XLSX XML and actual export/read round trips, not a desktop Excel visual test.

Validation: 39 targeted tests passed (299 assertions) across workbook, occupancy domain and registration-month tests. After adding native Excel date cells and limiting new usage choices, 32 workbook tests passed again (276 assertions). No production import performed.
