<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >
    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        ورود به پرتال Buildino
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-fonts.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ config('management_ui.libraries.bootstrap.css') }}"
        integrity="{{ config('management_ui.libraries.bootstrap.css_integrity') }}"
        crossorigin="anonymous"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-foundation.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-portal.css') }}"
    >
</head>

<body class="portal-login-body">
<div class="portal-login-shell">
    <section class="portal-login-showcase">
        <div class="portal-login-showcase__content">
            <div class="portal-login-brand">
                <span>B</span>

                <div>
                    <strong>
                        Buildino
                    </strong>

                    <small>
                        Resident & Provider Portal
                    </small>
                </div>
            </div>

            <div class="portal-login-copy">
                <span>
                    زندگی و خدمات ساختمان در یک جا
                </span>

                <h1>
                    پرتال شخصی
                    <em>Buildino</em>
                </h1>

                <p>
                    صورتحساب، کیف پول، مهمان، رزرو امکانات،
                    درخواست خدمات، پشتیبانی و پنل ارائه‌دهندگان خدمات.
                </p>
            </div>

            <div class="portal-login-features">
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'wallet',
                            'size' => 20,
                        ]
                    )
                    <span>
                        مالی و کیف پول
                    </span>
                </div>

                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'calendar',
                            'size' => 20,
                        ]
                    )
                    <span>
                        رزرو و امکانات
                    </span>
                </div>

                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'tools',
                            'size' => 20,
                        ]
                    )
                    <span>
                        خدمات و پشتیبانی
                    </span>
                </div>
            </div>
        </div>
    </section>

    <main class="portal-login-form-area">
        <div class="portal-login-card">
            <div class="portal-login-card__heading">
                <span>
                    ورود کاربران
                </span>

                <h2>
                    خوش آمدید
                </h2>

                <p>
                    مالک، مستأجر، ساکن یا ارائه‌دهنده خدمات می‌تواند
                    با رمز عبور یا کد پیامکی وارد حساب Buildino شود.
                </p>
            </div>

            @php
                $portalOtpMobile = session(
                    'buildino.web_otp.portal.mobile'
                );
                $activeAuthMethod = old(
                    'auth_method',
                    session('auth_method', 'password')
                );
            @endphp

            @if ($errors->any())
                <div class="alert alert-danger portal-login-alert">
                    @foreach ($errors->all() as $error)
                        <div>
                            {{ $error }}
                        </div>
                    @endforeach
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success portal-login-alert">
                    {{ session('status') }}
                </div>
            @endif

            <div
                class="portal-auth-method-switch"
                data-auth-switch
                data-active-method="{{ $activeAuthMethod }}"
                role="tablist"
                aria-label="روش ورود"
            >
                <button
                    type="button"
                    data-auth-method="password"
                    role="tab"
                >
                    رمز عبور
                </button>

                <button
                    type="button"
                    data-auth-method="otp"
                    role="tab"
                >
                    کد پیامکی
                </button>
            </div>

            <div
                data-auth-panel="password"
                @hidden($activeAuthMethod === 'otp')
            >
                <form
                    method="POST"
                    action="{{ route('portal.login.store') }}"
                    class="portal-login-form"
                    data-buildino-submit
                >
                    @csrf

                <label>
                    <span>
                        موبایل یا ایمیل
                    </span>

                    <input
                        type="text"
                        name="login"
                        value="{{ old('login') }}"
                        placeholder="0912... یا name@example.com"
                        autocomplete="username"
                        dir="ltr"
                        required
                        autofocus
                    >
                </label>

                <label>
                    <span>
                        رمز عبور
                    </span>

                    <input
                        type="password"
                        name="password"
                        placeholder="رمز عبور"
                        autocomplete="current-password"
                        dir="ltr"
                        required
                    >
                </label>

                <div class="portal-login-meta">
                    <label class="portal-remember">
                        <input
                            type="hidden"
                            name="remember"
                            value="0"
                        >

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        <span>
                            مرا به خاطر بسپار
                        </span>
                    </label>

                    <a href="{{ route('password.request') }}">
                        فراموشی رمز عبور
                    </a>
                </div>

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        ورود به پرتال
                    </button>
                </form>
            </div>

            <div
                class="portal-otp-login-panel"
                data-auth-panel="otp"
                @hidden($activeAuthMethod !== 'otp')
            >
                @if (session('otp_status'))
                    <div class="portal-otp-status">
                        {{ session('otp_status') }}
                    </div>
                @endif

                @if ($portalOtpMobile)
                    <div data-otp-verification>
                        <div class="portal-otp-copy">
                            <strong>کد پیامک‌شده را وارد کنید</strong>
                            <span>
                                کد ورود به شماره
                                <b dir="ltr">{{ $portalOtpMobile }}</b>
                                ارسال شده است.
                            </span>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('portal.login.otp.verify') }}"
                            class="portal-login-form"
                            data-buildino-submit
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="auth_method"
                                value="otp"
                            >

                            <label>
                                <span>کد تأیید</span>
                                <input
                                    class="portal-otp-code"
                                    type="text"
                                    name="code"
                                    value="{{ old('code') }}"
                                    autocomplete="one-time-code"
                                    inputmode="numeric"
                                    pattern="[0-9۰-۹٠-٩]{4,8}"
                                    placeholder="_ _ _ _ _ _"
                                    dir="ltr"
                                    maxlength="8"
                                    required
                                >
                            </label>

                            <button
                                type="submit"
                                class="portal-primary-button"
                            >
                                تأیید کد و ورود
                            </button>
                        </form>

                        <div class="portal-otp-actions">
                            <form
                                method="POST"
                                action="{{ route('portal.login.otp.request') }}"
                                data-buildino-submit
                            >
                                @csrf
                                <input
                                    type="hidden"
                                    name="mobile"
                                    value="{{ $portalOtpMobile }}"
                                >
                                <input
                                    type="hidden"
                                    name="auth_method"
                                    value="otp"
                                >
                                <button type="submit">ارسال دوباره کد</button>
                            </form>

                            <button
                                type="button"
                                data-otp-change
                            >
                                تغییر شماره موبایل
                            </button>
                        </div>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('portal.login.otp.request') }}"
                    class="portal-login-form portal-otp-request-form"
                    data-otp-request-form
                    data-buildino-submit
                    @hidden((bool) $portalOtpMobile)
                >
                    @csrf
                    <input
                        type="hidden"
                        name="auth_method"
                        value="otp"
                    >

                    <label>
                        <span>شماره موبایل حساب</span>
                        <input
                            type="tel"
                            name="mobile"
                            value="{{ old('mobile', $portalOtpMobile) }}"
                            placeholder="09123456789"
                            autocomplete="tel"
                            inputmode="numeric"
                            dir="ltr"
                            maxlength="16"
                            required
                        >
                    </label>

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        دریافت کد ورود
                    </button>
                </form>
            </div>

            <div class="portal-login-register">
                <span>
                    حساب پرتال ندارید؟ به‌عنوان مالک، ساکن یا ارائه‌دهنده خدمات ثبت‌نام کنید.
                </span>

                <a href="{{ route('register', ['persona' => 'tenant']) }}">
                    ایجاد حساب کاربری
                </a>
            </div>

            <div class="portal-login-management">
                <span>
                    مدیر ساختمان یا کاربر سازمانی هستید؟
                </span>

                <a href="{{ route('login') }}">
                    ورود به پنل مدیریتی
                </a>
            </div>
        </div>
    </main>
</div>
<script
    src="{{ asset('js/buildino-foundation.js') }}"
></script>
<script
    src="{{ asset('js/buildino-auth-login.js') }}"
    defer
></script>
</body>
</html>
