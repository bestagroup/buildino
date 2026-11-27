# Finance and provider workspace refresh

Approved plan: conversation, 2026-11-27. Implement inline, preserving domain services, permissions, routes, currencies and durable send claims. No new dependencies or database changes.

- [x] Shared monthly statements: compact accessible records, details, resident settlement, manager receipt dialog, embedded report refresh.
- [x] Provider: consistent navigation, dashboard, lists and details; reuse quote, bank and payout handlers/forms.
- [x] Charge SMS: scoped selectors, recipient selection/count/confirmation/progress, real statuses and history.
- [x] Verify real Blade UI at 320/390/768/1440, focused financial/access tests and independent review.

Decisions: keep native receipt POST and validation redirects; enhance settlement with the existing JSON response. SMS amounts are calculated period amounts in rials, not current outstanding. Unknown send results cannot be retried automatically. Use existing checkout on a dedicated branch so the user's local server continues showing the work.

## Progress

Base: b38eada0. Working tree was clean. Shared boundary: statement writes notify the existing glance parent with the same origin/source-checked message protocol. Provider CSS is scoped away from resident pages.

Remote sync: fetched and fast-forwarded to b9ffc35f during implementation at user's request. Three commits, Select2 and build changes; no overlapping modified files or conflicts. Continue testing against the updated selector runtime.

Initial RED: four focused UI/access tests failed for missing receipt dialog, inactive invoice navigation, unrelated provider statuses and missing SMS finance navigation. All four now pass.

Independent review found two important issues, both reproduced RED and fixed: quote currency must follow the assigned building (presentation eager load now includes currency), and editing a pending receipt must retain the parent busy guard (dedicated form owns these bridge events). No domain calculations changed.

Additional browser checks fixed duplicate payout confirmations, guarded unexpected HTML success responses, and kept the mobile resident header compact with the newer unit-wallet markup. Existing invoice browser expectations now follow the current monthly wallet-settlement contract, introduced before this branch.

Validation: 43 focused PHP tests passed (245 assertions). New real-Blade browser suite passed 32 mode/viewport combinations (320/390/768/1440); existing resident suite passed 33 combinations. Glance, portal assets and invitation browser suites passed. Final targeted mobile header and screenshot checks passed after the small header fix. JS/PHP syntax and git diff whitespace checks passed. No live SMS, payments or application data used; fixtures use SQLite :memory: and mocked browser writes. No new dependencies, migrations, or generated build changes.
