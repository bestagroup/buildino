<?php

return [
    'types' => [
        'concierge' => ['title' => 'لابی من', 'services' => ['reception' => 'پذیرش و راهنمایی', 'visits' => 'کنترل تردد', 'reservations' => 'هماهنگی مشاعات']],
        'resident_service' => ['title' => 'خدمات دهنده مستقر', 'services' => ['cleaning' => 'نظافت', 'maintenance' => 'تاسیسات و تعمیرات', 'gardening' => 'فضای سبز']],
        'support' => ['title' => 'پشتیبانی ساختمان', 'services' => ['support' => 'پاسخگویی و پیگیری درخواست‌ها', 'reservations' => 'هماهنگی مشاعات']],
        'guard' => ['title' => 'نگهبان ساختمان', 'services' => ['security' => 'نگهبانی', 'visits' => 'کنترل تردد']],
        'other' => ['title' => 'سایر', 'services' => ['other' => 'سایر خدمات ساختمان']],
    ],
    'permissions' => [
        'guests.view' => 'مشاهده مهمان‌ها', 'guests.create' => 'ثبت مهمان', 'guests.update' => 'ویرایش مهمان',
        'guest-visits.view' => 'مشاهده ترددها', 'guest-visits.create' => 'ثبت تردد', 'guest-visits.update' => 'ویرایش تردد',
        'facilities.view' => 'مشاهده مشاعات', 'facility-reservations.view' => 'مشاهده رزروها',
        'facility-reservations.approve' => 'تأیید رزرو مشاعات', 'facility-reservations.cancel' => 'لغو رزرو مشاعات',
        'service-requests.view' => 'مشاهده درخواست خدمات', 'service-requests.create' => 'ثبت درخواست خدمات', 'service-requests.update' => 'پیگیری درخواست خدمات',
        'support-tickets.view' => 'مشاهده پشتیبانی', 'support-tickets.create' => 'ثبت درخواست پشتیبانی', 'support-tickets.update' => 'پیگیری پشتیبانی',
    ],
];
