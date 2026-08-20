<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">

    <title>تعیین رمز عبور جدید | Buildino</title>

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
</head>

<body class="login-page auth-recovery-page">
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
                <span>تعیین رمز عبور جدید</span>
            </div>
        </a>

        <div class="auth-recovery-icon auth-recovery-icon--success">
            @include(
                'management.partials.icon',
                [
                    'name' => 'lock',
                    'size' => 24,
                ]
            )
        </div>

        <div class="auth-recovery-heading">
            <span class="eyebrow">
                NEW PASSWORD
            </span>

            <h1>رمز عبور جدید</h1>

            <p>
                یک رمز عبور جدید برای حساب خود تعیین کنید. رمز باید حداقل
                ۸ کاراکتر و شامل حرف و عدد باشد.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert--danger login-alert">
                <strong>بازنشانی انجام نشد</strong>

                @foreach ($errors->all() as $error)
                    <span>{{ $error }}</span>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.update') }}"
            class="login-form"
            data-buildino-submit
        >
            @csrf

            <input
                type="hidden"
                name="token"
                value="{{ $token }}"
            >

            <label class="auth-field">
                <span>ایمیل حساب</span>

                <div class="auth-input">
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'user',
                            'size' => 18,
                        ]
                    )

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        placeholder="name@example.com"
                        autocomplete="email"
                        dir="ltr"
                        required
                    >
                </div>
            </label>

            <label class="auth-field">
                <span>رمز عبور جدید</span>

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
                        placeholder="حداقل ۸ کاراکتر"
                        autocomplete="new-password"
                        dir="ltr"
                        required
                        autofocus
                    >
                </div>
            </label>

            <label class="auth-field">
                <span>تکرار رمز عبور جدید</span>

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
                        name="password_confirmation"
                        placeholder="رمز جدید را دوباره وارد کنید"
                        autocomplete="new-password"
                        dir="ltr"
                        required
                    >
                </div>
            </label>

            <button
                class="login-submit"
                type="submit"
            >
                <span>ذخیره رمز عبور جدید</span>

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
                پس از تغییر رمز، توکن بازنشانی مصرف می‌شود و برای ورود باید از رمز جدید استفاده کنید.
            </span>
        </div>
    </main>
</div>
<script
    src="{{ asset('js/buildino-foundation.js') }}"
></script>
</body>
</html>
