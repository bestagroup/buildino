<!doctype html>
<html lang="fa" dir="rtl" class="light-style" data-theme="theme-default" data-assets-path="{{ asset('assets/') }}/">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">

    <title>ورود به پنل مدیریتی Buildino</title>
    <!-- Materialize RTL theme extracted from the supplied UI reference -->
    <link id="template-core-css" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}">
    <link id="template-theme-css" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/materialize-demo.css') }}">


    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-fonts.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-foundation.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-management.css') }}"
    >
    <link rel="stylesheet" href="{{ asset('css/buildino-materialize.css') }}">
</head>

<body class="login-page buildino-materio-shell materialize-auth">
<div class="login-shell">
    <section class="login-visual">
        <div class="login-visual__mesh"></div>

        <div class="login-visual__content">
            <div class="showcase-brand">
                <div class="brand-mark brand-mark--large">
                    <span>B</span>
                </div>

                <div>
                    <strong>Buildino</strong>
                    <span>Smart Building Management</span>
                </div>
            </div>

            <div class="login-visual__headline">
                <span class="eyebrow eyebrow--light">
                    مدیریت هوشمند، تصمیم‌گیری دقیق
                </span>

                <h1>
                    همه‌چیز برای مدیریت ساختمان،
                    <em>در یک مرکز واحد.</em>
                </h1>

                <p>
                    از ساختار مجتمع و ساکنین تا کیف پول، شارژ،
                    رزرو امکانات، خدمات، پشتیبانی و گزارش‌های مدیریتی.
                </p>
            </div>

            <div class="login-feature-grid">
                <article>
                    <div class="login-feature-icon">
                        @include(
                            'management.partials.icon',
                            ['name' => 'building']
                        )
                    </div>
                    <strong>ساختار یکپارچه</strong>
                    <span>مجتمع، ساختمان، واحد و ساکنین</span>
                </article>

                <article>
                    <div class="login-feature-icon">
                        @include(
                            'management.partials.icon',
                            ['name' => 'wallet']
                        )
                    </div>
                    <strong>شفافیت مالی</strong>
                    <span>Wallet، Invoice، Payment و Ledger</span>
                </article>

                <article>
                    <div class="login-feature-icon">
                        @include(
                            'management.partials.icon',
                            ['name' => 'shield']
                        )
                    </div>
                    <strong>دسترسی کنترل‌شده</strong>
                    <span>Role، Permission و Scope واقعی</span>
                </article>
            </div>
        </div>

        <div class="login-visual__footer">
            <span>Buildino Backend/API v1.0</span>
            <span>Secure • Scoped • Auditable</span>
        </div>
    </section>

    <main class="login-form-area">
        <div class="login-card">
            <div class="login-card__mobile-brand">
                <div class="brand-mark">
                    <span>B</span>
                </div>
                <strong>Buildino</strong>
            </div>

            <div class="login-card__header">
                <span class="eyebrow">
                    پنل مدیریتی
                </span>

                <h2>خوش آمدید</h2>

                <p>
                    برای ورود، شماره موبایل یا ایمیل و رمز عبور خود را وارد کنید.
                </p>
            </div>

            @if (session('status'))
                <div class="alert alert--success login-alert">
                    <strong>انجام شد</strong>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert--danger login-alert">
                    <strong>ورود انجام نشد</strong>

                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('management.login.store') }}"
                class="login-form"
                data-buildino-submit
            >
                @csrf

                <label class="auth-field">
                    <span>شماره موبایل یا ایمیل</span>

                    <div class="auth-input">
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'user',
                                'size' => 18,
                            ]
                        )

                        <input
                            type="text"
                            name="login"
                            value="{{ old('login') }}"
                            autocomplete="username"
                            placeholder="0912... یا name@example.com"
                            dir="ltr"
                            required
                            autofocus
                        >
                    </div>
                </label>

                <label class="auth-field">
                    <span>رمز عبور</span>

                    <div class="auth-input">
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'lock',
                                'size' => 18,
                            ]
                        )

                        <input
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="رمز عبور"
                            dir="ltr"
                            required
                        >
                    </div>
                </label>

                <div class="login-form__meta">
                    <label class="remember-row">
                        <input
                            type="hidden"
                            name="remember"
                            value="0"
                        >

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                        >

                        <span>
                            مرا در این دستگاه به خاطر بسپار
                        </span>
                    </label>

                    <a
                        class="forgot-password-link"
                        href="{{ route('password.request') }}"
                    >
                        رمز عبور را فراموش کرده‌اید؟
                    </a>
                </div>

                <button
                    class="login-submit"
                    type="submit"
                >
                    <span>ورود به داشبورد</span>

                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'arrow-left',
                            'size' => 18,
                        ]
                    )
                </button>
            </form>

            <div class="login-security-note">
                @include(
                    'management.partials.icon',
                    [
                        'name' => 'shield',
                        'size' => 16,
                    ]
                )

                <span>
                    محتوای پنل بر اساس نقش و محدوده دسترسی حساب شما نمایش داده می‌شود.
                </span>
            </div>
        </div>
    </main>
</div>
<script
    src="{{ asset('js/buildino-foundation.js') }}"
></script>

<script src="{{ asset('js/buildino-materialize.js') }}" defer></script>

</body>
</html>
