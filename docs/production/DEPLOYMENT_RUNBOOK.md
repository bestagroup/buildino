# Buildino Production Deployment Runbook

این runbook برای انتشار کانتینری `compose.production.yml` است. اجرای فرمان‌ها باید توسط اپراتور دارای دسترسی production و از روی نسخه tag‌شده انجام شود.

## 1. پیش‌نیاز و secretها

1. `.env.production.example` را به `.env.production` کپی کنید.
2. `APP_KEY` را یک‌بار و خارج از repository تولید کنید؛ تغییر آن داده‌های رمزگذاری‌شده و sessionهای قبلی را نامعتبر می‌کند.
3. مقادیر DB، SMTP، SMS، FCM و PSP را از secret manager تزریق کنید.
4. DNS و TLS را پیش از فعال‌کردن callback/webhook آماده کنید.
5. `.env.production` و Firebase service account نباید وارد image یا Git شوند.

حداقل کنترل دستی:

```bash
test "$(grep '^APP_ENV=' .env.production)" = "APP_ENV=production"
test "$(grep '^APP_DEBUG=' .env.production)" = "APP_DEBUG=false"
docker compose --env-file .env.production -f compose.production.yml config --quiet
```

## 2. Backup پیش از انتشار

```bash
sh scripts/backup-production.sh /srv/backups/buildino
```

خروجی شامل dump تراکنشی دیتابیس، archive فایل‌های private و checksum است. backup را روی storage جدا از میزبان برنامه replicate کنید. سیاست پیشنهادی: ۷ نسخه روزانه، ۴ نسخه هفتگی و ۱۲ نسخه ماهانه.

## 3. Build و preflight

```bash
docker compose --env-file .env.production -f compose.production.yml build --pull
docker compose --env-file .env.production -f compose.production.yml run --rm --no-deps app composer check-platform-reqs
docker compose --env-file .env.production -f compose.production.yml run --rm --no-deps app php artisan about
```

در صورت استفاده از registry، imageهای `buildino/app` و `buildino/web` را با commit SHA tag و push کنید؛ از `latest` به‌عنوان تنها مرجع rollback استفاده نکنید.

## 4. انتشار

```bash
docker compose --env-file .env.production -f compose.production.yml up -d db redis clamav
docker compose --env-file .env.production -f compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml up -d --remove-orphans
docker compose --env-file .env.production -f compose.production.yml exec -T app php artisan db:seed --class=Database\\Seeders\\RoleMatrixSeeder --force
docker compose --env-file .env.production -f compose.production.yml exec -T app php artisan system:dispatch-queue-heartbeats
docker compose --env-file .env.production -f compose.production.yml exec -T app php artisan system:scheduler-heartbeat
docker compose --env-file .env.production -f compose.production.yml exec -T app php artisan release:gate --production
```

اگر migration دارای تغییرات پرریسک و حجیم است، ابتدا روی clone دیتابیس production در staging زمان‌گیری شود. migrationهای Buildino به عقب برگردانده نمی‌شوند مگر rollback همان migration روی داده واقعی قبلاً آزمایش شده باشد.

## 5. Smoke test

```bash
curl --fail --silent https://YOUR_DOMAIN/api/v1/system/readiness
docker compose --env-file .env.production -f compose.production.yml ps
docker compose --env-file .env.production -f compose.production.yml logs --since=10m app web queue-default queue-notifications queue-reports scheduler
```

سناریوهای پذیرش staging:

1. ورود مدیر و ساکن با حساب غیرواقعی staging.
2. بارگذاری چهار جدول اخیر management.
3. ایجاد صورتحساب، اقساط، پرداخت sandbox، callback و receipt PDF.
4. رزرو با ظرفیت محدود و جلوگیری از هم‌پوشانی.
5. ثبت تیکت/درخواست خدمت و تحویل notification.
6. بارگذاری فایل clean و فایل EICAR در محیط ایزوله؛ EICAR باید رد شود.
7. award و redeem امتیاز وفاداری با replay همان idempotency key.

## 6. مانیتورینگ و alert

Endpoints/commands:

```text
GET /api/v1/system/readiness             readiness کمینه و عمومی
GET /api/v1/admin/system/health          جزئیات محافظت‌شده
php artisan system:health --fail-on-degraded
php artisan system:integrity-audit
php artisan payments:gateway-audit
php artisan wallet-accounting:audit
```

Alert فوری برای این وضعیت‌ها تنظیم شود:

- readiness غیر ۲۰۰ در سه نمونه متوالی
- scheduler/queue heartbeat stale
- failed job جدید یا backlog بحرانی
- failure پیوسته PSP، SMS، FCM یا SMTP
- فضای دیسک/DB بالای ۸۰٪ و backup ناموفق
- یافته critical در integrity/accounting audit
- افزایش غیرعادی 401/403/429/5xx

صف‌ها هر ساعت با `--max-time=3600` restart می‌شوند تا deploy و memory reclamation کنترل‌پذیر باشد.

## 7. Rollback کد

1. deployment را متوقف و `php artisan down --retry=60` اجرا کنید.
2. image tag قبلی را در `BUILDINO_IMAGE_TAG` قرار دهید.
3. اگر schema backward-compatible است، فقط app/web/worker/scheduler را با tag قبلی بالا بیاورید.
4. `release:gate --production` و smoke test را اجرا کنید.
5. برنامه را با `php artisan up` خارج کنید.

اگر schema backward-compatible نیست، restore کامل لازم است.

## 8. Restore کامل

Restore مخرب است و فقط با فایل‌های absolute و flag صریح اجرا می‌شود:

```bash
sh scripts/restore-production.sh \
  --confirm-restore \
  /srv/backups/buildino/buildino-db-TIMESTAMP.sql.gz \
  /srv/backups/buildino/buildino-storage-TIMESTAMP.tar.gz \
  /srv/backups/buildino/buildino-TIMESTAMP.sha256
```

اسکریپت ابتدا هم‌ترازی timestamp، checksum، سلامت gzip/tar و مسیرهای archive را کنترل می‌کند و سپس maintenance mode، restore دیتابیس/فایل، migration، production gate و خروج از maintenance را اجرا می‌کند. ابتدا هر ماه روی staging restore drill انجام و RPO/RTO ثبت شود.

## 9. کارهای پس از انتشار

- PSP/SMS/FCM/SMTP sandbox و سپس production را با تراکنش حداقلی تأیید کنید.
- secretهای موقت staging را حذف کنید.
- artifact قرارداد API و mobile minimum version را با release هماهنگ کنید.
- گزارش release شامل commit SHA، migrationها، زمان deploy، اپراتور، نتیجه gate و محل backup ثبت شود.
