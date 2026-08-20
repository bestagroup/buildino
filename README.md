# Buildino

Buildino یک سامانه مدیریت ساختمان مبتنی بر Laravel 12 و PHP 8.2 است. محصول شامل پنل مدیریت، پرتال نقش‌محور ساکن/مالک/ارائه‌دهنده خدمت، API نسخه‌بندی‌شده و کلاینت Flutter ساکنان در پوشه `mobile/` است.

## قابلیت‌ها

- ساختار مجتمع، ساختمان، بلوک، طبقه، واحد، مالکیت و سکونت
- نقش و مجوز scope-aware در سطح سراسری، مجتمع و ساختمان
- مهمان، تردد، امکانات، ظرفیت، رزرو و پرداخت رزرو
- شارژ، صورتحساب، اقساط، جریمه/بخشودگی، پرداخت و رسید PDF
- کیف پول، انتقال، تسویه ارائه‌دهنده، حسابداری و مغایرت‌گیری
- خدمات، پیشنهاد قیمت، پرداخت، تیکت، پیام و SLA
- اعلان درون‌برنامه‌ای، SMS، ایمیل و FCM HTTP v1
- اسناد خصوصی با کنترل دسترسی و اسکن ClamAV
- گزارش‌های صفی CSV/Excel/PDF و پاک‌سازی زمان‌بندی‌شده
- ledger وفاداری، rule versioning، انقضای امتیاز و درخواست جایزه
- کلاینت Flutter با ورود امن، انتخاب واحد، صورتحساب/اقساط، وفاداری و دریافت رسید
- OpenAPI/Postman تولیدشده از route catalog، health، heartbeat و release gate

## نیازمندی توسعه محلی

- PHP 8.2 یا بالاتر و Composer 2
- Node.js 24 و npm
- SQLite برای تست یا MySQL/MariaDB برای اجرای محلی

راه‌اندازی استاندارد:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
npm ci
npm run build
php artisan serve
```

در Linux/macOS به‌جای `copy` از `cp` استفاده کنید. برای اجرای دائمی Scheduler و worker مستقل هر سه صف:

```bash
composer runtime
```

فرمان `composer dev` نیز سرور، Vite، Scheduler و workerهای
`default`، `notifications` و `reports` را یکجا اجرا می‌کند. فعال‌نبودن هرکدام
به‌درستی باعث نمایش وضعیت کاهش کیفیت در داشبورد می‌شود.

ورودی‌ها:

- پنل مدیریت: `/management`
- پرتال کاربران: `/portal/login`
- readiness عمومی: `/api/v1/system/readiness`
- قرارداد OpenAPI: `docs/api/openapi-v1.json`

## داده آزمایشی

فقط در محیط غیرتولید:

```bash
php artisan buildino:access-scenario
php artisan buildino:demo-data
```

حساب‌ها و رمزهای demo در `docs/authorization/ROLE_MATRIX.md` مستند شده‌اند و نباید به production منتقل شوند.

## کنترل کیفیت

```bash
composer validate --strict
composer audit
npm ci
npm run build
npm audit --audit-level=high
php artisan test
php artisan api:contract:audit
php artisan release:gate
```

برای بازتولید قراردادها:

```bash
php artisan api:contract:export
```

`release:gate --production` علاوه بر یکپارچگی داده، تنظیمات سرویس‌های واقعی و heartbeat صف/scheduler را نیز بررسی می‌کند و باید روی همان محیط production اجرا شود.

## استقرار کانتینری

فایل‌های `Dockerfile` و `compose.production.yml` سرویس‌های زیر را تعریف می‌کنند:

```text
Nginx + PHP-FPM
MariaDB + Redis + ClamAV
default / notifications / reports workers
Laravel scheduler
```

نمونه تنظیمات را کپی و همه secretها را از secret manager یا محیط استقرار تزریق کنید:

```bash
cp .env.production.example .env.production
docker compose --env-file .env.production -f compose.production.yml build
docker compose --env-file .env.production -f compose.production.yml up -d
docker compose --env-file .env.production -f compose.production.yml exec app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml exec app php artisan release:gate --production
```

راهنمای کامل انتشار، rollback و restore در `docs/production/DEPLOYMENT_RUNBOOK.md` قرار دارد.

## امنیت و داده حساس

- فایل‌های `.env`، credentialهای FCM، کلیدها و خروجی‌های runtime نباید commit شوند.
- اگر نسخه قدیمی مخزن شامل secret بوده است، حذف فایل از commit جاری کافی نیست؛ تاریخچه باید با فرآیند کنترل‌شده بازنویسی و همه کلیدهای افشاشده rotate شوند.
- callback و webhook پرداخت باید HTTPS، امضاشده، idempotent و محدود به PSP پیکربندی‌شده باشند.
- فایل‌های بارگذاری‌شده روی disk خصوصی قرار می‌گیرند و در production اسکن بدافزار اجباری است.

## مستندات

- `docs/architecture/COMPLETION_PLAN.md`: وضعیت قابلیت‌ها و معیار تحویل
- `docs/authorization/ROLE_MATRIX.md`: نقش‌ها و scopeها
- `docs/deployment/PRODUCTION_COMMUNICATION_MOBILE.md`: SMS، FCM، PSP و قرارداد موبایل
- `mobile/README.md`: اجرای کلاینت Flutter و ساخت خروجی Android/iOS
- `docs/production/INTEGRATION_ENV_CHECKLIST.md`: متغیرهای integration
- `docs/production/DEPLOYMENT_RUNBOOK.md`: انتشار، مانیتورینگ، backup و rollback

## مجوز

مجوز نهایی محصول باید پیش از انتشار عمومی توسط مالک پروژه تعیین شود. وابستگی‌های ثالث تابع مجوزهای خود هستند.
