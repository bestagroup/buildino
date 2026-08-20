# Production Integration Environment Checklist

مرجع کامل متغیرها `.env.production.example` است. مقدار واقعی هیچ secretی نباید در Git ثبت شود.

## Core

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://...
APP_KEY=<persistent key from secret manager>
DB_CONNECTION=mysql
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
```

## فایل و ClamAV

```text
FILE_DISK=private
FILE_SCAN_ENABLED=true
FILE_SCAN_DRIVER=clamd_tcp
FILE_SCAN_HOST=clamav
FILE_SCAN_PORT=3310
```

Compose یک clamd sidecar با volume امضای ویروس فراهم می‌کند. در استقرار غیرکانتینری می‌توان `FILE_SCAN_DRIVER=binary` و `FILE_SCAN_BINARY=clamdscan` را استفاده کرد.

## FCM

```text
PUSH_PROVIDER=fcm_v1
FCM_PROJECT_ID=
FCM_CREDENTIALS_PATH=/absolute/path/outside/repository.json
# یا FCM_CREDENTIALS_JSON_BASE64=
```

## SMS و Mail

```text
SMS_PROVIDER=http
SMS_HTTP_ENDPOINT=https://...
SMS_HTTP_TOKEN=
SMS_HTTP_SENDER=Buildino

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
```

## Payments

```text
PAYMENT_GATEWAY_DEFAULT=generic
PAYMENT_GATEWAY_CALLBACK_BASE_URL=https://...
PAYMENT_GATEWAY_GENERIC_ENABLED=true
PAYMENT_GATEWAY_GENERIC_REQUEST_URL=https://...
PAYMENT_GATEWAY_GENERIC_VERIFY_URL=https://...
PAYMENT_GATEWAY_GENERIC_MERCHANT_ID=
PAYMENT_GATEWAY_GENERIC_SECRET=<32+ random chars>
PAYMENT_GATEWAY_GENERIC_WEBHOOK_SECRET=<32+ random chars>
PAYMENT_GATEWAY_GENERIC_SIGN_REQUESTS=true
PAYMENT_GATEWAY_WEBHOOK_MAX_SKEW=300
```

## Mobile contract

```text
MOBILE_MIN_SUPPORTED_VERSION=1.0.0
MOBILE_LATEST_VERSION=1.0.0
MOBILE_MAINTENANCE_MODE=false
MOBILE_MAINTENANCE_MESSAGE=
```

## Runtime

```text
NOTIFICATION_QUEUE=notifications
REPORT_EXPORT_QUEUE=reports
HEALTH_REQUIRED_QUEUES=default,reports,notifications
```

## Gate

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan system:dispatch-queue-heartbeats
php artisan system:scheduler-heartbeat
php artisan release:gate --production
```

Gate production باید در همان شبکه و با همان secretها/queueها اجرا شود؛ سبزکردن مصنوعی heartbeat در محیط دیگری اعتبار ندارد.
