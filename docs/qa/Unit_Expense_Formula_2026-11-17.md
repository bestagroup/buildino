# Direct registered unit expense

Added `unit_expense` to charge/allocation methods, with the label «بر اساس هزینه ثبت شده برای واحد».

The registered amount is applied unchanged **per selected unit**, including negative credits. A multi-unit or all-unit selection repeats that amount for each eligible unit; it does not divide the amount. The form and formula wizard explain this explicitly. For installments only the current period's portion is applied. With no registered expense, this method creates no recurring fixed charge.

The period preview totals reflect the number of selected units. Existing building/block/unit scoping, expense status, payer resolution, stale-preview protection, installment scheduling and invoice aggregation remain in effect. Source formulas remain subject to the existing one-active-formula-per-category rule. No schema migration is needed (type columns are strings).

Validation: 11 targeted financial tests / 103 assertions passed, including direct amounts for units with different areas and no resident count, multiple selected units, excluded units, combination with other debt, two installments and remainder, repeated calculation without duplicate debt, negative direct credits, and no automatic charge without a bill. TypeScript validation and production build passed. No production financial writes or live visual tests performed.
