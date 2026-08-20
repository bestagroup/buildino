# Security Rotation Checklist

این چک‌لیست برای حالتی است که `.env` یا archiveهای قدیمی در تاریخچه Git قرار گرفته‌اند. اجرای آن نیازمند مالک repository و دسترسی سرویس‌های بیرونی است.

1. repository را موقتاً read-only و همه cloneها/CIها را شناسایی کنید.
2. secretهای PSP، SMS، SMTP، FCM، DB، object storage و tokenهای CI را revoke/rotate کنید.
3. درباره rotation `APP_KEY` تصمیم بگیرید؛ این کار encrypted data و sessionهای قبلی را تحت تأثیر قرار می‌دهد و باید با migration داده هماهنگ باشد.
4. با ابزار تأییدشده سازمان، `.env` و archiveهای secretدار را از تمام تاریخچه و tagها حذف کنید.
5. force-push کنترل‌شده انجام و cloneهای قدیمی را باطل کنید.
6. secret scanning را روی تاریخچه بازنویسی‌شده اجرا کنید.
7. credentialهای جدید فقط در secret manager ثبت و deploymentها دوباره ساخته شوند.
8. رخداد، دامنه افشا، زمان rotation و تأیید سرویس‌ها در incident record ثبت شود.

حذف فایل از commit جاری به‌تنهایی secret قبلی را از history یا cloneها حذف نمی‌کند.
