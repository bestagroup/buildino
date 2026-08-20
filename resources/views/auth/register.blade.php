<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">

    <title>ساخت حساب Buildino</title>

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

    @vite('resources/js/app.js')
</head>

@php
    $activePersona = old(
        'persona',
        $selectedPersona
    );
    $activeKind = $personas[
        $activePersona
    ]['kind'] ?? 'management';
@endphp

<body class="login-page registration-page">
<div class="registration-shell">
    <section class="registration-showcase">
        <div class="login-visual__mesh"></div>

        <div class="registration-showcase__content">
            <div class="showcase-brand">
                <div class="brand-mark brand-mark--large">
                    <span>B</span>
                </div>

                <div>
                    <strong>Buildino</strong>
                    <span>Start your smart workspace</span>
                </div>
            </div>

            <div class="registration-showcase__copy">
                <span class="eyebrow eyebrow--light">
                    شروع متناسب با نقش شما
                </span>

                <h1>
                    حساب خودتان را بسازید؛
                    <em>سناریوی خودتان را ببینید.</em>
                </h1>

                <p>
                    مدیران یک فضای کاری مستقل دریافت می‌کنند، ارائه‌دهندگان
                    وارد پرتال خدمات می‌شوند و مالک یا ساکن با دعوت امن واحد
                    به اطلاعات مرتبط با خودش دسترسی خواهد داشت.
                </p>
            </div>

            <div class="registration-safety-list">
                <div>
                    @include(
                        'management.partials.icon',
                        ['name' => 'shield', 'size' => 18]
                    )
                    <span>دسترسی کاملاً محدود به Scope حساب</span>
                </div>

                <div>
                    @include(
                        'management.partials.icon',
                        ['name' => 'building', 'size' => 18]
                    )
                    <span>ساخت خودکار مجتمع و ساختمان اولیه مدیران</span>
                </div>

                <div>
                    @include(
                        'management.partials.icon',
                        ['name' => 'user', 'size' => 18]
                    )
                    <span>اتصال مالک و ساکن فقط با دعوت معتبر واحد</span>
                </div>
            </div>
        </div>

        <div class="registration-showcase__footer">
            <span>ثبت‌نام امن با تأیید موبایل</span>
            <span>Role • Permission • Scope</span>
        </div>
    </section>

    <main class="registration-form-area">
        <div class="registration-card">
            <div class="registration-card__header">
                <div>
                    <span class="eyebrow">ایجاد حساب جدید</span>
                    <h2>به Buildino بپیوندید</h2>
                    <p>
                        نوع دسترسی را انتخاب و اطلاعات حساب را کامل کنید.
                    </p>
                </div>

                <div class="registration-step">
                    <strong>۱</strong>
                    <span>از ۲</span>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert--danger login-alert registration-alert">
                    <strong>ثبت‌نام تکمیل نشد</strong>

                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('register.store') }}"
                class="registration-form"
                data-registration-form
                data-buildino-submit
            >
                @csrf

                <label class="registration-field registration-field--wide">
                    <span>می‌خواهید با چه نقشی وارد شوید؟</span>

                    <select
                        name="persona"
                        required
                        data-registration-persona
                        data-placeholder="نوع حساب را انتخاب کنید"
                    >
                        @foreach ($personas as $name => $persona)
                            <option
                                value="{{ $name }}"
                                data-kind="{{ $persona['kind'] }}"
                                data-description="{{ $persona['description'] }}"
                                @selected($activePersona === $name)
                            >
                                {{ $persona['label'] }}
                            </option>
                        @endforeach
                    </select>

                    <small data-persona-description>
                        {{ $personas[$activePersona]['description'] ?? '' }}
                    </small>
                </label>

                <label class="registration-field">
                    <span>نام</span>
                    <input
                        type="text"
                        name="first_name"
                        value="{{ old('first_name') }}"
                        autocomplete="given-name"
                        placeholder="نام"
                        maxlength="100"
                        required
                    >
                </label>

                <label class="registration-field">
                    <span>نام خانوادگی</span>
                    <input
                        type="text"
                        name="last_name"
                        value="{{ old('last_name') }}"
                        autocomplete="family-name"
                        placeholder="نام خانوادگی"
                        maxlength="100"
                        required
                    >
                </label>

                <label class="registration-field">
                    <span>شماره موبایل</span>
                    <input
                        type="tel"
                        name="mobile"
                        value="{{ old('mobile') }}"
                        autocomplete="tel"
                        inputmode="numeric"
                        placeholder="09123456789"
                        dir="ltr"
                        maxlength="16"
                        required
                    >
                </label>

                <label class="registration-field">
                    <span>ایمیل <small>(اختیاری)</small></span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        placeholder="name@example.com"
                        dir="ltr"
                        maxlength="190"
                    >
                </label>

                <label class="registration-field">
                    <span>رمز عبور</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="حداقل ۸ کاراکتر، شامل حرف و عدد"
                        dir="ltr"
                        maxlength="255"
                        required
                    >
                </label>

                <label class="registration-field">
                    <span>تکرار رمز عبور</span>
                    <input
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="تکرار رمز عبور"
                        dir="ltr"
                        maxlength="255"
                        required
                    >
                </label>

                <section
                    class="registration-context registration-field--wide"
                    data-registration-section="management"
                    @hidden($activeKind !== 'management')
                >
                    <div class="registration-context__heading">
                        <div>
                            @include(
                                'management.partials.icon',
                                ['name' => 'building', 'size' => 18]
                            )
                        </div>

                        <span>
                            <strong>فضای کاری اولیه</strong>
                            <small>
                                این مجتمع فقط برای حساب شما ساخته می‌شود.
                            </small>
                        </span>
                    </div>

                    <div class="registration-context__grid">
                        <label class="registration-field">
                            <span>نام مجتمع</span>
                            <input
                                type="text"
                                name="complex_title"
                                value="{{ old('complex_title') }}"
                                placeholder="مثلاً مجتمع سرو"
                                maxlength="255"
                            >
                        </label>

                        <label class="registration-field">
                            <span>نام ساختمان اولیه</span>
                            <input
                                type="text"
                                name="building_title"
                                value="{{ old('building_title') }}"
                                placeholder="مثلاً برج A"
                                maxlength="255"
                            >
                        </label>

                        <label class="registration-field">
                            <span>استان</span>
                            <input
                                type="text"
                                name="province"
                                value="{{ old('province') }}"
                                placeholder="استان"
                                maxlength="100"
                            >
                        </label>

                        <label class="registration-field">
                            <span>شهر</span>
                            <input
                                type="text"
                                name="city"
                                value="{{ old('city') }}"
                                placeholder="شهر"
                                maxlength="100"
                            >
                        </label>

                        <label class="registration-field registration-field--wide">
                            <span>نشانی <small>(اختیاری)</small></span>
                            <textarea
                                name="address"
                                rows="2"
                                placeholder="نشانی مجتمع یا ساختمان"
                                maxlength="2000"
                            >{{ old('address') }}</textarea>
                        </label>

                        <label class="registration-field">
                            <span>کد پستی <small>(اختیاری)</small></span>
                            <input
                                type="text"
                                name="postal_code"
                                value="{{ old('postal_code') }}"
                                placeholder="کد پستی"
                                inputmode="numeric"
                                maxlength="20"
                            >
                        </label>
                    </div>
                </section>

                <section
                    class="registration-context registration-context--invite registration-field--wide"
                    data-registration-section="resident"
                    @hidden($activeKind !== 'resident')
                >
                    <div class="registration-context__heading">
                        <div>
                            @include(
                                'management.partials.icon',
                                ['name' => 'key', 'size' => 18]
                            )
                        </div>

                        <span>
                            <strong>دعوت امن واحد</strong>
                            <small>
                                کد باید با شماره موبایل یا ایمیل شما مطابقت داشته باشد.
                            </small>
                        </span>
                    </div>

                    <label class="registration-field">
                        <span>کد دعوت واحد</span>
                        <input
                            type="text"
                            name="invitation_token"
                            value="{{ old('invitation_token', $invitationToken) }}"
                            placeholder="کد موجود در لینک دعوت مدیر ساختمان"
                            dir="ltr"
                            maxlength="255"
                        >
                    </label>
                </section>

                <section
                    class="registration-context registration-context--provider registration-field--wide"
                    data-registration-section="provider"
                    @hidden($activeKind !== 'provider')
                >
                    <div class="registration-context__heading">
                        <div>
                            @include(
                                'management.partials.icon',
                                ['name' => 'tools', 'size' => 18]
                            )
                        </div>

                        <span>
                            <strong>پرتال ارائه‌دهندگان خدمات</strong>
                            <small>
                                پس از تأیید موبایل مستقیماً وارد محیط خدمات می‌شوید.
                            </small>
                        </span>
                    </div>
                </section>

                <label class="registration-terms registration-field--wide">
                    <input
                        type="checkbox"
                        name="terms"
                        value="1"
                        @checked(old('terms'))
                        required
                    >

                    <span>
                        قوانین استفاده و سیاست حریم خصوصی Buildino را می‌پذیرم.
                    </span>
                </label>

                <button
                    class="login-submit registration-submit registration-field--wide"
                    type="submit"
                >
                    <span>ارسال کد تأیید و ادامه</span>

                    @include(
                        'management.partials.icon',
                        ['name' => 'arrow-left', 'size' => 18]
                    )
                </button>
            </form>

            <div class="registration-login-links">
                <span>قبلاً حساب ساخته‌اید؟</span>
                <a href="{{ route('login') }}">ورود مدیران</a>
                <i></i>
                <a href="{{ route('portal.login') }}">ورود پرتال کاربران</a>
            </div>
        </div>
    </main>
</div>

<script
    src="{{ asset('js/buildino-foundation.js') }}"
></script>
<script
    src="{{ asset('js/buildino-registration.js') }}"
    defer
></script>
</body>
</html>
