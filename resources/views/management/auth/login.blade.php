<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">

    <title>ورود به پنل مدیریتی Buildino</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-fonts.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ \App\Support\UiAssets::url('bootstrap.css') }}"
        crossorigin="anonymous"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-foundation.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-management.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-design-system.css') }}"
    >
    <link rel="stylesheet" href="{{ asset('css/buildino-login.css') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/buildino-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-brand.css') }}">
</head>

<body class="login-page">
@include('shared.login-backdrop')
<div class="login-shell">
    <main class="login-form-area">
        <div class="login-card">
            

            @include('shared.login-brand')
            <div class="login-card__header">
                <span class="eyebrow">
                    پنل مدیریتی
                </span>

                <h2>خوش آمدید</h2>

                <p>
                    با رمز عبور یا کد یک‌بارمصرف پیامکی وارد شوید.
                </p>
            </div>

            @php
                $managementOtpMobile = session(
                    'buildino.web_otp.management.mobile'
                );
                $activeAuthMethod = old(
                    'auth_method',
                    session('auth_method', 'password')
                );
            @endphp

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

            <div
                class="auth-method-switch"
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
                    @include(
                        'management.partials.icon',
                        ['name' => 'lock', 'size' => 16]
                    )
                    <span>رمز عبور</span>
                </button>

                <button
                    type="button"
                    data-auth-method="otp"
                    role="tab"
                >
                    @include(
                        'management.partials.icon',
                        ['name' => 'key', 'size' => 16]
                    )
                    <span>کد پیامکی</span>
                </button>
            </div>

            <div
                data-auth-panel="password"
                @if ($activeAuthMethod === 'otp') hidden @endif
            >
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
                            مرا به خاطر بسپار
                        </span>
                    </label>

                    <a
                        class="forgot-password-link"
                        href="{{ route('password.request') }}"
                    >
                        فراموشی رمز عبور؟
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
            </div>

            <div
                class="otp-login-panel"
                data-auth-panel="otp"
                @if ($activeAuthMethod !== 'otp') hidden @endif
            >
                @if (session('otp_status'))
                    <div class="otp-login-status" data-otp-status @if ($errors->has('mobile')) hidden @endif>
                        @include(
                            'management.partials.icon',
                            ['name' => 'shield', 'size' => 16]
                        )
                        <span>{{ session('otp_status') }}</span>
                    </div>
                @endif

                @if ($managementOtpMobile)
                    <div
                        class="otp-login-verification"
                        data-otp-verification
                        @if ($errors->has('mobile')) hidden @endif
                    >
                        <div class="otp-login-copy">
                            <strong>کد پیامک‌شده را وارد کنید</strong>
                            <span>
                                کد ورود به شماره
                                <b dir="ltr">{{ $managementOtpMobile }}</b>
                                ارسال شده است.
                            </span>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('management.login.otp.verify') }}"
                            class="login-form"
                            data-buildino-submit
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="auth_method"
                                value="otp"
                            >

                            <label class="auth-field">
                                <span>کد تأیید</span>

                                <div class="auth-input auth-input--otp">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'key', 'size' => 18]
                                    )

                                    <input
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
                                </div>
                            </label>

                            <button class="login-submit" type="submit">
                                <span>تأیید کد و ورود</span>
                                @include(
                                    'management.partials.icon',
                                    ['name' => 'arrow-left', 'size' => 18]
                                )
                            </button>
                        </form>

                        <div class="otp-login-actions">
                            <form
                                method="POST"
                                action="{{ route('management.login.otp.request') }}"
                                data-buildino-submit
                            >
                                @csrf
                                <input
                                    type="hidden"
                                    name="mobile"
                                    value="{{ $managementOtpMobile }}"
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
                    action="{{ route('management.login.otp.request') }}"
                    class="login-form otp-request-form"
                    data-otp-request-form
                    data-buildino-submit
                    @if ((bool) $managementOtpMobile && ! $errors->has('mobile')) hidden @endif
                >
                    @csrf
                    <input
                        type="hidden"
                        name="auth_method"
                        value="otp"
                    >

                    <label class="auth-field">
                        <span>شماره موبایل حساب</span>

                        <div class="auth-input">
                            @include(
                                'management.partials.icon',
                                ['name' => 'user', 'size' => 18]
                            )

                            <input
                                type="tel"
                                name="mobile"
                                value="{{ old('mobile', $managementOtpMobile) }}"
                                autocomplete="tel"
                                inputmode="numeric"
                                placeholder="09123456789"
                                dir="ltr"
                                maxlength="16"
                                required
                            >
                        </div>
                    </label>

                    <button class="login-submit" type="submit">
                        <span>دریافت کد ورود</span>
                        @include(
                            'management.partials.icon',
                            ['name' => 'arrow-left', 'size' => 18]
                        )
                    </button>
                </form>
            </div>

            <div class="login-separator" aria-hidden="true">یا</div>
            <div class="login-registration-cta">
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'user-plus',
                            'size' => 18,
                        ]
                    )

                    <span>
                        <strong>هنوز حساب ندارید؟</strong>
                    </span>
                </div>

                <a href="{{ route('register', ['persona' => 'building_manager']) }}">
                    ساخت حساب جدید
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
@include('shared.core-auth')
</body>
</html>
