# وضعیت تکمیل Buildino

این سند وضعیت قابل اثبات repository را از کارهایی که فقط روی staging/production و با credential واقعی قابل تأیید هستند جدا می‌کند. «تکمیل کد» به معنی وجود implementation، migration، permission، API/UI و تست خودکار مرتبط است؛ به معنی فعال‌بودن سرویس بیرونی مشتری نیست.

| فاز | محدوده | وضعیت repository | معیار/شاهد |
|---:|---|---|---|
| 0 | پاک‌سازی snapshot | تکمیل | `.env`، IDE، vendor، archive و runtime artifact از tracking خارج؛ CI و ignore policy فعال |
| 1 | زیرساخت Laravel/Vite | تکمیل | نصب lock‌شده، build، route/view/config cache و release gate |
| 2 | scope و فایل خصوصی | تکمیل | policy/PermissionChecker، تست cross-scope، private disk و ClamAV binary/TCP |
| 3 | هویت و ضدسوءاستفاده | تکمیل | password/OTP، reset، device binding، token revoke، rate limit و عدم بازگرداندن OTP تولید |
| 4 | ساختار ساختمان | تکمیل | مجتمع تا واحد، پارکینگ/انباری، مالکیت/سکونت/دعوت، اسناد و CRUD مدیریتی |
| 5 | موتور شارژ | تکمیل | formula/period/calculate/issue، rounding سرور و تست مالی |
| 6 | پرداخت | تکمیل کد | adapter عمومی PSP، initiate/verify/callback/webhook، HMAC، replay protection و reconciliation؛ تست sandbox PSP بیرونی لازم است |
| 7 | اقساط و رسید | تکمیل | برنامه متوازن، پرداخت ترتیبی، overdue، جریمه/بخشودگی audited و receipt PDF |
| 8 | صندوق و حسابداری | تکمیل | کیف پول، posting، تسویه، payout، audit و integrity gate |
| 9 | رزرو | تکمیل | ظرفیت/قواعد/blackout، overlap/race protection، لغو و پرداخت |
| 10 | اعلان | تکمیل کد | inbox/preferences، SMS HTTP، FCM v1، SMTP و cleanup token؛ delivery واقعی نیازمند credential است |
| 11 | خدمات و پشتیبانی | تکمیل | request/quote/assign/pay، ticket/message/SLA/resolve/reopen و گزارش |
| 12 | UI مدیریت | تکمیل | design system، CRUD پویا، DataTables server-side محلی، اقساط و وفاداری |
| 13 | UI پرتال | تکمیل وب | داشبوردهای resident/provider، جزئیات عملیاتی و responsive web؛ پذیرش مرورگر واقعی staging لازم است |
| 14 | موبایل | تکمیل source | auth/device/bootstrap/version/features/pagination و کلاینت Flutter با secure storage، واحدها، صورتحساب/اقساط، وفاداری و تست CI؛ تولید binary امضاشده به SDK و کلیدهای مالک وابسته است |
| 15 | OpenAPI/Postman | تکمیل | هر route-method از catalog runtime صادر و security drift خودکار audit می‌شود |
| 16 | گزارش/خروجی | تکمیل | گزارش مالی/عملیاتی، export صفی CSV/Excel/PDF، retention و permission scope |
| 17 | وفاداری | تکمیل | ledger idempotent، FIFO allocation، expiry، reversal، rule versioning و claim workflow |
| 18 | مشاهده‌پذیری | تکمیل کد | readiness/admin health، scheduler/queue heartbeat، integrity/accounting/gateway audits |
| 19 | QA و انتشار | تکمیل repository | PHPUnit/contract/build/audit/CI، Docker production، backup/restore/rollback runbook؛ restore drill و UAT باید روی staging اجرا شود |

## دروازه تحویل

قبل از tag انتشار باید همه این فرمان‌ها سبز باشند:

```bash
composer validate --strict
composer audit
npm ci
npm run build
npm audit --audit-level=high
php artisan migrate:fresh --force
php artisan test
php artisan api:contract:export
php artisan release:gate
```

روی production/staging واقعی:

```bash
php artisan system:dispatch-queue-heartbeats
php artisan system:scheduler-heartbeat
php artisan release:gate --production
```

## خروجی‌های خارج از اختیار repository

این موارد را نمی‌توان با کد یا داده ساختگی «سبز» اعلام کرد:

- credential و تأیید قرارداد PSP، SMS، FCM و SMTP
- DNS، TLS، سرور، registry و سیاست secret manager مالک پروژه
- بازنویسی تاریخچه Git و rotation کلیدهای قبلی بدون تأیید مالک
- UAT مرورگر/دستگاه واقعی، تست sandbox PSP و restore drill staging
- ایجاد scaffolding نهایی Android/iOS و ساخت/امضای binary native بدون Flutter SDK، keystore و Apple/Google accounts

راهنمای اجرای این موارد در `docs/production/DEPLOYMENT_RUNBOOK.md` و `docs/production/SECURITY_ROTATION_CHECKLIST.md` قرار دارد.
