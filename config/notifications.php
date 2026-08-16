<?php

return [
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),

    'sms_provider' => env('SMS_PROVIDER', 'log'),
    'push_provider' => env('PUSH_PROVIDER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging HTTP v1
    |--------------------------------------------------------------------------
    |
    | Credentials can be supplied either by absolute file path or as
    | base64-encoded service-account JSON. Never commit the credentials file.
    |
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH', env('GOOGLE_APPLICATION_CREDENTIALS')),
        'credentials_json_base64' => env('FCM_CREDENTIALS_JSON_BASE64'),
        'timeout' => (int) env('FCM_TIMEOUT', 15),
        'android_priority' => env('FCM_ANDROID_PRIORITY', 'high'),
        'apns_priority' => env('FCM_APNS_PRIORITY', '10'),
    ],

    'http_sms' => [
        'endpoint' => env('SMS_HTTP_ENDPOINT'),
        'token' => env('SMS_HTTP_TOKEN'),
        'token_header' => env('SMS_HTTP_TOKEN_HEADER', 'Authorization'),
        'token_prefix' => env('SMS_HTTP_TOKEN_PREFIX', 'Bearer '),
        'sender' => env('SMS_HTTP_SENDER'),
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 15),
        'message_id_path' => env('SMS_HTTP_MESSAGE_ID_PATH', 'id'),
    ],

    'http_push' => [
        'endpoint' => env('PUSH_HTTP_ENDPOINT'),
        'token' => env('PUSH_HTTP_TOKEN'),
        'token_header' => env('PUSH_HTTP_TOKEN_HEADER', 'Authorization'),
        'token_prefix' => env('PUSH_HTTP_TOKEN_PREFIX', 'Bearer '),
        'timeout' => (int) env('PUSH_HTTP_TIMEOUT', 15),
        'message_id_path' => env('PUSH_HTTP_MESSAGE_ID_PATH', 'id'),
    ],
];
