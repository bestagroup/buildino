# Charge-period SMS notices

Location: Operations → Finance and wallet → ارسال پیامک شارژ دوره.

- Building-scoped `charge-sms.manage` permission, granted to existing superadmin/building_manager roles by migration; included in the role seeder for new installations.
- Issued and closed periods only. Calculation previews are not debts and are not used.
- Positive outstanding balances of issued/partial/paid/overdue invoices in the selected period are summed by payer. Source installment plans are excluded. Paid invoices with zero balance do not generate messages.
- Each building/period/user has at most one durable sending claim. A unique database constraint and period-row lock protect concurrent submissions. HTTP calls happen after commit, without automatic retries.
- Missing invoice payer: manager explicitly chooses an active owner/occupant of that unit; mappings must be completed before the first sending claim. Existing invoice payers are never overwritten. No SMS expiration.
- Snapshot records amount, currency, invoice IDs, parameters, mobile, selected template, requesting manager, timestamp, status and provider ID. No financial records are changed.
- Accepted means provider acceptance, not confirmed delivery. Timeout, rejection or ambiguous result is marked unknown; inspect provider logs/dashboard before any manual repair. Never automatically resend unknown/processing records.
- Frontend sends one selected recipient per request sequentially. Leaving the page stops remaining client requests. Refresh shows persisted history and prevents duplicate requests for recipients already claimed.

## Deployment

Run `php artisan migrate --force` then `php artisan optimize:clear`.
Create and approve a dedicated Ghasedak verify template; suggested name `BuildinoChargeNotice` (not created by this change).

Suggested text:
«بیلدینو؛ ساختمان [param1]، مانده قابل پرداخت شارژ دوره [param2] شما: [param3]. مشاهده جزئیات و پرداخت از کیف پول واحد: https://panel.buildino.ir/portal/resident»

Use Ghasedak's own placeholder notation when entering the text. Parameters:
1. Building title
2. Charge-period title (including month/year)
3. Formatted outstanding amount plus ریال or تومان

Then set `SMS_PROVIDER=ghasedak` and `SMS_TEMPLATE_CHARGE_NOTICE` to the exact approved template name. No default template is supplied; absent configuration disables sends. Existing API key and secure Ghasedak endpoint are reused.

Validation: focused feature tests cover payer aggregation across units, repeated requests, unresolved payers, missing template, uncertain result, access isolation and unissued periods. No live SMS or production browser test was performed.
