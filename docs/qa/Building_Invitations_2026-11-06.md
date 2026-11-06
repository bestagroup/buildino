# Building invitation workspace

- Existing management operations/invitations URL now renders a building-scoped workspace.
- One permitted building is selected automatically; multiple permitted buildings require selection. Incoming building/unit context is checked against view permission.
- Candidate rows are distinct active user/unit relationships from ownerships and occupancies, scoped to the selected building. A person linked to multiple units has independent rows and codes. Disabled accounts and missing mobile numbers cannot be selected.
- Recipients and history are paginated (30 rows). Select-all explicitly selects the current page. Browser sends selected pairs sequentially, retaining each result and refreshing history without discarding failure details.
- Each POST rechecks building-specific create/update permissions, current relationship and SMS eligibility. A unit row lock serializes sends through this workspace. Resend additionally locks its invitation against acceptance. Each recipient has a separate transaction; no HTTP transaction retry is configured.
- Pending state persists on provider errors or uncertain delivery. A retry is manual. Sent means gateway accepted, not confirmed handset delivery.
- Resend rotates the existing code and resets validity to the chosen 1–720 hours. Used/cancelled invitations are rejected. An older invitation cannot be reactivated over another pending/sent valid invitation for the same user/unit.
- History shows last successful send time, expiry, accepted status and accepted_at. Acceptance remains handled by the existing invitation/registration service.
- The SMS contract is unchanged: BuildinoInvitation receives code, recipient name and building/unit. The registration URL must be included as static text in the approved provider template: https://panel.buildino.ir/register . No provider-panel edit or production SMS send was performed.

## Focused checks

- BuildingInvitationPageTest: 6 tests, 33 assertions passed (building selection, authorization, duplicate prevention, resend, used state, multi-unit recipient, provider failure).
- Existing UnitInvitationFlowTest: 9 tests passed as part of the first combined run (13 tests / 112 assertions including the initial four workspace tests).
- DOM test building-invitations.test.mjs passed: selection, distinct unit/user pairs, duplicate-click guard, partial failure and history refresh.
- JavaScript syntax and git diff whitespace checks passed. No full suite run, no real browser visual test or production deployment.

Deploy PHP, Blade and public/js together; run php artisan view:clear. No migration or frontend build needed.
