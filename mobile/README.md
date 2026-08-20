# Buildino Mobile

Flutter client برای قرارداد `/api/v1` Buildino. توکن Sanctum در secure storage نگهداری می‌شود و base URL فقط از build-time define دریافت می‌شود.

## آماده‌سازی platformها

در محیطی که Flutter SDK نصب است، یک‌بار از داخل این پوشه اجرا کنید:

```bash
flutter create --platforms=android,ios --org com.buildino --project-name buildino_mobile .
flutter pub get
flutter analyze
flutter test
```

حداقل Android SDK برای `flutter_secure_storage` نسخه جاری 23 است؛ پس از ایجاد platform، مقدار `minSdk` را کنترل کنید.

## اجرا

```bash
flutter run \
  --dart-define=BUILDINO_API_BASE_URL=https://staging.example.com \
  --dart-define=BUILDINO_APP_VERSION=1.0.0
```

از HTTP فقط برای `localhost` در debug استفاده کنید. برای release، API باید HTTPS باشد.

## build release

```bash
flutter build appbundle --release \
  --dart-define=BUILDINO_API_BASE_URL=https://app.example.com \
  --dart-define=BUILDINO_APP_VERSION=1.0.0

flutter build ipa --release \
  --dart-define=BUILDINO_API_BASE_URL=https://app.example.com \
  --dart-define=BUILDINO_APP_VERSION=1.0.0
```

Signing key، provisioning profile، FCM platform files و store accountها باید توسط مالک محصول تأمین شوند و نباید در repository commit شوند.
