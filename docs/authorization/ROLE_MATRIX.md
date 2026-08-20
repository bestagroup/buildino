# Buildino Role & Scope Matrix

## اصل طراحی

Buildino از `user_role_assignments` با Scope پلی‌مورفیک استفاده می‌کند.

```text
Global
Complex
Building
```

و برای Owner / Tenant، منبع اصلی دسترسی:

```text
UnitOwnership
UnitOccupancy
```

است.

## نقش‌ها

| Role | Scope | Management Web | هدف |
|---|---|---:|---|
| superadmin | Global | بله | کنترل کامل سامانه |
| complex_manager | Complex | بله | مدیریت ساختمان‌های یک مجتمع |
| building_manager | Building | بله | مدیریت کامل روزمره یک ساختمان |
| finance_manager | Building | بله | مالی، شارژ، Wallet، قبض و گزارش |
| operator | Building | بله | مهمان، رزرو، خدمات و عملیات |
| support_agent | Building | بله | تیکت و SLA |
| service_provider | Building | خیر | پرتال اختصاصی ارائه‌دهنده؛ فقط کارهای تخصیص‌یافته، کیف پول و تسویه خودش |
| owner | UnitOwnership | خیر | مالک؛ دسترسی Relation-driven |
| tenant | UnitOccupancy | خیر | مستأجر؛ دسترسی Relation-driven |

## قواعد امنیتی مهم

### SuperAdmin
Assignment:

```text
scope_type = NULL
scope_id   = NULL
```

تمام Permissionهای موجود را دریافت می‌کند.

### Complex Manager

Assignment روی `Complex`.

PermissionChecker برای ساختمان‌های همان مجتمع، Scope مجتمع را معتبر می‌داند.

مثال:

```text
Complex A
├── Building A ✅
└── Building B ✅

Complex B
└── Building C ❌
```

### Building Manager

Assignment مستقیم روی `Building`.

```text
Building A ✅
Building B ❌
Building C ❌
```

### Owner / Tenant

به آن‌ها Permission مدیریتی Building داده نمی‌شود.

علت:

```text
Owner Unit 101
```

نباید با Role اشتباه بتواند داده:

```text
Unit 102
Unit 103
کل Building
```

را مشاهده کند.

در نتیجه:

```text
Owner  → UnitOwnership
Tenant → UnitOccupancy
```

منبع دسترسی است.

## فایل مرجع

Role Matrix در:

```text
config/role_matrix.php
```

قرار دارد.

Seeder:

```text
database/seeders/RoleMatrixSeeder.php
```

سناریوی تست:

```text
database/seeders/AccessScenarioSeeder.php
```

Command:

```bash
php artisan buildino:access-scenario
```

## حساب‌های سناریوی تست

رمز تمام حساب‌های زیر:

```text
Demo@1405
```

| Persona | Mobile | Email |
|---|---|---|
| SuperAdmin | 09121110000 | role.superadmin@buildino.local |
| Complex Manager | 09121110001 | role.complex@buildino.local |
| Building Manager | 09121110002 | role.building@buildino.local |
| Finance Manager | 09121110003 | role.finance@buildino.local |
| Operator | 09121110004 | role.operator@buildino.local |
| Support Agent | 09121110005 | role.support@buildino.local |
| Service Provider | 09121110006 | role.provider@buildino.local |
| Owner | 09121110007 | role.owner@buildino.local |
| Tenant | 09121110008 | role.tenant@buildino.local |

مسیر ورود نقش‌های مدیریتی (`superadmin` تا `support_agent`):

```text
/management/login
```

مسیر ورود مالک، مستأجر و ارائه‌دهنده خدمات:

```text
/portal/login
```

پس از ورود، Middleware و Scope همان حساب مقصد مجاز را انتخاب می‌کند؛
ورود پرتال به معنی دسترسی به پنل مدیریت و داده سایر واحدها یا ارائه‌دهندگان نیست.

## داده Scope تست

```text
مجتمع آزمایشی دسترسی
├── ساختمان آلفا
│   ├── واحد 101 → Owner + کیف پول + صورتحساب قابل پرداخت
│   ├── واحد 102 → Tenant + کیف پول + صورتحساب قابل پرداخت
│   ├── کار خدماتی Owner → Service Provider
│   └── کار مقایسه‌ای Tenant → Operator (خارج از کارتابل Provider)
└── ساختمان بتا

مجتمع خارج از محدوده
└── ساختمان گاما
```

### انتظار

Complex Manager:

```text
ساختمان آلفا ✅
ساختمان بتا ✅
ساختمان گاما ❌
```

Building Manager / Finance / Operator / Support:

```text
ساختمان آلفا ✅
ساختمان بتا ❌
ساختمان گاما ❌
```

Owner / Tenant:

```text
Management Login ❌
Resident Portal  → فاز بعدی
```
