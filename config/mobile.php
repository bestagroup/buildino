<?php

return [
    'api_version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Mobile release compatibility
    |--------------------------------------------------------------------------
    |
    | Flutter should send X-App-Version and X-Device-Id on bootstrap.
    |
    */
    'minimum_supported_version' => env(
        'MOBILE_MIN_SUPPORTED_VERSION',
        '1.0.0'
    ),

    'latest_version' => env(
        'MOBILE_LATEST_VERSION',
        '1.0.0'
    ),

    'maintenance_mode' => (bool) env(
        'MOBILE_MAINTENANCE_MODE',
        false
    ),

    'maintenance_message' => env(
        'MOBILE_MAINTENANCE_MESSAGE',
        'سامانه موقتاً در حال بروزرسانی است.'
    ),

    'features' => [
        'resident' => true,
        'provider' => true,
        'wallet' => true,
        'wallet_topup' => true,
        'invoices' => true,
        'guest_visits' => true,
        'facilities' => true,
        'reservations' => true,
        'service_marketplace' => true,
        'support' => true,
        'notifications' => true,
        'push_notifications' => true,
    ],
];
