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
                    با حساب Buildino خود وارد شود.
                </p>
            </div>

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
</body>
</html>
