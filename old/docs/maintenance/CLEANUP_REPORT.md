# گزارش پاکسازی نهایی Buildino

## نتیجه

این بسته از ZIP نهایی کاربر استخراج و با رویکرد محافظه‌کارانه پاکسازی شده است. هیچ Migration مالی/کیف‌پول/پرداخت، Service هسته مالی، Policy فعال، Route فعال، Model فعال یا تست دامنه‌ای حذف نشده است.

## آمار

- فایل‌های PHP پس از پاکسازی: **699**
- فایل‌های حذف‌شده: **53**
- قراردادهای API در Manifest موجود: **237**
- مسیرهای OpenAPI: **152**
- Requestهای Postman: **237**

## کنترل‌های استاتیک پس از پاکسازی

- PHP syntax errors: **0**
- PSR-4 mismatches: **0**
- Duplicate FQCNs: **0**
- Missing App imports: **0**
- Factory → missing Model: **0**
- Duplicate Schema::create: **0**
- JSON parse errors: **0**

## تغییرات کوچک تنظیمات

- کلید منسوخ `TRUST_REQUEST_ID_HEADER` از `config/api_security.php` حذف شد؛ Middleware قدیمی آن دیگر استفاده نمی‌شد.
- fallback مربوط به `job_batches` و `failed_jobs` در `config/queue.php` از `sqlite` به `mysql` همسو با DB پیش‌فرض پروژه تغییر کرد؛ در تست‌ها `DB_CONNECTION=sqlite` همچنان override می‌شود.

## فایل‌های حذف‌شده

### نسخه‌های قدیمی و فایل‌های راهنما/نمونه‌ی منسوخ
- `bootstrap/app.final.php`
- `bootstrap/providers.final.php`
- `bootstrap/middleware_registration.php`
- `routes/api.final.php`
- `routes/auth_v1.final.php`
- `routes/api_v1_security_example.php`

### فایل‌های Runtime/Generated
- `bootstrap/cache/packages.php`
- `bootstrap/cache/services.php`
- `database/database.sqlite`

### آرتیفکت‌های نمونه‌ی تست/Pest
- `tests/Pest.php`
- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`

### Middleware/Concernهای جایگزین‌شده
- `app/Http/Middleware/ForceJsonResponse.php`
- `app/Http/Middleware/RequestIdMiddleware.php`
- `tests/Unit/Middleware/RequestIdMiddlewareTest.php`
- `app/Models/Concerns/UsesApiAuthentication.php`
- `app/Models/Concerns/HasApiTokens.php`

### Factoryهای ناسازگار با مدل/Schema نهایی
- `database/factories/ResidentHistoryFactory.php`
- `database/factories/BuildingDocumentFactory.php`
- `database/factories/UnitGuestFactory.php`
- `database/factories/UnitDocumentFactory.php`
- `database/factories/UnitResidentFactory.php`

### کلاس‌های Legacy بدون Reference
- `app/Enums/RoleScopeType.php`
- `app/Support/ApiResponse.php`
- `app/Http/Requests/StoreUserRequest.php`
- `app/Http/Requests/UpdateUserRequest.php`
- `app/Http/Requests/UpdateSystemSettingRequest.php`
- `app/Http/Requests/UpdateFacilityReservationRequest.php`
- `app/Http/Requests/UpdateBuildingSubscriptionRequest.php`
- `app/Http/Requests/StoreBuildingSubscriptionRequest.php`
- `app/Http/Requests/StoreSystemSettingRequest.php`
- `app/Http/Requests/UpdatePaymentRequest.php`
- `app/Http/Resources/V1/UserResource.php`
- `app/Http/Resources/V1/MeetingMinuteResource.php`
- `app/Http/Resources/V1/AnnouncementResource.php`
- `app/Http/Resources/V1/ServiceRequestResource.php`
- `app/Http/Resources/V1/BuildingIncomeResource.php`
- `app/Http/Resources/V1/DocumentRecordResource.php`
- `app/Http/Resources/V1/SupportTicketResource.php`
- `app/Http/Resources/V1/BuildingExpenseResource.php`
- `app/Actions/SupportTicket/CreateSupportTicket.php`
- `app/Actions/SupportTicket/UpdateSupportTicket.php`
- `app/Actions/Payment/UpdatePayment.php`
- `app/Actions/Payment/CreatePayment.php`
- `app/Actions/ServiceRequest/UpdateServiceRequest.php`
- `app/Actions/ServiceRequest/CreateServiceRequest.php`
- `app/Actions/UnitInvoice/CreateUnitInvoice.php`
- `app/Actions/UnitInvoice/UpdateUnitInvoice.php`
- `app/Actions/BuildingFacility/UpdateBuildingFacility.php`
- `app/Actions/BuildingFacility/CreateBuildingFacility.php`
- `app/Actions/FacilityReservation/CreateFacilityReservation.php`
- `app/Actions/FacilityReservation/ApproveFacilityReservation.php`
- `app/Http/Controllers/Api/V1/UnitOccupancyOperationController.php`

## موارد عمداً نگه‌داشته‌شده

- تمام Migrationهای نهایی و Patch Migrationهای مالی/Runtime.
- تمام تست‌های Domain/Feature/Regression واقعی، از جمله Route reboot و Final Completion.
- `docs/api` و `postman` چون خروجی رسمی Contract فعلی پروژه‌اند.
- OpenAPI Attribute/L5-Swagger code؛ با وجود همپوشانی با Runtime Contract Export، حذف آن بدون تصمیم معماری جداگانه انجام نشد.
- `PostmanTestSeeder` چون فقط در `local/testing` مجاز است و برای تست API مفید است.

## نکته مهم درباره بسته آپلودشده

ZIP ورودی شامل پوشه‌های کد اختصاصی پروژه است، اما فایل‌های استاندارد ریشه‌ی یک پروژه مستقل Laravel مانند `artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `.env.example`, `public/` و `storage/` در آن وجود نداشتند. بنابراین این خروجی **Clean Application Source** است و باید روی Root واقعی Laravel پروژه نگه‌داری/اعمال شود. برای جلوگیری از حدس‌زدن نسخه دقیق وابستگی‌های واقعی، فایل‌های ریشه از نو ساخته نشده‌اند.

## Gate پیشنهادی روی Root واقعی پروژه

```bash
composer dump-autoload
php artisan optimize:clear
php artisan test
php artisan api:contract:audit --json
php artisan system:integrity-audit
php artisan wallet-accounting:audit
php artisan payments:gateway-audit
php artisan release:gate
```

پس از سبز بودن این Gateها، همین بسته مبنای Freeze و مستندسازی نهایی قرار می‌گیرد.
