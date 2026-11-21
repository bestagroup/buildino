# Resident portal implementation

Approved direction: compact home and independent work pages; preserve Blade, existing process APIs and server authorization.

- [x] Shared resident navigation, typography, mobile sidebar, invitation presentation.
- [x] Scoped work pages, creation dialogs, unit and loyalty pages.
- [x] Authorized actions on all historical record details; payment availability.
- [x] Available reservation slots, form errors, safe submission and return context.
- [x] Browser and isolated database regression tests; production build and review.

Ruling: use a feature branch in the current checkout so local Laravel assets and dependencies remain available; no secondary worktree or duplicated dependencies.
Ruling: existing providers retain their dashboard and operation templates; shared improvements must remain compatible with provider behavior.
Baseline: 13 portal web/access tests passed during audit. No database migrations are planned.

Completed: `feat/resident-portal-workspace`, using existing Blade layouts, Bootstrap, Select2, Jalali and numeric runtimes. No schema or process API changes; resident datatables accept an authorized optional unit filter. The existing provider views are covered by regressions.

Verification:
- Feature suites: PortalWebAccessTest, PortalOperationalDetailsWebTest, PortalLifecyclePhase2Test, PortalBladeCompileRegressionTest, ResidentPortalWorkspaceTest, PortalManagementShortcutTest, FacilityReservationFlowTest: **39 passed, 256 assertions**.
- Browser suites: resident-workspace-ui, portal-assets-ui, portal-invitation-ui: **3 passed**. Real route-rendered Blade and deployed assets, SQLite memory fixtures, mocked HTTP responses. New resident suite covers seven scenarios at actual 320/390/1440px widths (21 combinations), including dark mode, menu focus, records, slot/manual reservations, validation, uncertain responses and payment retry.
- `npm run build`: passed. Changed JavaScript syntax and `git diff --check`: passed.
- Visual inspection: actual rendered home and reservation dialog at desktop/mobile sizes; fixed primary-action color token contrast with a failing browser assertion followed by a passing run.

Final review: fresh read-only reviewer. No Critical findings. Four Important findings and filter-return context (regraded Important) reproduced by failing browser cases, then fixed:
- Selected reservation slot: send `facility_time_slot_id`, omit incompatible manual times, retain server price/capacity.
- Facilities without predefined slots: preserve explicit-time booking supported by the current backend.
- Ticket reply validation/uncertain result: visible form-local error and recovery link.
- Interrupted/truncated response body: preserve uncertain-write protection and block duplicate submission.
- Reset filters: clear saved URL context before following a record.
Verification after fixes: targeted browser cases RED→GREEN; full feature/browser suites above passed. No deferred review findings.

Deployment limitation: authenticated production sessions and the real payment gateway were not exercised. Browser API failures/payment initiation are simulated; existing API security/reservation tests exercise real application services against SQLite memory. No production data was changed.
