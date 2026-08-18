# Buildino — Production Communication, PSP & Mobile Readiness

## 1. Firebase Cloud Messaging (HTTP v1)

Production:

```env
PUSH_PROVIDER=fcm_v1

FCM_PROJECT_ID=your-firebase-project-id

# Recommended: absolute path outside public/ and outside the repository
FCM_CREDENTIALS_PATH=/secure/buildino/firebase-service-account.json

# Alternative for secret managers / container environments:
# FCM_CREDENTIALS_JSON_BASE64=<base64-service-account-json>

FCM_TIMEOUT=15
FCM_ANDROID_PRIORITY=high
FCM_APNS_PRIORITY=10
```

Do not commit the Firebase service-account JSON.

Buildino uses:

```text
OAuth 2.0 service-account JWT
→ short-lived access token
→ FCM HTTP v1 messages:send
```

If Firebase returns:

```text
UNREGISTERED
```

the invalid push token is detached from `user_devices`.

Raw device tokens are not persisted in `notification_logs`. Provider diagnostics contain hashes only.

---

## 2. SMS

The existing provider-neutral HTTP SMS driver remains the production integration point:

```env
SMS_PROVIDER=http
SMS_HTTP_ENDPOINT=https://sms-provider.example/api/messages
SMS_HTTP_TOKEN=...
SMS_HTTP_TOKEN_HEADER=Authorization
SMS_HTTP_TOKEN_PREFIX="Bearer "
SMS_HTTP_SENDER=Buildino
SMS_HTTP_TIMEOUT=15
```

Map the payload in `HttpSmsSender` if the selected Iranian/international provider requires a different contract.

---

## 3. Mail

Production must not use the `log` or `array` mailer.

Example:

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME=Buildino
```

---

## 4. Payment Gateway

The current gateway layer already provides:

```text
initiate
verify
callback
webhook
HMAC signature
timestamp skew protection
event id / replay protection
idempotency
audit
```

Production configuration:

```env
PAYMENT_GATEWAY_DEFAULT=generic
PAYMENT_GATEWAY_CALLBACK_BASE_URL=https://app.example.com

PAYMENT_GATEWAY_GENERIC_ENABLED=true
PAYMENT_GATEWAY_GENERIC_REQUEST_URL=https://psp.example/request
PAYMENT_GATEWAY_GENERIC_VERIFY_URL=https://psp.example/verify
PAYMENT_GATEWAY_GENERIC_MERCHANT_ID=...
PAYMENT_GATEWAY_GENERIC_SECRET=...
PAYMENT_GATEWAY_GENERIC_WEBHOOK_SECRET=...
PAYMENT_GATEWAY_GENERIC_SIGN_REQUESTS=true
```

Never enable the `fake` gateway in production.

When the final PSP is selected, map the provider-specific field names to the existing adapter configuration instead of implementing invoice/wallet logic inside the gateway adapter.

---

## 5. Queue / Worker

Notification delivery is queue-oriented. Recommended:

```env
QUEUE_CONNECTION=redis
NOTIFICATION_QUEUE=notifications
```

or:

```env
QUEUE_CONNECTION=database
NOTIFICATION_QUEUE=notifications
```

Run a persistent worker under Supervisor/systemd/container orchestration.

Example:

```bash
php artisan queue:work --queue=notifications,default --tries=3 --timeout=90
```

Also run the Laravel scheduler continuously.

---

## 6. Mobile / Flutter Authentication

Existing endpoints:

```text
POST /api/v1/auth/password/login
POST /api/v1/auth/otp/request
POST /api/v1/auth/otp/login

GET  /api/v1/auth/me
POST /api/v1/auth/logout
POST /api/v1/auth/logout-all
```

Login remains backward compatible.

Flutter can optionally send:

```json
{
  "device_name": "Hossein Pixel",
  "device_id": "stable-installation-uuid",
  "platform": "android",
  "push_token": "fcm-registration-token"
}
```

`device_id` should be a stable app-installation identifier, not IMEI or another hardware identifier.

When `device_id` is present, Buildino automatically syncs `user_devices`.

On device logout:

```json
{
  "device_id": "stable-installation-uuid"
}
```

the current Sanctum token is revoked and that device registry row is released so another account can safely use the same app installation.

---

## 7. Mobile Bootstrap

New endpoint:

```text
GET /api/v1/app/bootstrap
```

Headers:

```text
Authorization: Bearer <sanctum-token>
```

Returns the canonical resident bootstrap payload containing:

```text
authenticated user
relationship-derived personas: owner and/or occupant
one context per related unit
merged owner/occupant relationship flags per context
context-local charges.view and wallet.view capabilities
a deterministic suggested_context, or null when no context exists
```

Only active, date-valid ownerships and occupancies belonging to the authenticated user are considered. Management/provider roles do not create resident contexts.

---

## 8. Production Audit

Run before every production release:

```bash
php artisan system:production-audit --strict
```

The audit now checks:

```text
APP_KEY
APP_DEBUG
APP_URL HTTPS
persistent queue
persistent cache
runtime tables
required PHP extensions

default PSP exists
PSP enabled warning
fake PSP prohibition
callback HTTPS
generic PSP credentials
generic PSP request/verify HTTPS

SMS provider
SMS endpoint HTTPS
SMS token warning

Push provider
FCM project id
FCM credentials
HTTP Push HTTPS

real mail transport
notification queue
runtime health
```

A critical finding exits non-zero.

With `--strict`, warnings also exit non-zero.

---

## 9. Recommended release commands

```bash
composer dump-autoload
php artisan optimize:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan system:production-audit --strict
php artisan test
```
