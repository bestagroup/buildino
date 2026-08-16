<?php

return [

    'resident' => [

        'invoices' => [
            'title' => 'صورتحساب‌های من',
            'description' => 'تمام صورتحساب‌های واحدهای مرتبط، مانده بدهی و وضعیت پرداخت.',
            'icon' => 'invoice',
            'columns' => [
                ['data' => 'invoice_number', 'title' => 'شماره'],
                ['data' => 'unit_title', 'title' => 'واحد', 'orderable' => false],
                ['data' => 'total_amount_formatted', 'title' => 'مبلغ کل', 'orderable' => false],
                ['data' => 'outstanding_amount_formatted', 'title' => 'مانده', 'orderable' => false],
                ['data' => 'due_date_jalali', 'title' => 'سررسید', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'reservations' => [
            'title' => 'رزروهای من',
            'description' => 'تاریخچه رزرو امکانات، وضعیت پرداخت و فرآیند لغو.',
            'icon' => 'calendar',
            'columns' => [
                ['data' => 'facility_title', 'title' => 'امکان', 'orderable' => false],
                ['data' => 'unit_title', 'title' => 'واحد', 'orderable' => false],
                ['data' => 'reservation_date_jalali', 'title' => 'تاریخ', 'orderable' => false],
                ['data' => 'time_range', 'title' => 'ساعت', 'orderable' => false, 'searchable' => false],
                ['data' => 'final_amount_formatted', 'title' => 'مبلغ', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'guests' => [
            'title' => 'مهمان‌های من',
            'description' => 'دعوت‌ها، ورود و خروج مهمان‌های واحدهای مرتبط.',
            'icon' => 'user-plus',
            'columns' => [
                ['data' => 'guest_name', 'title' => 'مهمان', 'orderable' => false],
                ['data' => 'mobile', 'title' => 'موبایل', 'orderable' => false],
                ['data' => 'unit_title', 'title' => 'واحد', 'orderable' => false],
                ['data' => 'expected_entry_jalali', 'title' => 'ورود مورد انتظار', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'services' => [
            'title' => 'خدمات من',
            'description' => 'درخواست‌ها، پیشنهاد قیمت، وضعیت اجرا و تسویه خدمات.',
            'icon' => 'tools',
            'columns' => [
                ['data' => 'request_number', 'title' => 'درخواست'],
                ['data' => 'title', 'title' => 'عنوان'],
                ['data' => 'provider_name', 'title' => 'ارائه‌دهنده', 'orderable' => false],
                ['data' => 'priority_label', 'title' => 'اولویت', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'created_at_jalali', 'title' => 'ثبت', 'orderable' => false],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'support' => [
            'title' => 'تیکت‌های من',
            'description' => 'پیگیری گفتگو، SLA، وضعیت رسیدگی و بازگشایی تیکت.',
            'icon' => 'support',
            'columns' => [
                ['data' => 'ticket_number', 'title' => 'تیکت'],
                ['data' => 'subject', 'title' => 'موضوع'],
                ['data' => 'category_title', 'title' => 'دسته‌بندی', 'orderable' => false],
                ['data' => 'assigned_name', 'title' => 'کارشناس', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'created_at_jalali', 'title' => 'ثبت', 'orderable' => false],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'wallet' => [
            'title' => 'گردش کیف پول',
            'description' => 'تمام تراکنش‌های کیف پول شخصی و کیف پول واحدهای مرتبط.',
            'icon' => 'wallet',
            'columns' => [
                ['data' => 'wallet_label', 'title' => 'کیف پول', 'orderable' => false],
                ['data' => 'entry_type_label', 'title' => 'نوع', 'orderable' => false],
                ['data' => 'amount_formatted', 'title' => 'مبلغ', 'orderable' => false],
                ['data' => 'balance_after_formatted', 'title' => 'مانده بعد', 'orderable' => false],
                ['data' => 'description', 'title' => 'شرح', 'orderable' => false],
                ['data' => 'created_at_jalali', 'title' => 'زمان', 'orderable' => false],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['from', 'to'],
        ],
    ],

    'provider' => [

        'services' => [
            'title' => 'کارهای تخصیص‌یافته',
            'description' => 'تمام درخواست‌های خدمت تخصیص‌یافته، Quote و وضعیت مالی هر کار.',
            'icon' => 'tools',
            'columns' => [
                ['data' => 'request_number', 'title' => 'درخواست'],
                ['data' => 'title', 'title' => 'عنوان'],
                ['data' => 'building_title', 'title' => 'ساختمان', 'orderable' => false],
                ['data' => 'unit_title', 'title' => 'واحد', 'orderable' => false],
                ['data' => 'payment_status_label', 'title' => 'وضعیت وجه', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'payouts' => [
            'title' => 'درخواست‌های تسویه',
            'description' => 'تاریخچه درخواست تسویه، حساب مقصد، کارمزد و وضعیت پرداخت.',
            'icon' => 'money',
            'columns' => [
                ['data' => 'amount_formatted', 'title' => 'مبلغ', 'orderable' => false],
                ['data' => 'fee_amount_formatted', 'title' => 'کارمزد', 'orderable' => false],
                ['data' => 'net_amount_formatted', 'title' => 'خالص', 'orderable' => false],
                ['data' => 'bank_label', 'title' => 'حساب مقصد', 'orderable' => false],
                ['data' => 'status_label', 'title' => 'وضعیت', 'orderable' => false, 'status' => true],
                ['data' => 'created_at_jalali', 'title' => 'درخواست', 'orderable' => false],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['status', 'from', 'to'],
        ],

        'wallet' => [
            'title' => 'گردش کیف پول Provider',
            'description' => 'اعتبارها، برداشت‌ها، تسویه و مانده کیف پول ارائه‌دهنده خدمات.',
            'icon' => 'wallet',
            'columns' => [
                ['data' => 'entry_type_label', 'title' => 'نوع', 'orderable' => false],
                ['data' => 'amount_formatted', 'title' => 'مبلغ', 'orderable' => false],
                ['data' => 'balance_after_formatted', 'title' => 'مانده بعد', 'orderable' => false],
                ['data' => 'description', 'title' => 'شرح', 'orderable' => false],
                ['data' => 'created_at_jalali', 'title' => 'زمان', 'orderable' => false],
                ['data' => 'action_url', 'title' => 'جزئیات', 'orderable' => false, 'searchable' => false, 'action' => true],
            ],
            'filters' => ['from', 'to'],
        ],
    ],
];
