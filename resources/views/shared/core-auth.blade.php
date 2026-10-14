<link rel="stylesheet" href="{{ asset('css/buildino-core-flows.css') }}">
<script>
    window.BuildinoAuthUi = {{ \Illuminate\Support\Js::from([
        'errors' => $errors->messages(),
        'otpDigits' => config('auth_otp.digits', 6),
        'resendAfter' => config('auth_otp.resend_after', 60),
        'otpSent' => (bool) session('otp_status') || (request()->routeIs('register.verify') && (bool) session('status')),
        'portalLoginUrl' => route('portal.login'),
        'managementLoginUrl' => route('login'),
    ]) }};
</script>
<script src="{{ asset('js/buildino-core-auth.js') }}" defer></script>
