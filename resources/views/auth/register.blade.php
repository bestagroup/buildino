<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">

    <title>ساخت حساب Buildino</title>

    <link rel="stylesheet" href="{{ asset('css/buildino-fonts.css') }}">
    <link rel="stylesheet" href="{{ config('management_ui.libraries.bootstrap.css') }}" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/buildino-foundation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-management.css') }}">

    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('css/buildino-design-system.css') }}">
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
    <main class="registration-form-area">
        <div class="registration-card">
            <div class="registration-card__header">
                <div>
                    <span class="eyebrow">ایجاد حساب جدید</span>
                    <h2>به Buildino بپیوندید</h2>
                    <p>
                        در چهار مرحله اطلاعات حساب را تکمیل کنید.
                    </p>
                </div>

                <div class="registration-step" aria-live="polite" aria-atomic="true">
                    <strong data-registration-current-step>۱</strong>
                    <span>از ۴</span>
                </div>
            </div>

            <nav class="registration-progress" aria-label="مراحل ثبت ‌نام" data-registration-progress>
                <ol>
                    @foreach (['نوع حساب', 'مشخصات فردی', 'رمز عبور', 'فضای کار و نشانی'] as $stepTitle)
                        <li @if ($loop->first) aria-current="step" @endif>
                            <button type="button" data-registration-go="{{ $loop->index }}" disabled>
                                <span class="registration-progress__number">{{ ['۱', '۲', '۳', '۴'][$loop->index] }}</span>
                                <span @if ($loop->last) data-registration-context-label @endif>{{ $stepTitle }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
                <div class="registration-progress__track" role="progressbar" aria-label="پیشرفت مراحل فرم ثبت‌نام" aria-valuemin="1" aria-valuemax="4" aria-valuenow="1" data-registration-progress-bar>
                    <span></span>
                </div>
            </nav>

            @if ($errors->any())
                <div class="alert alert--danger login-alert registration-alert">
                    <strong>ثبت‌ نام تکمیل نشد</strong>

                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="registration-form" data-registration-form data-buildino-submit>
                @csrf

                <section class="registration-wizard-panel" data-registration-step="0" aria-labelledby="registration-account-heading">
                    <div class="registration-wizard-heading">
                        <h3 id="registration-account-heading" tabindex="-1">نوع حساب شما چیست؟</h3>
                        <p>حسابی را انتخاب کنید که با فعالیت شما در بیلدینو مطابقت دارد.</p>
                    </div>
                    <label class="registration-field registration-field--wide">
                        <select name="persona" required data-registration-persona data-placeholder="نوع حساب را انتخاب کنید">
                            @foreach ($personas as $name => $persona)
                                <option value="{{ $name }}" data-kind="{{ $persona['kind'] }}" data-description="{{ $persona['description'] }}">
                                    {{ $persona['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <small data-persona-description>
                            {{ $personas[$activePersona]['description'] ?? '' }}
                        </small>
                    </label>
                </section>

                <section class="registration-wizard-panel" data-registration-step="1" aria-labelledby="registration-personal-heading">
                    <div class="registration-wizard-heading">
                        <h3 id="registration-personal-heading" tabindex="-1">مشخصات فردی شما</h3>
                        <p>کد تأیید به شماره موبایلی که اینجا وارد می‌کنید ارسال می‌شود.</p>
                    </div>
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
                </section>

                <section class="registration-wizard-panel" data-registration-step="2" aria-labelledby="registration-password-heading">
                    <div class="registration-wizard-heading">
                        <h3 id="registration-password-heading" tabindex="-1">رمز عبور حساب</h3>
                        <p>رمزی با حداقل ۸ کاراکتر و شامل حرف و عدد انتخاب کنید.</p>
                    </div>
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
                </section>

                <section class="registration-wizard-panel" data-registration-step="3" aria-labelledby="registration-context-heading">
                    <div class="registration-wizard-heading">
                        <h3 id="registration-context-heading" tabindex="-1" data-registration-context-heading>فضای کار اولیه و نشانی</h3>
                        <p data-registration-context-description>اطلاعات اولیه مجتمع و ساختمان را وارد کنید؛ نشانی و کد پستی اختیاری‌اند.</p>
                    </div>
                    <section class="registration-context registration-field--wide" data-registration-section="management" >
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
                                <select
                                    name="province_id"
                                    data-registration-province
                                    data-required="1"
                                    data-registration-error-name="province"
                                    data-placeholder="استان را انتخاب کنید"
                                >
                                    <option value="">استان را انتخاب کنید</option>
                                    @foreach ($locationProvinces as $province)
                                        <option value="{{ $province['id'] }}" data-name="{{ $province['name'] }}" @selected($selectedProvinceId === $province['id'])>{{ $province['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="registration-field">
                                <span>شهر</span>
                                <select
                                    name="city_id"
                                    data-registration-city
                                    data-required="1"
                                    data-registration-error-name="city"
                                    data-placeholder="شهر را انتخاب کنید"
                                    aria-describedby="registration-city-hint"
                                >
                                    <option value="">ابتدا استان را انتخاب کنید</option>
                                    @foreach ($locationProvinces as $province)
                                        <optgroup label="{{ $province['name'] }}" data-province-id="{{ $province['id'] }}">
                                            @foreach ($province['cities'] as $city)
                                                <option value="{{ $city['id'] }}" @selected($selectedProvinceId === $province['id'] && $selectedCityId === $city['id'])>{{ $city['label'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <small id="registration-city-hint" data-registration-city-hint aria-live="polite">ابتدا استان را انتخاب کنید؛ سپس شهر را جست‌وجو کنید.</small>
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
                </section>

                <div class="registration-wizard-actions registration-field--wide">
                    <button class="registration-wizard-back" type="button" data-registration-previous hidden>مرحله قبل</button>
                    <button class="login-submit registration-submit" type="button" data-registration-next hidden>
                        <span>ادامه</span>
                        @include('management.partials.icon', ['name' => 'arrow-left', 'size' => 18])
                    </button>
                    <button class="login-submit registration-submit" type="submit" data-registration-submit>
                        <span>ارسال کد تأیید و ادامه</span>

                        @include(
                            'management.partials.icon',
                            ['name' => 'arrow-left', 'size' => 18]
                        )
                    </button>
                </div>
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
<script src="{{ asset('js/buildino-foundation.js') }}"></script>
<script src="{{ asset('js/buildino-registration.js') }}" defer></script>
@include('shared.core-auth')
</body>
</html>
