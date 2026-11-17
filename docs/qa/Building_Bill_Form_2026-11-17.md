# Building bill presentation revision

- Removed `outline` and `outline-*` declarations from project-owned CSS files in resources/public, including deployed library styles and compiled styles. No CSS tests were run, as requested.
- Category selectors now load active authorized expense categories directly, including parents without charge formulas. Backend formula/authorization rules are unchanged; a category without the required active formula cannot be calculated until configured.
- Form order: parent category, subcategory, title, payer, receipt/invoice number, target units, payable period, installment count, total payable, read-only installment amount, description.
- Removed distribution/date/block and line-editor controls from the form. The API still receives its existing fields: preserved distribution mode, preserved existing date or today's date on creation, and hidden existing scope. No schema or calculation changes.
- Existing source lines remain intact when editing without changing total. New/changed totals use the existing invoice-line payload, quantity 1. Installment display follows existing integer division and shows the final installment when it carries a remainder.
- Verified only the affected form with JSDOM (order, hidden fields, all parent choices, Persian amount input, auto installment amount, selected-unit payload, legacy scope), the financial workspace controller tests (6 tests, 327 assertions), TypeScript checking and production build.
- Production/visual browser verification has not been performed. No migration required.
