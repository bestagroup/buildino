<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">

    <title>تأیید شماره موبایل Buildino</title>

    <link rel="stylesheet" href="{{ asset('css/buildino-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-foundation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-management.css') }}">
</head>

<body class="login-page registration-verify-page">
<main class="registration-verify-shell">
    <section class="registration-verify-card">
        <a
            class="auth-recovery-brand"
            href="{{ route('register') }}"
        >
            <div class="brand-mark">
                <span>B</span>
            </div>

            <span>
                <strong>Buildino</strong>
                <small>تأیید ثبت‌نام</small>
            </span>
        </a>

        <div class="registration-verify-icon">
            @include(
                'management.partials.icon',
                ['name' => 'lock', 'size' => 28]
            )
        </div>

        <div class="registration-verify-heading">
            <span class="eyebrow">مرحله ۲ از ۲</span>
            <h1>شماره موبایل را تأیید کنید</h1>
            <p>
                کد یک‌بارمصرف ارسال‌شده به
                <b dir="ltr">{{ $maskedMobile }}</b>
                را برای تکمیل حساب «{{ $personaLabel }}» وارد کنید.
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert--success login-alert registration-alert">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert--danger login-alert registration-alert">
                @foreach ($errors->all() as $error)
                    <span>{{ $error }}</span>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('register.verify.store') }}"
            class="registration-verify-form"
            data-buildino-submit
        >
            @csrf

            <label class="registration-field">
                <span>کد تأیید</span>
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
                    autofocus
                >
            </label>

            <button class="login-submit" type="submit">
                <span>تأیید و ورود به حساب</span>
                @include(
                    'management.partials.icon',
                    ['name' => 'arrow-left', 'size' => 18]
                )
            </button>
        </form>

        <div class="registration-verify-actions">
            <form
                method="POST"
                action="{{ route('register.otp.resend') }}"
                data-buildino-submit
            >
                @csrf
                <button type="submit">ارسال دوباره کد</button>
            </form>

            <a href="{{ route('register') }}">
                اصلاح اطلاعات ثبت‌نام
            </a>
        </div>

        <div class="login-security-note registration-verify-note">
            @include(
                'management.partials.icon',
                ['name' => 'shield', 'size' => 16]
            )
            <span>
                حساب و فضای کاری فقط پس از تأیید موفق این کد ساخته می‌شود.
            </span>
        </div>
    </section>
</main>

<script src="{{ asset('js/buildino-foundation.js') }}"></script>
</body>
</html>
