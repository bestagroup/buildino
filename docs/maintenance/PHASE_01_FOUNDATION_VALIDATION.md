# گزارش تغییرات فاز ۱: Foundation

تاریخ بررسی: 2026-08-16

## تغییرات انجام‌شده

- provider مربوط به Yajra DataTables به‌صورت صریح در bootstrap ثبت شد.
- تعریف تکراری `GET /api/v1/mobile/bootstrap` حذف و composition موجود حفظ شد.
- فرمان `domain:expire-guest-visits` هر پنج دقیقه زمان‌بندی شد.
- ورودی‌های مفقود Vite ایجاد شدند.
- لایه مشترک motion/loading/skeleton/empty-state و SweetAlert به دو پنل افزوده شد.
- `.env.example` با تنظیمات واقعی API، OTP، اعلان، FCM، پرداخت، موبایل،
  گزارش، health و Swagger کامل شد.
- `.gitignore` از انتشار secretها و runtime artifactها جلوگیری می‌کند.
- دستورات `composer release` و `composer release:production` تست PHPUnit
  را پیش از ممیزی‌های دامنه اجرا می‌کنند.
- تست‌های regression برای binding دیتاتیبل، یکتایی route اپلیکیشن و
  ثبت scheduler اضافه شد.

## اعتبارسنجی موردنیاز روی runtime

محیطی که این snapshot در آن بررسی شد PHP و Composer ندارد. بنابراین موارد
زیر باید قبل از merge یا deploy روی CI دارای PHP 8.2+ اجرا شوند:

```bash
composer validate --strict
composer install
composer test
composer release
php artisan schedule:list
php artisan route:list --path=api/v1/mobile/bootstrap
php artisan l5-swagger:generate
php artisan api:contract:audit
```

برای frontend نیز باید `npm install`، ایجاد `package-lock.json` و
`npm run build` انجام شود.

## وابستگی‌های مرحله بعد

پس از فراهم‌شدن runtime، نصب وابستگی‌ها باید با Composer انجام شود تا
`composer.json` و `composer.lock` همزمان تغییر کنند:

```bash
composer require laravel/socialite:^5.29 matomo/device-detector:^6.5 mews/captcha:^3.5
```

فعال‌سازی feature flagها تا زمان تکمیل route، migration/config، تست و
راهنمای استقرار مجاز نیست.
