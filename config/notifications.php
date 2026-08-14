<?php

return [
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),

    'sms_provider' => env('SMS_PROVIDER', 'log'),
    'push_provider' => env('PUSH_PROVIDER', 'log'),

    'http_sms' => [
        'endpoint' => env('SMS_HTTP_ENDPOINT'),
        'token' => env('SMS_HTTP_TOKEN'),
        'token_header' => env('SMS_HTTP_TOKEN_HEADER', 'Authorization'),
        'token_prefix' => env('SMS_HTTP_TOKEN_PREFIX', 'Bearer '),
        'sender' => env('SMS_HTTP_SENDER'),
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 15),
    ],

    'http_push' => [
        'endpoint' => env('PUSH_HTTP_ENDPOINT'),
        'token' => env('PUSH_HTTP_TOKEN'),
        'token_header' => env('PUSH_HTTP_TOKEN_HEADER', 'Authorization'),
        'token_prefix' => env('PUSH_HTTP_TOKEN_PREFIX', 'Bearer '),
        'timeout' => (int) env('PUSH_HTTP_TIMEOUT', 15),
    ],
];
