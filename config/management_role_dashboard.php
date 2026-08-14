<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role-aware Management Dashboard
    |--------------------------------------------------------------------------
    |
    | This file controls presentation only. Authorization remains owned by
    | PermissionChecker / policies / scoped API queries.
    |
    */

    'priority' => [
        'superadmin',
        'complex_manager',
        'building_manager',
        'finance_manager',
        'operator',
        'support_agent',
    ],

    'profiles' => [

        'superadmin' => [
            'title' =>
                'کنترل کل پلتفرم',

            'short_title' =>
                'داشبورد SuperAdmin',

            'description' =>
                'نمای کلان ساختمان‌ها، کاربران، مالی پلتفرم، زیرساخت و کنترل‌های سیستمی.',

            'eyebrow' =>
                'PLATFORM COMMAND CENTER',

            'icon' =>
                'shield',

            'tone' =>
                'navy',

            'kpis' => [
                [
                    'key' => 'buildings',
                    'title' => 'ساختمان‌های فعال',
                    'source' => 'counts.buildings',
                    'unit' => 'ساختمان',
                    'icon' => 'building',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'users',
                    'title' => 'کاربران فعال',
                    'source' => 'counts.users_total',
                    'unit' => 'کاربر',
                    'icon' => 'users',
                    'tone' => 'success',
                ],
                [
                    'key' => 'commission',
                    'title' => 'کمیسیون پلتفرم',
                    'source' => 'platform_summary.service_marketplace.platform_commission',
                    'unit' => 'currency',
                    'icon' => 'money',
                    'tone' => 'warning',
                    'money' => true,
                ],
                [
                    'key' => 'notifications',
                    'title' => 'اعلان‌های جدید',
                    'source' => 'counts.notifications_unread',
                    'unit' => 'اعلان',
                    'icon' => 'bell',
                    'tone' => 'danger',
                ],
            ],

            'quick_actions' => [
                [
                    'resource' => 'complexes',
                    'title' => 'ثبت مجتمع',
                    'icon' => 'building',
                    'create' => true,
                ],
                [
                    'resource' => 'buildings',
                    'title' => 'ثبت ساختمان',
                    'icon' => 'building',
                    'create' => true,
                ],
                [
                    'resource' => 'users',
                    'title' => 'ثبت کاربر',
                    'icon' => 'users',
                    'create' => true,
                ],
                [
                    'resource' => 'roles',
                    'title' => 'نقش و دسترسی',
                    'icon' => 'key',
                ],
                [
                    'resource' => 'report-exports',
                    'title' => 'گزارش‌ها',
                    'icon' => 'chart',
                ],
            ],

            'operations' => [
                'reservations',
                'services',
                'support',
                'invoices',
            ],

            'recent' => [
                'payments',
                'reservations',
                'services',
                'support',
            ],

            'sections' => [
                'modules' => true,
                'finance' => true,
                'receivables' => true,
                'operations' => true,
                'recent' => true,
                'system' => true,
                'api' => true,
            ],
        ],

        'complex_manager' => [
            'title' =>
                'داشبورد مدیر مجتمع',

            'short_title' =>
                'مدیریت مجتمع',

            'description' =>
                'کنترل ساختمان‌های زیرمجموعه، ساکنین، مطالبات، رزرو و عملیات جاری مجتمع.',

            'eyebrow' =>
                'COMPLEX MANAGEMENT',

            'icon' =>
                'building',

            'tone' =>
                'blue',

            'kpis' => [
                [
                    'key' => 'buildings',
                    'title' => 'ساختمان‌های تحت مدیریت',
                    'source' => 'counts.buildings',
                    'unit' => 'ساختمان',
                    'icon' => 'building',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'units',
                    'title' => 'واحدها',
                    'source' => 'counts.units',
                    'unit' => 'واحد',
                    'icon' => 'grid',
                    'tone' => 'success',
                ],
                [
                    'key' => 'residents',
                    'title' => 'مالک و ساکن',
                    'source' => 'counts.residents',
                    'unit' => 'کاربر مرتبط',
                    'icon' => 'users',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'receivables',
                    'title' => 'مطالبات باز',
                    'source' => 'counts.invoices_outstanding',
                    'unit' => 'currency',
                    'icon' => 'invoice',
                    'tone' => 'danger',
                    'money' => true,
                ],
            ],

            'quick_actions' => [
                [
                    'resource' => 'buildings',
                    'title' => 'ساختمان‌ها',
                    'icon' => 'building',
                ],
                [
                    'resource' => 'units',
                    'title' => 'واحدها',
                    'icon' => 'grid',
                ],
                [
                    'resource' => 'occupancies',
                    'title' => 'ساکنین',
                    'icon' => 'users',
                ],
                [
                    'resource' => 'reservations',
                    'title' => 'رزروها',
                    'icon' => 'calendar',
                ],
                [
                    'resource' => 'invoices',
                    'title' => 'صورتحساب‌ها',
                    'icon' => 'invoice',
                ],
            ],

            'operations' => [
                'reservations',
                'services',
                'support',
                'invoices',
            ],

            'recent' => [
                'payments',
                'reservations',
                'services',
                'support',
            ],

            'sections' => [
                'modules' => true,
                'finance' => true,
                'receivables' => true,
                'operations' => true,
                'recent' => true,
                'system' => false,
                'api' => false,
            ],
        ],

        'building_manager' => [
            'title' =>
                'داشبورد مدیر ساختمان',

            'short_title' =>
                'فرماندهی ساختمان',

            'description' =>
                'نمای لحظه‌ای مالی و عملیاتی ساختمان، ساکنین، رزرو، خدمات و پشتیبانی.',

            'eyebrow' =>
                'BUILDING COMMAND CENTER',

            'icon' =>
                'building',

            'tone' =>
                'teal',

            'kpis' => [
                [
                    'key' => 'wallet',
                    'title' => 'کیف پول ساختمان',
                    'source' => 'building_dashboard.kpis.wallet_balance',
                    'unit' => 'currency',
                    'icon' => 'wallet',
                    'tone' => 'primary',
                    'money' => true,
                ],
                [
                    'key' => 'receivables',
                    'title' => 'مطالبات باز',
                    'source' => 'counts.invoices_outstanding',
                    'unit' => 'currency',
                    'icon' => 'invoice',
                    'tone' => 'danger',
                    'money' => true,
                ],
                [
                    'key' => 'reservations',
                    'title' => 'رزرو فعال',
                    'source' => 'counts.reservations_active',
                    'unit' => 'رزرو',
                    'icon' => 'calendar',
                    'tone' => 'success',
                ],
                [
                    'key' => 'support',
                    'title' => 'تیکت فعال',
                    'source' => 'counts.support_active',
                    'unit' => 'تیکت',
                    'icon' => 'support',
                    'tone' => 'warning',
                ],
            ],

            'quick_actions' => [
                [
                    'resource' => 'units',
                    'title' => 'مدیریت واحدها',
                    'icon' => 'grid',
                ],
                [
                    'resource' => 'occupancies',
                    'title' => 'مالک و ساکن',
                    'icon' => 'users',
                ],
                [
                    'resource' => 'guest-visits',
                    'title' => 'مهمان و تردد',
                    'icon' => 'user-plus',
                ],
                [
                    'resource' => 'reservations',
                    'title' => 'رزرو امکانات',
                    'icon' => 'calendar',
                ],
                [
                    'resource' => 'support-tickets',
                    'title' => 'تیکت جدید',
                    'icon' => 'support',
                    'create' => true,
                ],
            ],

            'operations' => [
                'reservations',
                'services',
                'support',
                'invoices',
            ],

            'recent' => [
                'payments',
                'reservations',
                'services',
                'support',
            ],

            'sections' => [
                'modules' => true,
                'finance' => true,
                'receivables' => true,
                'operations' => true,
                'recent' => true,
                'system' => false,
                'api' => false,
            ],
        ],

        'finance_manager' => [
            'title' =>
                'مرکز مالی ساختمان',

            'short_title' =>
                'داشبورد مالی',

            'description' =>
                'تمرکز بر شارژ، مطالبات، وصول، جریان وجه، هزینه‌ها، قبوض و تسویه‌های ساختمان.',

            'eyebrow' =>
                'FINANCE CONTROL CENTER',

            'icon' =>
                'wallet',

            'tone' =>
                'emerald',

            'kpis' => [
                [
                    'key' => 'wallet',
                    'title' => 'کیف پول ساختمان',
                    'source' => 'building_dashboard.kpis.wallet_balance',
                    'unit' => 'currency',
                    'icon' => 'wallet',
                    'tone' => 'primary',
                    'money' => true,
                ],
                [
                    'key' => 'receivables',
                    'title' => 'مطالبات باز',
                    'source' => 'counts.invoices_outstanding',
                    'unit' => 'currency',
                    'icon' => 'invoice',
                    'tone' => 'danger',
                    'money' => true,
                ],
                [
                    'key' => 'collections',
                    'title' => 'پرداخت موفق',
                    'source' => 'counts.payments_paid',
                    'unit' => 'currency',
                    'icon' => 'money',
                    'tone' => 'success',
                    'money' => true,
                ],
                [
                    'key' => 'net_cash_flow',
                    'title' => 'خالص جریان نقد',
                    'source' => 'building_dashboard.kpis.net_cash_flow',
                    'unit' => 'currency',
                    'icon' => 'chart',
                    'tone' => 'warning',
                    'money' => true,
                ],
            ],

            'quick_actions' => [
                [
                    'resource' => 'charge-periods',
                    'title' => 'دوره شارژ',
                    'icon' => 'calendar',
                ],
                [
                    'resource' => 'invoices',
                    'title' => 'صورتحساب‌ها',
                    'icon' => 'invoice',
                ],
                [
                    'resource' => 'expenses',
                    'title' => 'ثبت هزینه',
                    'icon' => 'money',
                    'create' => true,
                ],
                [
                    'resource' => 'payments',
                    'title' => 'پرداخت‌ها',
                    'icon' => 'wallet',
                ],
                [
                    'resource' => 'bill-payments',
                    'title' => 'پرداخت قبوض',
                    'icon' => 'invoice',
                ],
            ],

            'operations' => [
                'invoices',
            ],

            'recent' => [
                'payments',
            ],

            'sections' => [
                'modules' => true,
                'finance' => true,
                'receivables' => true,
                'operations' => true,
                'recent' => true,
                'system' => false,
                'api' => false,
            ],
        ],

        'operator' => [
            'title' =>
                'مرکز عملیات ساختمان',

            'short_title' =>
                'داشبورد اپراتور',

            'description' =>
                'تمرکز بر مهمان و تردد، رزرو امکانات، درخواست خدمات و پیگیری عملیات روزانه.',

            'eyebrow' =>
                'OPERATIONS DESK',

            'icon' =>
                'grid',

            'tone' =>
                'amber',

            'kpis' => [
                [
                    'key' => 'guests',
                    'title' => 'مهمان‌های فعال',
                    'source' => 'counts.guest_visits_active',
                    'unit' => 'بازدید',
                    'icon' => 'user-plus',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'reservations',
                    'title' => 'رزرو فعال',
                    'source' => 'counts.reservations_active',
                    'unit' => 'رزرو',
                    'icon' => 'calendar',
                    'tone' => 'success',
                ],
                [
                    'key' => 'services',
                    'title' => 'خدمت فعال',
                    'source' => 'counts.services_active',
                    'unit' => 'درخواست',
                    'icon' => 'tools',
                    'tone' => 'warning',
                ],
                [
                    'key' => 'support',
                    'title' => 'تیکت فعال',
                    'source' => 'counts.support_active',
                    'unit' => 'تیکت',
                    'icon' => 'support',
                    'tone' => 'danger',
                ],
            ],

            'quick_actions' => [
                [
                    'resource' => 'guest-visits',
                    'title' => 'ثبت مهمان',
                    'icon' => 'user-plus',
                ],
                [
                    'resource' => 'reservations',
                    'title' => 'رزرو امکانات',
                    'icon' => 'calendar',
                ],
                [
                    'resource' => 'service-requests',
                    'title' => 'درخواست خدمت',
                    'icon' => 'tools',
                    'create' => true,
                ],
                [
                    'resource' => 'support-tickets',
                    'title' => 'ثبت تیکت',
                    'icon' => 'support',
                    'create' => true,
                ],
            ],

            'operations' => [
                'reservations',
                'services',
                'support',
            ],

            'recent' => [
                'reservations',
                'services',
                'support',
            ],

            'sections' => [
                'modules' => true,
                'finance' => false,
                'receivables' => false,
                'operations' => true,
                'recent' => true,
                'system' => false,
                'api' => false,
            ],
        ],

        'support_agent' => [
            'title' =>
                'مرکز پشتیبانی و SLA',

            'short_title' =>
                'داشبورد پشتیبانی',

            'description' =>
                'پیگیری تیکت‌های باز، درخواست‌های خدمت، کاربران مرتبط و صف رسیدگی پشتیبانی.',

            'eyebrow' =>
                'SUPPORT & SLA DESK',

            'icon' =>
                'support',

            'tone' =>
                'violet',

            'kpis' => [
                [
                    'key' => 'support',
                    'title' => 'تیکت فعال',
                    'source' => 'counts.support_active',
                    'unit' => 'تیکت',
                    'icon' => 'support',
                    'tone' => 'danger',
                ],
                [
                    'key' => 'services',
                    'title' => 'خدمت در جریان',
                    'source' => 'counts.services_active',
                    'unit' => 'درخواست',
                    'icon' => 'tools',
                    'tone' => 'warning',
                ],
                [
                    'key' => 'residents',
                    'title' => 'کاربران مرتبط',
                    'source' => 'counts.residents',
                    'unit' => 'کاربر',
                    'icon' => 'users',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'notifications',
                    'title' => 'اعلان جدید',
                    'source' => 'counts.notifications_unread',
                    'unit' => 'اعلان',
                    'icon' => 'bell',
                    'tone' => 'success',
                ],
            ],

            'quick_actions' => [
                [
                    'resource' => 'support-tickets',
                    'title' => 'تیکت جدید',
                    'icon' => 'support',
                    'create' => true,
                ],
                [
                    'resource' => 'service-requests',
                    'title' => 'درخواست‌های خدمت',
                    'icon' => 'tools',
                ],
                [
                    'resource' => 'support-categories',
                    'title' => 'دسته‌بندی',
                    'icon' => 'grid',
                ],
                [
                    'resource' => 'support-sla',
                    'title' => 'SLA',
                    'icon' => 'health',
                ],
            ],

            'operations' => [
                'support',
                'services',
            ],

            'recent' => [
                'support',
                'services',
            ],

            'sections' => [
                'modules' => true,
                'finance' => false,
                'receivables' => false,
                'operations' => true,
                'recent' => true,
                'system' => false,
                'api' => false,
            ],
        ],

        'default' => [
            'title' =>
                'مرکز مدیریت Buildino',

            'short_title' =>
                'داشبورد',

            'description' =>
                'نمای متناسب با دسترسی‌های فعال حساب جاری.',

            'eyebrow' =>
                'MANAGEMENT WORKSPACE',

            'icon' =>
                'home',

            'tone' =>
                'blue',

            'kpis' => [
                [
                    'key' => 'buildings',
                    'title' => 'ساختمان‌ها',
                    'source' => 'counts.buildings',
                    'unit' => 'ساختمان',
                    'icon' => 'building',
                    'tone' => 'primary',
                ],
                [
                    'key' => 'units',
                    'title' => 'واحدها',
                    'source' => 'counts.units',
                    'unit' => 'واحد',
                    'icon' => 'grid',
                    'tone' => 'success',
                ],
                [
                    'key' => 'notifications',
                    'title' => 'اعلان جدید',
                    'source' => 'counts.notifications_unread',
                    'unit' => 'اعلان',
                    'icon' => 'bell',
                    'tone' => 'warning',
                ],
                [
                    'key' => 'support',
                    'title' => 'تیکت فعال',
                    'source' => 'counts.support_active',
                    'unit' => 'تیکت',
                    'icon' => 'support',
                    'tone' => 'danger',
                ],
            ],

            'quick_actions' => [],

            'operations' => [
                'reservations',
                'services',
                'support',
                'invoices',
            ],

            'recent' => [
                'payments',
                'reservations',
                'services',
                'support',
            ],

            'sections' => [
                'modules' => true,
                'finance' => false,
                'receivables' => false,
                'operations' => true,
                'recent' => true,
                'system' => true,
                'api' => true,
            ],
        ],
    ],
];
