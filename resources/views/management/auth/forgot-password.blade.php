<!doctype html>
<html lang="fa" dir="rtl" class="light-style" data-theme="theme-default" data-assets-path="{{ asset('assets/') }}/">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">

    <title>فراموشی رمز عبور | Buildino</title>
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

<body class="login-page auth-recovery-page materialize-auth">
<div class="auth-recovery-shell">
    <main class="auth-recovery-card">
        <a
            href="{{ route('login') }}"
            class="auth-recovery-brand"
        >
            <div class="brand-mark">
                <span>B</span>
            </div>

            <div>
                <strong>Buildino</strong>
                <span>بازیابی دسترسی مدیریتی</span>
            </div>
        </a>

        <div class="auth-recovery-icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'key',
                    'size' => 24,
                ]
            )
        </div>

        <div class="auth-recovery-heading">
            <span class="eyebrow">
                PASSWORD RECOVERY
            </span>

            <h1>فراموشی رمز عبور</h1>

            <p>
                شماره موبایل یا ایمیل حساب را وارد کنید. اگر حساب فعال باشد و
                ایمیل تأییدشده داشته باشد، لینک تعیین رمز جدید برای آن ارسال می‌شود.
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert--success login-alert">
                <strong>درخواست ثبت شد</strong>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert--danger login-alert">
                <strong>اطلاعات را بررسی کنید</strong>

                @foreach ($errors->all() as $error)
                    <span>{{ $error }}</span>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.email') }}"
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
                        placeholder="0912... یا name@example.com"
                        autocomplete="username"
                        dir="ltr"
                        required
                        autofocus
                    >
                </div>
            </label>

            <button
                class="login-submit"
                type="submit"
            >
                <span>ارسال لینک بازنشانی</span>

                @include(
                    'management.partials.icon',
                    [
                        'name' => 'arrow-left',
                        'size' => 18,
                    ]
                )
            </button>
        </form>

        <div class="auth-recovery-note">
            @include(
                'management.partials.icon',
                [
                    'name' => 'shield',
                    'size' => 16,
                ]
            )

            <span>
                برای جلوگیری از شناسایی حساب‌های سامانه، نتیجه درخواست برای حساب موجود و ناموجود یکسان نمایش داده می‌شود.
            </span>
        </div>

        <a
            href="{{ route('login') }}"
            class="auth-back-link"
        >
            بازگشت به صفحه ورود
        </a>
    </main>
</div>
<script
    src="{{ asset('js/buildino-foundation.js') }}"
></script>

<script src="{{ asset('js/buildino-materialize.js') }}" defer></script>

</body>
</html>
