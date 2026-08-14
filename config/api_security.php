<?php

return [
    'default_rate_limit' => (int) env('API_RATE_LIMIT', 120),
    'auth_rate_limit' => (int) env('AUTH_RATE_LIMIT', 10),
    'otp_request_rate_limit' => (int) env('OTP_REQUEST_RATE_LIMIT', 5),
    'payment_rate_limit' => (int) env('PAYMENT_RATE_LIMIT', 30),

    'require_verified_identity' => env('API_REQUIRE_VERIFIED_IDENTITY', true),

];
