<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription enforcement
    |--------------------------------------------------------------------------
    |
    | Local/test environments may keep this disabled to avoid coupling
    | developer fixtures to commercial plans. Production must enable it.
    */
    'enforce' => (bool) env('SUBSCRIPTION_ENFORCEMENT', false),

    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

    'default_trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Feature catalog
    |--------------------------------------------------------------------------
    |
    | Codes are intentionally stable because application code may use them as
    | entitlement keys. Display labels may change without breaking contracts.
    */
    'features' => [
        'units' => [
            'title' => 'تعداد واحد',
            'value_type' => 'integer',
        ],
        'reservations' => [
            'title' => 'رزرو امکانات',
            'value_type' => 'boolean',
        ],
        'payments' => [
            'title' => 'پرداخت آنلاین',
            'value_type' => 'boolean',
        ],
        'reports' => [
            'title' => 'گزارش‌های پیشرفته',
            'value_type' => 'boolean',
        ],
        'marketplace' => [
            'title' => 'بازار خدمات',
            'value_type' => 'boolean',
        ],
        'loyalty' => [
            'title' => 'باشگاه وفاداری',
            'value_type' => 'boolean',
        ],
        'mobile' => [
            'title' => 'اپلیکیشن موبایل',
            'value_type' => 'boolean',
        ],
    ],
];
