<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>ورود به پنل مدیریتی Buildino</title>
    <link rel="stylesheet" href="{{ asset('css/buildino-management.css') }}">
</head>
<body class="login-page">
    <div class="login-shell">
        <section class="login-showcase">
            <div class="login-showcase__content">
                <div class="showcase-brand">
                    <span class="brand-mark brand-mark--large">B</span>
                    <div>
                        <strong>Buildino</strong>
                        <span>Smart Building Management</span>
                    </div>
                </div>

                <h1>مرکز مدیریت یکپارچه ساختمان</h1>
                <p>
                    مشاهده وضعیت مالی، رزرو امکانات، خدمات، پشتیبانی،
                    اعلان‌ها و شاخص‌های کلیدی ساختمان از یک داشبورد واحد.
                </p>

                <div class="showcase-grid">
                    <div>
                        <strong>مالی شفاف</strong>
                        <span>Wallet، Invoice و Ledger در یک جریان قابل کنترل</span>
                    </div>
                    <div>
                        <strong>عملیات یکپارچه</strong>
                        <span>ساکنین، مهمان، Facility، Service و Support</span>
                    </div>
                    <div>
                        <strong>کنترل دسترسی</strong>
                        <span>Permission و Scope در سطح پلتفرم و ساختمان</span>
                    </div>
                    <div>
                        <strong>آماده بهره‌برداری</strong>
                        <span>Health، Audit، Queue و Production Readiness</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="login-card-wrap">
            <div class="login-card">
                <div class="login-card__header">
                    <span class="eyebrow">Management Dashboard</span>
                    <h2>ورود به پنل مدیریتی</h2>
                    <p>با شماره موبایل یا ایمیل و رمز عبور وارد شوید.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert--danger">
                        <strong>ورود انجام نشد</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('management.login.store') }}"
                    class="login-form"
                >
                    @csrf

                    <label class="field">
                        <span>شماره موبایل یا ایمیل</span>
                        <input
                            type="text"
                            name="login"
                            value="{{ old('login') }}"
                            autocomplete="username"
                            placeholder="0912... یا example@domain.com"
                            required
                            autofocus
                        >
                    </label>

                    <label class="field">
                        <span>رمز عبور</span>
                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="رمز عبور"
                            required
                        >
                    </label>

                    <label class="remember-row">
                        <input type="hidden" name="remember" value="0">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                        >
                        <span>مرا به خاطر بسپار</span>
                    </label>

                    <button class="primary-button primary-button--wide" type="submit">
                        ورود به داشبورد
                    </button>
                </form>

                <div class="login-card__footer">
                    دسترسی این پنل بر اساس Role، Permission و Scope واقعی Buildino کنترل می‌شود.
                </div>
            </div>
        </section>
    </div>
</body>
</html>
