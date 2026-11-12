# انتشار و بازیابی Buildino روی cPanel

راهنمای اجرایی انتشار: [CPANEL_DEPLOYMENT.md](CPANEL_DEPLOYMENT.md). پروژه با Composer، Artisan و Vite نگهداری می‌شود.

## پیش از انتشار

- از دیتابیس و `storage/app` با ابزار Backup هاست نسخه پشتیبان بگیرید؛ فایل تنظیمات و `APP_KEY` را جداگانه و امن حفظ کنید.
- بازیابی نسخه پشتیبان را روی محیط آزمایشی بررسی کنید؛ صرف وجود فایل backup کافی نیست.
- `APP_ENV=production`، `APP_DEBUG=false` و `OTP_LOG_CODES=false` باشند. ثبت کد OTP فقط با فعال‌سازی صریح در محیط local مجاز است.
- نسخه PHP و extensionهای مورد نیاز را با `composer check-platform-reqs --no-dev` بررسی کنید.
- commit مقصد و نسخه قبلی را برای امکان rollback ثبت کنید.

## انتشار

از ریشه پروژه و با حساب مالک فایل‌ها اجرا کنید:

```bash
sh scripts/deploy-cpanel.sh
php artisan release:gate --production
```

صف‌های `default`، `notifications` و `reports` و scheduler باید مطابق راهنمای cPanel فعال باشند. دسترسی وب فقط به پوشه `public` داده شود. کلیدها و `.env` نباید در Git یا مسیر عمومی قرار گیرند.

## کنترل پس از انتشار

```bash
php artisan system:health --fail-on-degraded
php artisan system:integrity-audit
php artisan payments:gateway-audit
php artisan wallet-accounting:audit
```

ورود، دانلود نمونه اکسل و پیش‌نمایش یک فایل آزمایشی را بررسی کنید. پیامک، ایمیل و پرداخت واقعی فقط با مخاطب یا تراکنش مجاز بررسی شوند. برای خطای صف، نبود heartbeat، شکست پشتیبان‌گیری و افزایش خطای 5xx هشدار تنظیم کنید.

## بازگشت و بازیابی

در صورت شکست انتشار، برنامه را با `php artisan down --retry=60` وارد maintenance کنید. اگر schema با نسخه قبلی سازگار است، کد و assetهای همان نسخه را بازگردانید، `composer install --no-dev --prefer-dist --optimize-autoloader` و پاک‌سازی/بازسازی cache را مطابق راهنمای انتشار انجام دهید و workerها را restart کنید.

بازگرداندن دیتابیس باعث از دست رفتن تغییرات پس از backup می‌شود؛ تنها با تصمیم اپراتور، backup تأییدشده و توقف نوشتن داده انجام شود. migrationها را بدون بررسی سازگاری داده rollback نکنید. پس از بررسی health و سناریوی مرتبط، `php artisan up` اجرا شود.

در گزارش انتشار commit، نتیجه کنترل‌ها، محل backup و محدودیت‌های باقی‌مانده ثبت شود.
