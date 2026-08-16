# نقشه تکمیل Buildino

این سند backlog اجرایی سه محصول «داشبورد مدیریت»، «پرتال کاربران» و
«اپلیکیشن کاربران» است. ترتیب مراحل بر اساس ریسک امنیتی، وابستگی فنی و
ارزش قابل تحویل تعیین شده است.

| فاز | محدوده | خروجی قابل تحویل | معیار خروج | وضعیت |
|---:|---|---|---|---|
| 0 | پاک‌سازی snapshot | حذف secret و runtime artifact از بسته، یک منبع OpenAPI و Postman | اسکن بسته هیچ `.env`، log، session، cache یا سند API قدیمی نشان ندهد | تکمیل شده |
| 1 | تثبیت زیرساخت | رفع binding دیتاتیبل، route تکراری، scheduler، Vite entry و release gate | نصب تمیز، build موفق، route و schedule یکتا، تست‌ها سبز | پیاده‌سازی کامل؛ تست runtime لازم است |
| 2 | امنیت scope و فایل | محدودسازی query/create به ساختمان و واحد، target allowlist، دانلود مجاز و اسکن فایل | تست عبور و منع دسترسی بین دو ساختمان برای منابع مالی، اسناد و فایل | پیاده‌سازی کامل؛ تست runtime لازم است |
| 3 | هویت و ضدسوءاستفاده | SMS واقعی OTP، دعوت قابل قبول، Socialite، Captcha و تشخیص device | هیچ OTP در log، اتصال حساب امن، rate-limit و تست abuse | برنامه‌ریزی شده |
| 4 | ساختار ساختمان | UI کامل پارکینگ، انباری، قوانین، شماره ضروری، اسناد و صورت‌جلسات | عملیات CRUD و audit از هر سه سطح مرتبط قابل انجام باشد | برنامه‌ریزی شده |
| 5 | موتور شارژ | فرمول هزینه مشترک/اختصاصی، دوره شارژ، صدور گروهی و تعدیل | بازتولیدپذیری صورتحساب و تست rounding/تفکیک واحدها | برنامه‌ریزی شده |
| 6 | پرداخت واقعی | PSP منتخب، callback/webhook امن، QR، پرداخت دستی و reconciliation | idempotency، verify مستقل، مغایرت‌گیری و تست sandbox PSP | برنامه‌ریزی شده |
| 7 | اقساط و رسید | برنامه اقساط، دیرکرد/بخشودگی، رسید PDF و تاریخچه تغییر | جمع اقساط با صورتحساب برابر و هر تغییر audit شود | برنامه‌ریزی شده |
| 8 | صندوق و حسابداری | صندوق ساختمان، سرفصل‌ها، درآمد/هزینه و گزارش گردش | posting دوبل، عدم مانده منفی غیرمجاز و reconciliation سبز | برنامه‌ریزی شده |
| 9 | رزرو کامل | ظرفیت، محدودیت واحد، لغو/تعمیرات، پرداخت و اعلان تغییر | جلوگیری از هم‌پوشانی همزمان و تست race condition | برنامه‌ریزی شده |
| 10 | اعلان هدفمند | مخاطب‌گذاری مجتمع/ساختمان/نقش، ترجیح کانال و push | delivery قابل رهگیری و عدم ارسال خارج scope | برنامه‌ریزی شده |
| 11 | درخواست و پشتیبانی | SLA، ارجاع، پیام، ضمیمه، وضعیت و رضایت | زمان پاسخ/حل قابل گزارش و فایل‌ها مجاز باشند | برنامه‌ریزی شده |
| 12 | UI مدیریت | design system، DataTables server-side همه فهرست‌ها، فرم و تقویم جلالی | بدون فهرست 100تایی client-side، responsive و keyboard-accessible | برنامه‌ریزی شده |
| 13 | UI پرتال | داشبورد نقش‌محور مالک/مستأجر/خدمات، پرداخت و رزرو روان | آزمون مسیرهای اصلی روی موبایل وب و desktop | برنامه‌ریزی شده |
| 14 | قرارداد اپلیکیشن | bootstrap پایدار، pagination، error envelope و version policy | تست contract و backward compatibility برای نسخه پشتیبانی‌شده | برنامه‌ریزی شده |
| 15 | Swagger و Postman | شرح/نمونه/خطا برای هر endpoint و تست موردبه‌مورد | تعداد route، operation و request برابر و audit خودکار سبز | برنامه‌ریزی شده |
| 16 | گزارش و خروجی | درآمد، هزینه، بدهکاران، پرداخت، صندوق و export صفی | scope صحیح، سقف ردیف، پاک‌سازی فایل و تست داده واقعی | برنامه‌ریزی شده |
| 17 | وفاداری | امتیاز، خوش‌حسابی، ledger امتیاز و rule versioning | هر امتیاز قابل توضیح/برگشت و بدون محاسبه تکراری باشد | برنامه‌ریزی شده |
| 18 | مشاهده‌پذیری | health، heartbeat، queue metrics، audit activity و alert | failure سناریوهای صف/درگاه/ذخیره‌سازی هشدار بدهد | برنامه‌ریزی شده |
| 19 | QA و انتشار | تست end-to-end، performance، accessibility، backup/restore و runbook | release gate production و سناریوی restore تایید شود | برنامه‌ریزی شده |

## ترتیب اجرای فاز جاری

1. اجرای Composer روی محیط PHP 8.2+ و بازتولید package discovery.
2. اجرای PHPUnit و اصلاح هر regression ناشی از route/scheduler/provider.
3. اجرای `npm install` و `npm run build` و ثبت lockfile.
4. تولید مجدد Swagger و مقایسه عملیات با route manifest و Postman.
5. اجرای اسکن بسته و تحویل snapshot پاک.

## سیاست کتابخانه‌های رابط و هویت

| کتابخانه | نسخه پایه سازگار | سیاست استفاده |
|---|---:|---|
| Bootstrap | 5.3.8 | مبنای grid، فرم، modal و دسترس‌پذیری هر دو پنل |
| SweetAlert2 | 11.26.25 | confirm، feedback و toast؛ بدون `alert()` در جریان نهایی |
| DataTables | 2.3.8 | renderer سمت مرورگر با pagination/filter سمت سرور |
| Yajra DataTables | 12.x | query builder scope-aware؛ بدون materialize کردن collection بزرگ |
| Morilog Jalali | 3.5.x | تبدیل سمت سرور؛ ذخیره زمان همچنان UTC |
| Laravel Socialite | 5.29.x | فقط login/link؛ عدم اعتماد به email بدون تایید provider |
| Matomo Device Detector | 6.5.x | metadata حداقلی و بدون fingerprint دائمی کاربر |
| Mews Captcha | 3.5.x | فرم‌های عمومی پرریسک؛ OTP همچنان rate-limited |

سه وابستگی آخر بعد از نصب با Composer، بررسی extensionها و ثبت دقیق در
`composer.lock` فعال می‌شوند.
