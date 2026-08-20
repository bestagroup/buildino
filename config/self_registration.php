<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public self-registration personas
    |--------------------------------------------------------------------------
    |
    | Superadmin is deliberately absent. Management personas receive a new,
    | isolated workspace; resident personas must present a valid unit invite.
    |
    */

    'personas' => [
        'building_manager' => [
            'label' => 'مدیر ساختمان',
            'description' => 'ایجاد مجتمع و ساختمان اولیه و مدیریت بلوک‌ها، طبقات، واحدها و ساکنین.',
            'kind' => 'management',
            'scope' => 'building',
        ],

        'complex_manager' => [
            'label' => 'مدیر مجتمع',
            'description' => 'مدیریت یک مجتمع اختصاصی و افزودن چند ساختمان و همه عملیات آن‌ها.',
            'kind' => 'management',
            'scope' => 'complex',
        ],

        'finance_manager' => [
            'label' => 'مدیر مالی',
            'description' => 'دسترسی مالی محدود به ساختمان اختصاصی برای شارژ، صورتحساب، پرداخت و گزارش‌ها.',
            'kind' => 'management',
            'scope' => 'building',
        ],

        'operator' => [
            'label' => 'اپراتور ساختمان',
            'description' => 'دسترسی عملیاتی محدود به مهمان، رزرو، خدمات و پشتیبانی ساختمان اختصاصی.',
            'kind' => 'management',
            'scope' => 'building',
        ],

        'support_agent' => [
            'label' => 'کارشناس پشتیبانی',
            'description' => 'رسیدگی به تیکت‌ها و درخواست‌های خدمت در محدوده ساختمان اختصاصی.',
            'kind' => 'management',
            'scope' => 'building',
        ],

        'service_provider' => [
            'label' => 'ارائه‌دهنده خدمات',
            'description' => 'ورود به پرتال ارائه‌دهندگان برای مشاهده سفارش‌ها، پیشنهاد قیمت و تسویه.',
            'kind' => 'provider',
            'scope' => null,
        ],

        'owner' => [
            'label' => 'مالک واحد',
            'description' => 'ورود به پرتال شخصی با دعوت معتبر مدیر ساختمان برای مشاهده واحد و امور مالی.',
            'kind' => 'resident',
            'scope' => 'unit',
            'invitation_types' => ['owner'],
        ],

        'tenant' => [
            'label' => 'مستأجر یا ساکن',
            'description' => 'ورود به پرتال شخصی با دعوت معتبر واحد برای شارژ، کیف پول، رزرو و خدمات.',
            'kind' => 'resident',
            'scope' => 'unit',
            'invitation_types' => [
                'tenant',
                'resident',
                'family_member',
            ],
        ],
    ],

    'pending_session_key' => 'buildino.pending_registration',
    'otp_purpose' => 'registration',
];
