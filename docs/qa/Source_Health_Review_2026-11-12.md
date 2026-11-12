# بازبینی فنی سورس — ۲۰۲۶-۱۱-۱۲

مبنای بررسی: آخرین نسخه دریافت‌شده از `origin/main` با commit `4d36e68f`.

## نتیجه

در محدوده تغییرات اخیر، ورود اکسل، تکمیل ساختمان، OTP، مسیریابی و انتشار، اصلاحات زیر انجام شد. این گزارش گواه سلامت تمام سامانه یا محیط production نیست؛ مطابق درخواست، کل مجموعه تست‌ها دوباره اجرا نشد.

| بخش | یافته و اصلاح |
| --- | --- |
| OTP | ثبت کد در محیط local بدون opt-in انجام می‌شد. اکنون علاوه بر local، تنظیم صریح `OTP_LOG_CODES=true` لازم است؛ پیش‌فرض و نمونه production مقدار false دارند. |
| اکسل | بررسی تعارض مالک/ساکن با متن خام موبایل انجام می‌شد؛ حساب ذخیره‌شده با +98 می‌توانست با همان حساب در فایل 09 تعارض داشته باشد. تطبیق مشترک شماره حفظ شد و تعارض بر اساس شناسه کاربر بررسی می‌شود. |
| وابستگی‌ها | Sail حذف و فقط سه بسته Symfony شامل http-foundation، http-kernel و routing از 7.4.20 به 7.4.23 ارتقا یافتند. ممیزی اولیه ۶ هشدار در این سه بسته داشت؛ ممیزی lock نهایی بدون advisory است. |
| انتشار | اسکریپت cPanel وابستگی‌های Composer را نصب نمی‌کرد و در شکست انتشار برنامه را خودکار فعال می‌کرد. نصب از lock، کنترل platform، restart صف و توقف در maintenance هنگام شکست اضافه شد. |
| پاک‌سازی | Dockerfile، compose، تنظیمات docker، dockerignore و اسکریپت‌های backup/restore وابسته به Docker حذف شدند. مرحله ساخت کانتینر برنامه از CI حذف شد. راهنماها با استقرار cPanel هماهنگ شدند. |

Flutter/Dart در فایل‌های رهگیری‌شده مخزن پیدا نشد. frontend فعال Laravel شامل Blade، Inertia/Vue و Vite حفظ شد. سرویس‌های دیتابیس CI برای تست integration حفظ شدند؛ حذف آن‌ها پوشش لازم را از بین می‌برد. راهنمای backup اکنون از ابزار پشتیبان‌گیری هاست استفاده می‌کند؛ اسکریپت‌های حذف‌شده خارج از Docker قابل استفاده نبودند.

## فایل‌های اصلی

- `app/Services/Auth/LogOtpSender.php`، `config/auth_otp.php` و نمونه‌های env: کنترل صریح ثبت OTP.
- `app/Services/Web/BuildingUserImportService.php` و `BuildingWorkbookService.php`: استفاده مشترک از تطبیق موبایل و بررسی هویت روابط.
- `composer.json` و `composer.lock`: حذف Sail و اصلاح وابستگی‌های دارای advisory.
- `scripts/deploy-cpanel.sh`: نصب و کنترل وابستگی‌ها و رفتار شکست انتشار.
- `.github/workflows/ci.yml`: حذف ساخت Docker برنامه و افزودن تست رفتار انتشار.
- `tests/Unit/Auth/LocalOtpLoggingTest.php`، `tests/Feature/Web/BuildingUserImportTest.php` و `tests/Deployment/deploy-cpanel.test.mjs`: رگرسیون اصلاحات.
- README و راهنماهای production/architecture: هم‌راستایی مستندات با مسیر پشتیبانی‌شده.

## اعتبارسنجی

محیط محلی PHP 8.4.27؛ دیتابیس آزمایشی SQLite:

- PHPUnit: **۵۷ تست، ۲۸۶ assertion، موفق** در LocalOtpLoggingTest، BuildingUserImportTest، BuildingSetupWebTest، OtpSecurityTest و FinalCriticalRouteDispatchTest.
- Node: **۳ تست انتشار موفق**؛ ترتیب نصب قبل از migration و فعال‌نشدن برنامه پس از شکست install/migrate با executable آزمایشی بررسی شد. این تست اجرای واقعی روی cPanel نیست.
- Composer validate --strict، check-platform-reqs --no-dev و بازسازی autoload/package discovery موفق.
- composer audit --locked: بدون advisory و بدون بسته abandoned.
- بررسی syntax اسکریپت، ساختار YAML، Pint فایل‌های تغییرکرده و git diff --check موفق.

پس از حذف Sail، manifest محلی قدیمی به provider حذف‌شده ارجاع می‌داد؛ اجرای چرخه استاندارد Composer dump-autoload آن را بازسازی کرد و اجرای نهایی تست‌ها موفق شد. انتشار باید با Composer scripts فعال انجام شود.

## محدودیت‌ها و کنترل انتشار

- سرور production، MySQL/MariaDB واقعی، صف، scheduler، SMTP، قاصدک و PSP در این نوبت تست زنده نشده‌اند؛ نتیجه قبلی خطای دعوت پیامکی با این تغییرات تأیید یا رفع‌شده محسوب نمی‌شود.
- رابط کاربری و کل تست‌های مالی مجدداً اجرا نشدند؛ منطق مالی و UI تغییر نکرده‌اند.
- از دیتابیس و storage قبل از انتشار backup تأییدشده تهیه شود. تغییر schema یا حذف داده در این اصلاحات وجود ندارد.
- پس از انتشار، health و queue/scheduler و دانلود نمونه/پیش‌نمایش اکسل روی میزبان بررسی شوند.
- این نوبت فقط commit محلی آماده شده است؛ انتشار remote به دلیل توقف بررسی خودکار مجوز، نیازمند تأیید صریح مقصد `bestagroup/buildino` است.
