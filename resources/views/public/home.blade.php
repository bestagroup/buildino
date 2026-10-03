<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Buildino؛ سامانه یکپارچه مدیریت ساختمان، شارژ، رزرو امکانات، خدمات و ارتباط با ساکنین.">
    <title>Buildino | مدیریت هوشمند ساختمان</title>
    <link rel="stylesheet" href="{{ asset('css/buildino-fonts.css') }}">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(circle_at_20%_20%,rgba(16,185,129,.18),transparent_30%),radial-gradient(circle_at_80%_20%,rgba(14,165,233,.16),transparent_28%)]"></div>

    <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
        <a href="{{ route('public.home') }}" class="text-xl font-black tracking-tight">Buildino</a>
        <nav class="flex items-center gap-3 text-sm">
            <a class="rounded-xl border border-white/10 px-4 py-2 hover:bg-white/5" href="{{ route('portal.login') }}">ورود ساکنین</a>
            <a class="rounded-xl bg-emerald-500 px-4 py-2 font-bold text-slate-950 hover:bg-emerald-400" href="{{ route('login') }}">پنل مدیریت</a>
        </nav>
    </header>

    <main>
        <section class="mx-auto grid max-w-7xl gap-12 px-6 pb-20 pt-16 lg:grid-cols-[1.15fr_.85fr] lg:items-center lg:px-8 lg:pt-24">
            <div>
                <span class="inline-flex rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300">سامانه یکپارچه مدیریت ساختمان</span>
                <h1 class="mt-6 max-w-3xl text-4xl font-black leading-[1.35] md:text-6xl">
                    مدیریت ساختمان، مالی و خدمات در یک سامانه یکپارچه
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-8 text-slate-300 md:text-lg">
                    از مدیریت واحدها و ساکنین تا شارژ، صورتحساب، کیف پول، رزرو امکانات،
                    درخواست خدمات، پشتیبانی و گزارش‌های مدیریتی؛ Buildino جریان‌های اصلی
                    ساختمان را در یک بستر امن و قابل توسعه متمرکز می‌کند.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a class="rounded-2xl bg-emerald-500 px-6 py-3 font-extrabold text-slate-950 hover:bg-emerald-400" href="{{ route('register') }}">ایجاد حساب</a>
                    <a class="rounded-2xl border border-white/10 px-6 py-3 font-bold hover:bg-white/5" href="{{ route('portal.login') }}">ورود به پرتال</a>
                </div>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-white/[.04] p-5 shadow-2xl shadow-emerald-950/30 backdrop-blur">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['عنوان' => 'مالی و شارژ', 'متن' => 'فرمول شارژ، صورتحساب، اقساط، پرداخت و کیف پول'],
                        ['عنوان' => 'امکانات', 'متن' => 'زمان‌بندی، ظرفیت، رزرو، پرداخت و محدودیت استفاده'],
                        ['عنوان' => 'ساکنین', 'متن' => 'مالکیت، سکونت، دعوت امن، مهمان و تردد'],
                        ['عنوان' => 'خدمات', 'متن' => 'درخواست خدمت، ارائه‌دهنده، تیکت و SLA'],
                        ['عنوان' => 'گزارش', 'متن' => 'گزارش‌های مالی و عملیاتی با خروجی‌های استاندارد'],
                        ['عنوان' => 'اشتراک', 'متن' => 'پلن، دوره آزمایشی، تمدید، Grace و کنترل مجوز'],
                    ] as $feature)
                        <article class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                            <h2 class="font-extrabold text-white">{{ $feature['عنوان'] }}</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-400">{{ $feature['متن'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="border-y border-white/10 bg-white/[.025]">
            <div class="mx-auto grid max-w-7xl gap-6 px-6 py-16 md:grid-cols-3 lg:px-8">
                <div><strong class="text-2xl font-black text-emerald-300">امن و مبتنی بر محدوده دسترسی</strong><p class="mt-2 text-sm leading-7 text-slate-400">کنترل دسترسی در سطح پلتفرم، مجتمع، ساختمان و روابط واحد.</p></div>
                <div><strong class="text-2xl font-black text-emerald-300">معماری API محور</strong><p class="mt-2 text-sm leading-7 text-slate-400">Laravel REST API، Sanctum، کلاینت وب و موبایل با قرارداد نسخه‌بندی‌شده.</p></div>
                <div><strong class="text-2xl font-black text-emerald-300">آماده رشد</strong><p class="mt-2 text-sm leading-7 text-slate-400">Queue، Redis، گزارش صفی، health check و معماری ماژولار برای توسعه تدریجی.</p></div>
            </div>
        </section>
    </main>

    <footer class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-10 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <span>© {{ now()->year }} Buildino</span>
        <span>سامانه مدیریت امکانات و خدمات ساختمانی</span>
    </footer>
</body>
</html>
