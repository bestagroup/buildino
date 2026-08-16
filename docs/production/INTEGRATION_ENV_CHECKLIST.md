# Production Integration Environment Checklist

## Core

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://...
QUEUE_CONNECTION=database|redis|sqs
CACHE_STORE=database|redis
```

## FCM

```text
PUSH_PROVIDER=fcm_v1
FCM_PROJECT_ID=
FCM_CREDENTIALS_PATH=
# OR:
FCM_CREDENTIALS_JSON_BASE64=
FCM_TIMEOUT=15
FCM_ANDROID_PRIORITY=high
FCM_APNS_PRIORITY=10
```

## SMS

```text
SMS_PROVIDER=http
SMS_HTTP_ENDPOINT=https://...
SMS_HTTP_TOKEN=
SMS_HTTP_TOKEN_HEADER=Authorization
SMS_HTTP_TOKEN_PREFIX=Bearer 
SMS_HTTP_TIMEOUT=10
```

Adapt `recipient_field`, `message_field`, and extra JSON in `config/notifications.php`
if the chosen SMS vendor uses different field names.

## Mail

Configure a real Laravel mail driver/provider.

## Payments

```text
PAYMENT_GATEWAY_DEFAULT=generic
PAYMENT_GATEWAY_CALLBACK_BASE_URL=https://...

PAYMENT_GATEWAY_GENERIC_ENABLED=true
PAYMENT_GATEWAY_GENERIC_REQUEST_URL=https://...
PAYMENT_GATEWAY_GENERIC_VERIFY_URL=https://...
PAYMENT_GATEWAY_GENERIC_MERCHANT_ID=
PAYMENT_GATEWAY_GENERIC_SECRET=<32+ chars>
PAYMENT_GATEWAY_GENERIC_WEBHOOK_SECRET=<32+ chars>
PAYMENT_GATEWAY_GENERIC_REDIRECT_TEMPLATE=
PAYMENT_GATEWAY_GENERIC_SIGN_REQUESTS=true
PAYMENT_GATEWAY_WEBHOOK_MAX_SKEW=300
```

## Mobile

```text
MOBILE_DEFAULT_CURRENCY=IRR
MOBILE_MIN_BUILD_ANDROID=1
MOBILE_MIN_BUILD_IOS=1
MOBILE_STORE_URL_ANDROID=
MOBILE_STORE_URL_IOS=
```

## Gate

```bash
php artisan optimize:clear
php artisan config:cache
php artisan system:production-audit --strict
php artisan payments:gateway-audit
php artisan release:gate --production
```
