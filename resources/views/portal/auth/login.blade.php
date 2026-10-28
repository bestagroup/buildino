<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/buildino-logo.svg') }}">
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
        href="{{ \App\Support\UiAssets::url('bootstrap.css') }}"
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
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-design-system.css') }}"
    >
    <link rel="stylesheet" href="{{ asset('css/buildino-login.css') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/buildino-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-brand.css') }}">
</head>

<body class="portal-login-body">
@include('shared.login-backdrop')
<div class="portal-login-shell">
    <main class="portal-login-form-area">
        <div class="portal-login-card">
            @include('shared.login-brand')
            <div class="portal-login-card__heading">
                <span>
                    ورود کاربران
                </span>

                <h2>
                    خوش آمدید
                </h2>

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
                    @include('management.partials.icon', ['name' => 'mail', 'size' => 18])
                    <span>رمز عبور</span>
                </button>

                <button
                    type="button"
                    data-auth-method="otp"
                    role="tab"
                >
                    @include('management.partials.icon', ['name' => 'message', 'size' => 18])
                    <span>کد پیامکی</span>
                </button>
            </div>

            <div
                data-auth-panel="password"
                @if ($activeAuthMethod === 'otp') hidden @endif
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

                    <div class="auth-input">
                        @include('management.partials.icon', ['name' => 'user', 'size' => 20])
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
                    </div>
                </label>

                <label>
                    <span>
                        رمز عبور
                    </span>

                    <div class="auth-input">
                        @include('management.partials.icon', ['name' => 'lock', 'size' => 20])
                        <input
                            type="password"
                            name="password"
                            placeholder="رمز عبور"
                            autocomplete="current-password"
                            dir="ltr"
                            required
                        >
                    </div>
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
                        <span>ورود به پرتال</span>
                        @include('management.partials.icon', ['name' => 'arrow-left', 'size' => 18])
                    </button>
                </form>
            </div>

            <div
                class="portal-otp-login-panel"
                data-auth-panel="otp"
                @if ($activeAuthMethod !== 'otp') hidden @endif
            >
                @if (session('otp_status'))
                    <div class="portal-otp-status" data-otp-status @if ($errors->has('mobile')) hidden @endif>
                        {{ session('otp_status') }}
                    </div>
                @endif

                @if ($portalOtpMobile)
                    <div data-otp-verification @if ($errors->has('mobile')) hidden @endif>
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
                                <div class="auth-input">
                                    @include('management.partials.icon', ['name' => 'key', 'size' => 20])
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
                                </div>
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
                    @if ((bool) $portalOtpMobile && ! $errors->has('mobile')) hidden @endif
                >
                    @csrf
                    <input
                        type="hidden"
                        name="auth_method"
                        value="otp"
                    >

                    <label>
                        <span>شماره موبایل حساب</span>
                        <div class="auth-input">
                            @include('management.partials.icon', ['name' => 'user', 'size' => 20])
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
                        </div>
                    </label>

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        دریافت کد ورود
                    </button>
                </form>
            </div>

            <div class="login-separator" aria-hidden="true">یا</div>
            <div class="portal-login-register">
                <div>
                    @include('management.partials.icon', ['name' => 'user-plus', 'size' => 20])
                    <span>
                        <strong>حساب پرتال ندارید؟</strong>
                        <small>به‌عنوان مالک، ساکن یا ارائه‌دهنده خدمات ثبت‌نام کنید.</small>
                    </span>
                </div>
                <a href="{{ route('register', ['persona' => 'tenant']) }}">ایجاد حساب کاربری</a>
            </div>

            <div class="portal-login-management">
                <span>
                    مدیر ساختمان یا کاربر سازمانی هستید؟
                </span>

                <a href="{{ route('login') }}">
                    @include('management.partials.icon', ['name' => 'settings', 'size' => 16])
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
@include('shared.core-auth')
</body>
</html>
