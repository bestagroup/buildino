<?php
return [
    'digits' => (int) env('OTP_DIGITS', 6),
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 2),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_after' => (int) env('OTP_RESEND_AFTER_SECONDS', 60),
    'request_lock_seconds' => (int) env('OTP_REQUEST_LOCK_SECONDS', 10),
    'log_channel' => env('OTP_LOG_CHANNEL'),
];
