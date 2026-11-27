# Facilities and reservations

Approved plan: P10/P11 redesign in management and resident portal; Blade and existing JavaScript, tokens, Select2, numeric and Jalali controls. No database changes or new payment rules.

1. Add an authorized, atomic schedule-batch endpoint to the existing configuration controller/service. Validate complete equal-length slots; conflicts roll back all days. Existing APIs remain compatible.
2. Keep three configuration sections. Generate days and slots together with preview; preserve individual editing and persisted rules. No automatic replacement of existing schedules.
3. Share availability/selection logic between management and resident booking. Two visible steps, manual-time compatibility, truthful server result, preserved context and recovery after uncertain writes.
4. Management directory first and modal details/actions. Resident uses its existing modal. Scope all CSS and preserve other portal forms.
5. Verify backend authorization/rollback/reservation/payment regressions and real Blade browser flows at 320/390/768/1440, light/dark and keyboard.

Execution ledger:
- Baseline: 12 PHP tests / 80 assertions and both existing facility browser tests pass; clean main b21c68e2.
- Workspace: feature branch in the existing local checkout so the user's running local server previews changes; no separate dependency installation.
- Implemented: atomic schedule generation with rollback/authorization, compact three-section facility setup, shared slot availability, guided manager/resident booking, history-first manager view and native detail dialog. Existing manual-time, approval, payment and cancellation rules remain in use; no migration or payment simulation added.
- Final verification: 28 PHP tests / 188 assertions; shared state/configuration/reservation browser tests pass; actual facility Blade flow passes at 390/1440 in light/dark; actual resident reservation flows (normal/manual/422 recovery) pass at 390/1440. Earlier full resident portal suite passed all configured modes and 320/390/1440 layouts. `git diff --check` is clean.
- Broader portal regression has one pre-existing failure: `PortalLifecyclePhase2Test::test_provider_portal_exposes_wallet_history_and_waits_for_locked_payment_before_start` asserts the previous provider dashboard heading. Reproduced with the original `HEAD` layout; provider dashboard files are outside this change.
