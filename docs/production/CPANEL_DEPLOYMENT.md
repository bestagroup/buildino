# Buildino deployment on cPanel shared hosting

این راهنما برای هاست اشتراکی cPanel است که Docker و دسترسی root در اختیار ندارد.

## پیش‌نیازها

- Repository پروژه روی هاست clone شده باشد.
- فایل `.env` یا `.env.production` واقعی سرور خارج از Git نگهداری شود.
- PHP CLI نسخه سازگار با پروژه در دسترس باشد.
- برای قابلیت دعوت پیامکی، تنظیمات زیر روی محیط production وجود داشته باشد:

```env
SMS_PROVIDER=ghasedak
GHASEDAK_ENDPOINT=https://api.ghasedaksms.com/v2/send/verify
GHASEDAK_API_KEY=YOUR_REAL_KEY
GHASEDAK_TEMPLATE=YOUR_APPROVED_TEMPLATE
GHASEDAK_TYPE=1
GHASEDAK_TIMEOUT=30

UNIT_INVITATION_CODE_DIGITS=8
UNIT_INVITATION_MESSAGE="شما برای عضویت در یک واحد در سامانه بیلدینو دعوت شده‌اید."
```

## روش پیشنهادی با Terminal cPanel

از ریشه پروژه اجرا شود:

```bash
sh scripts/deploy-cpanel.sh
```

این اسکریپت به ترتیب:

1. شاخه `main` را از GitHub دریافت می‌کند.
2. فقط fast-forward pull انجام می‌دهد.
3. برنامه را موقتاً در maintenance قرار می‌دهد.
4. migrationهای جدید را با `--force` اجرا می‌کند.
5. cacheهای Laravel را پاک و دوباره تولید می‌کند.
6. برنامه را از maintenance خارج می‌کند.

## اگر PHP در cPanel با نام دیگری است

در بسیاری از هاست‌های cPanel مسیر PHP CLI چیزی شبیه `/usr/local/bin/ea-php82` است. در این صورت:

```bash
PHP_BIN=/usr/local/bin/ea-php82 sh scripts/deploy-cpanel.sh
```

ابتدا مسیر صحیح را با یکی از دستورات زیر پیدا کنید:

```bash
which php
php -v
ls -1 /usr/local/bin/ea-php* 2>/dev/null
```

## اگر Update from Remote را از cPanel انجام داده‌اید

اگر سورس قبلاً از طریق Git Version Control به‌روزرسانی شده و فقط post-deploy لازم است:

```bash
SKIP_GIT=1 sh scripts/deploy-cpanel.sh
```

## اگر cPanel نمی‌تواند به GitHub متصل شود

ابتدا در Terminal بررسی کنید:

```bash
git remote -v
git ls-remote origin
```

اگر SSH روی پورت 22 بسته است، GitHub را از SSH روی 443 استفاده کنید:

```text
Host github.com
    HostName ssh.github.com
    User git
    Port 443
    IdentityFile ~/.ssh/id_ed25519
    IdentitiesOnly yes
```

فایل بالا در `~/.ssh/config` قرار می‌گیرد و مجوز آن باید 600 باشد:

```bash
chmod 600 ~/.ssh/config
ssh -T git@github.com
```

## انتشار این نسخه دعوت پیامکی

این نسخه migration جدید دارد؛ بنابراین بعد از دریافت سورس اجرای migration الزامی است:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

اگر PHP پیش‌فرض هاست نسخه صحیح نیست، به‌جای `php` از مسیر PHP انتخاب‌شده در cPanel استفاده کنید.
