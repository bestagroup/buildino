# Invoice debt scheduling by charge period

Manual invoice creation now accepts a starting charge period and 1–5 installments. The invoice is a draft source document, not an additional payable liability. A unique source/period allocation holds each signed installment. The final invoice issued by the charge period includes only its own installment, along with fixed charge and allocated expenses.

- Periods must belong to the unit's building and be draft or calculated. Select consecutive defined periods by start date; cancelled periods are excluded. Missing future periods or issued/closed intervening periods reject the complete transaction.
- Integer division retains the remainder in the last installment; negative credit schedules are supported.
- Source schedules cannot be issued directly, independently adjusted, or paid as source documents. Existing standalone invoices retain their previous API lifecycle.
- Source edits/deletion are blocked once any target period has issued an installment or is no longer open. Before issuance, changes invalidate affected calculations and preview tokens.
- Building locking serializes schedule changes with period creation, preview, calculation and issuance. Period issuance records the resulting invoice on each allocation. Financial foreign keys and uniqueness constraints protect allocations. Migration rollback intentionally retains financial data.
- UI removes date ranges, due/issue dates, discounts, penalties, row totals and bottom financial summary from manual entry. Period due dates/ranges supply internal accounting dates. The source detail displays all period installments and their issuance state. Building-at-a-glance includes scheduled installments in the latest calculated total.

Validation performed:
- InvoicePeriodScheduleTest: six tests covering API permissions and period scope, five-period exact division, single-period debt, read-only issued schedules, repeat calculation, stale preview/issue rejection, insufficient/closed/foreign periods, signed zero net and aggregate building report.
- InvoiceServiceTest, InvoiceCreditServiceTest, InvoiceAdjustmentServiceTest, ChargePeriodPreviewTest, ChargePeriodIntegrityTest, ChargePeriodBuildingScopeTest, BuildingAtGlanceTest and FinanceWorkspaceWebTest passed in the local test database.
- Real Vue component behavior verified through JSDOM for period selection, integer amounts, payload, duplicate submit prevention and absence of direct issuance on source schedules. Existing credit UI checks also passed.
- TypeScript validation, JavaScript syntax and Vite production build passed. Full project suite was not run.
- No live production mutation, visual browser validation or real MySQL concurrent-session execution was performed.

Deployment: pull main, run `php artisan migrate --force`, then `php artisan optimize:clear`. The compiled Vite assets are committed. Deploy migrations before serving the new UI/API. No existing invoice data is converted automatically. Future periods must be defined before a multi-period schedule can be saved.
