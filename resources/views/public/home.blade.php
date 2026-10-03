<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Buildino؛ سامانه یکپارچه مدیریت ساختمان، امور مالی، رزرو امکانات و خدمات ساکنان">
    <title>Buildino | مدیریت یکپارچه ساختمان</title>
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
</head>
<body>
<header class="public-nav">
    <div class="container public-nav__inner">
        <a class="brand" href="{{ route('public.home') }}" aria-label="Buildino">
            <span class="brand__mark">B</span>
            <span>Buildino</span>
        </a>

        <nav class="public-nav__links" aria-label="منوی اصلی">
            <a href="#features">امکانات</a>
            <a href="#platform">سامانه</a>
            <a href="#start">شروع استفاده</a>
        </nav>

        <div class="public-nav__actions">
            <a class="button button--ghost" href="{{ route('portal.login') }}">ورود کاربران</a>
            <a class="button button--primary" href="{{ route('login') }}">ورود مدیریت</a>
        </div>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero__grid">
            <div>
                <span class="eyebrow">مدیریت ساختمان، ساده و قابل‌کنترل</span>
                <h1>همه فرایندهای ساختمان در یک سامانه یکپارچه</h1>
                <p>
                    از مدیریت واحدها، شارژ و پرداخت تا رزرو امکانات، درخواست خدمات،
                    اطلاع‌رسانی و گزارش‌های مدیریتی؛ Buildino برای ارتباط منظم میان
                    مدیر ساختمان، مالک، مستأجر و ارائه‌دهنده خدمت طراحی شده است.
                </p>

                <div class="hero__actions" id="start">
                    <a class="button button--primary" href="{{ route('register', ['persona' => 'building_manager']) }}">
                        ایجاد فضای کاری ساختمان
                    </a>
                    <a class="button button--ghost" href="{{ route('portal.login') }}">
                        ورود به پرتال ساکنان
                    </a>
                </div>

                <div class="hero__meta">
                    <span>دسترسی نقش‌محور</span>
                    <span>گزارش و سابقه عملیات</span>
                    <span>پشتیبانی وب و موبایل</span>
                </div>
            </div>

            <div class="product-card" aria-label="نمای سامانه">
                <div class="product-card__top">
                    <div class="product-card__title">
                        <strong>داشبورد ساختمان</strong>
                        <small>نمای یکپارچه عملیات روزانه</small>
                    </div>
                    <span class="status-pill">فعال</span>
                </div>

                <div class="product-card__body">
                    <div class="stat-grid">
                        <div class="stat"><span>صورتحساب‌های جاری</span><strong>۲۴</strong></div>
                        <div class="stat"><span>رزروهای امروز</span><strong>۸</strong></div>
                        <div class="stat"><span>درخواست‌های باز</span><strong>۱۲</strong></div>
                        <div class="stat"><span>اعلان‌های جدید</span><strong>۵</strong></div>
                    </div>

                    <div class="activity">
                        <div class="activity__item">
                            <span class="activity__icon">✓</span>
                            <span>پرداخت شارژ واحد با موفقیت ثبت شد.</span>
                        </div>
                        <div class="activity__item">
                            <span class="activity__icon">↗</span>
                            <span>درخواست خدمات جدید به ارائه‌دهنده ارجاع شد.</span>
                        </div>
                        <div class="activity__item">
                            <span class="activity__icon">◷</span>
                            <span>رزرو سالن اجتماعات برای فردا ثبت شده است.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="features">
        <div class="container">
            <div class="section__header">
                <span class="eyebrow">هسته عملیاتی</span>
                <h2>فرایندهای اصلی ساختمان، بدون پراکندگی ابزارها</h2>
                <p>
                    اطلاعات و عملیات هر ساختمان در محدوده دسترسی همان ساختمان نگهداری
                    می‌شود و کاربران فقط قابلیت‌های مرتبط با نقش خود را مشاهده می‌کنند.
                </p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <span class="feature-card__icon">01</span>
                    <h3>ساختار ساختمان و ساکنان</h3>
                    <p>مجتمع، بلوک، طبقه، واحد، مالکیت، سکونت، پارکینگ، انباری و دعوت امن کاربران.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-card__icon">02</span>
                    <h3>شارژ و صورتحساب</h3>
                    <p>فرمول شارژ، دوره مالی، صورتحساب واحد، اقساط، پرداخت و سابقه قابل پیگیری.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-card__icon">03</span>
                    <h3>کیف پول و تراکنش</h3>
                    <p>کیف پول ساختمان، واحد و کاربر با ثبت Ledger و کنترل عملیات مالی.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-card__icon">04</span>
                    <h3>رزرو امکانات</h3>
                    <p>تعریف امکانات، سانس، ظرفیت، هزینه، قوانین رزرو و زمان‌های مسدودشده.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-card__icon">05</span>
                    <h3>خدمات و پشتیبانی</h3>
                    <p>درخواست خدمات، ارائه‌دهندگان، تیکت، پیام، وضعیت و سابقه رسیدگی.</p>
                </article>
                <article class="feature-card">
                    <span class="feature-card__icon">06</span>
                    <h3>گزارش و کنترل مدیریتی</h3>
                    <p>گزارش‌های مالی و عملیاتی، خروجی فایل، Audit Trail و کنترل سلامت سرویس.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="platform">
        <div class="container">
            <div class="cta">
                <div class="cta__inner">
                    <div>
                        <h2>برای مدیریت ساختمان یک فضای کاری مستقل بسازید</h2>
                        <p>ثبت‌نام با تأیید شماره موبایل و شروع دوره آزمایشی برای ساختمان جدید.</p>
                    </div>
                    <a class="button button--primary" href="{{ route('register', ['persona' => 'building_manager']) }}">
                        شروع ثبت‌نام
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="public-footer">
    <div class="container public-footer__inner">
        <span>© {{ now()->year }} Buildino. کلیه حقوق محفوظ است.</span>
        <div class="public-footer__links">
            <a href="{{ route('public.privacy') }}">حریم خصوصی</a>
            <a href="{{ route('public.terms') }}">شرایط استفاده</a>
        </div>
    </div>
</footer>
</body>
</html>
