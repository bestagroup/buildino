@php
    $registrationSuccess = auth()->check() && session()->pull('buildino.registration_success', false);
    $loginSuccess = auth()->check() && session()->pull('buildino.login_success', false);
@endphp
@if ($registrationSuccess || $loginSuccess)
    <link rel="stylesheet" href="{{ asset('css/buildino-login-success.css') }}">
    <div class="login-success" role="status" aria-live="polite" aria-atomic="true" data-login-success>
        <div class="login-success__card">
            <div class="login-success__symbol">
                <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
                    <circle class="login-success__ring" cx="32" cy="32" r="26" pathLength="100"/>
                    <path class="login-success__check" d="m20 32 8 8 16-17" pathLength="100"/>
                </svg>
            </div>
            <strong>{{ $registrationSuccess ? 'حساب شما با موفقیت ساخته شد' : 'با موفقیت وارد شدید' }}</strong>
            <span>به بیلدینو خوش آمدید</span>
        </div>
    </div>
    <script src="{{ asset('js/buildino-login-success.js') }}" defer></script>
@endif
