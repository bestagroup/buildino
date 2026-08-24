<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Buildino Role Matrix
    |--------------------------------------------------------------------------
    |
    | Management roles use Global / Complex / Building / Block assignments.
    | Owner and Tenant are intentionally relation-driven personas:
    | UnitOwnership / UnitOccupancy remains their access source of truth.
    |
    */

    'roles' => [

        'superadmin' => [
            'display_name' =>
                'مدیر کل سامانه',

            'description' =>
                'دسترسی کامل و سراسری به تمام امکانات مدیریتی، مالی، عملیاتی و سیستمی Buildino.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'global',

            /*
             * `*` means every permission currently registered
             * in the permissions table.
             */
            'permissions' => ['*'],
        ],

        'complex_manager' => [
            'display_name' =>
                'مدیر مجتمع',

            'description' =>
                'مدیریت عملیاتی ساختمان‌های زیرمجموعه یک مجتمع بدون دسترسی مدیریت کاربران سراسری یا تنظیمات حساس پلتفرم.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'complex',

            'permissions' => [
                'reports.dashboard.view',
                'reports.financial.view',
                'reports.receivables.view',
                'reports.operations.view',

                'complexes.view',
                'complexes.update',

                'buildings.view',
                'buildings.create',
                'buildings.update',

                'blocks.view',
                'blocks.create',
                'blocks.update',

                'floors.view',
                'floors.create',
                'floors.update',

                'units.view',
                'units.create',
                'units.update',

                'parking-spaces.view',
                'parking-spaces.create',
                'parking-spaces.update',

                'storage-units.view',
                'storage-units.create',
                'storage-units.update',

                'unit-ownerships.view',
                'unit-ownerships.create',
                'unit-ownerships.update',

                'unit-occupancies.view',
                'unit-occupancies.create',
                'unit-occupancies.update',

                'unit-invitations.view',
                'unit-invitations.create',
                'unit-invitations.update',

                'users.view',
                'users.create',

                'guests.view',
                'guests.create',
                'guests.update',

                'guest-visits.view',
                'guest-visits.create',
                'guest-visits.update',

                'facilities.view',
                'facilities.create',
                'facilities.update',

                'facility-reservations.view',
                'facility-reservations.create',
                'facility-reservations.update',
                'facility-reservations.approve',
                'facility-reservations.cancel',

                'charge-formulas.view',
                'charge-formulas.create',
                'charge-formulas.update',

                'charge-periods.view',
                'charge-periods.create',
                'charge-periods.update',
                'charge-periods.calculate',
                'charge-periods.issue',

                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.issue',
                'invoices.adjust',

                'payments.view',

                'expenses.view',
                'expenses.create',
                'expenses.update',

                'incomes.view',
                'incomes.create',
                'incomes.update',

                'wallets.view',
                'building-wallet.view',

                'building-bank-accounts.view',
                'building-bank-accounts.create',

                'wallet-payouts.view',
                'wallet-payouts.create',

                'building-bills.view',
                'building-bills.create',
                'building-bills.complete',
                'building-bills.fail',

                'service-requests.view',
                'service-requests.create',
                'service-requests.update',

                'support-tickets.view',
                'support-tickets.create',
                'support-tickets.update',

                'support-config.view',

                'announcements.view',
                'announcements.create',
                'announcements.update',

                'documents.view',
                'documents.create',
                'documents.update',

                'files.view',
                'files.create',

                'meeting-minutes.view',
                'meeting-minutes.create',
                'meeting-minutes.update',

                'loyalty-rewards.view',
                'loyalty-rewards.create',
                'loyalty-rewards.update',
                'loyalty-rewards.delete',

                'generated-reports.view',
                'generated-reports.create',
            ],
        ],

        'building_manager' => [
            'display_name' =>
                'مدیر ساختمان',

            'description' =>
                'مدیریت روزمره یک ساختمان شامل ساختار، ساکنین، رزرو، شارژ، خدمات، پشتیبانی و گزارش‌های همان ساختمان.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'building',

            'permissions' => [
                'reports.dashboard.view',
                'reports.financial.view',
                'reports.receivables.view',
                'reports.operations.view',

                'buildings.view',
                'buildings.update',

                'blocks.view',
                'blocks.create',
                'blocks.update',

                'floors.view',
                'floors.create',
                'floors.update',

                'units.view',
                'units.create',
                'units.update',

                'parking-spaces.view',
                'parking-spaces.create',
                'parking-spaces.update',

                'storage-units.view',
                'storage-units.create',
                'storage-units.update',

                'unit-ownerships.view',
                'unit-ownerships.create',
                'unit-ownerships.update',

                'unit-occupancies.view',
                'unit-occupancies.create',
                'unit-occupancies.update',

                'unit-invitations.view',
                'unit-invitations.create',
                'unit-invitations.update',

                'users.view',
                'users.create',

                'guests.view',
                'guests.create',
                'guests.update',

                'guest-visits.view',
                'guest-visits.create',
                'guest-visits.update',

                'facilities.view',
                'facilities.create',
                'facilities.update',

                'facility-reservations.view',
                'facility-reservations.create',
                'facility-reservations.update',
                'facility-reservations.approve',
                'facility-reservations.cancel',

                'charge-formulas.view',
                'charge-formulas.create',
                'charge-formulas.update',

                'charge-periods.view',
                'charge-periods.create',
                'charge-periods.update',
                'charge-periods.calculate',
                'charge-periods.issue',

                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.issue',
                'invoices.adjust',

                'payments.view',

                'expenses.view',
                'expenses.create',
                'expenses.update',

                'incomes.view',
                'incomes.create',
                'incomes.update',

                'wallets.view',
                'building-wallet.view',

                'building-bank-accounts.view',
                'building-bank-accounts.create',

                'wallet-payouts.view',
                'wallet-payouts.create',

                'building-bills.view',
                'building-bills.create',
                'building-bills.complete',
                'building-bills.fail',

                'service-requests.view',
                'service-requests.create',
                'service-requests.update',

                'support-tickets.view',
                'support-tickets.create',
                'support-tickets.update',

                'support-config.view',

                'announcements.view',
                'announcements.create',
                'announcements.update',

                'documents.view',
                'documents.create',
                'documents.update',

                'files.view',
                'files.create',

                'meeting-minutes.view',
                'meeting-minutes.create',
                'meeting-minutes.update',

                'loyalty-rewards.view',
                'loyalty-rewards.create',
                'loyalty-rewards.update',
                'loyalty-rewards.delete',

                'generated-reports.view',
                'generated-reports.create',
            ],
        ],

        'block_manager' => [
            'display_name' =>
                'مدیر بلوک',

            'description' =>
                'تعریف و مشاهده کاربران فقط در محدوده بلوک تخصیص‌یافته.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'block',

            'permissions' => [
                'reports.dashboard.view',
                'users.view',
                'users.create',
            ],
        ],

        'finance_manager' => [
            'display_name' =>
                'مدیر مالی ساختمان',

            'description' =>
                'مدیریت شارژ، صورتحساب، پرداخت، هزینه، درآمد، کیف پول، قبوض، تسویه و گزارش مالی در Scope ساختمان.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'building',

            'permissions' => [
                'reports.dashboard.view',
                'reports.financial.view',
                'reports.receivables.view',

                'buildings.view',
                'units.view',

                'financial-categories.view',
                'financial-categories.create',
                'financial-categories.update',

                'financial-accounts.view',
                'financial-accounts.create',
                'financial-accounts.update',

                'funds.view',
                'funds.create',
                'funds.update',

                'charge-formulas.view',
                'charge-formulas.create',
                'charge-formulas.update',

                'charge-periods.view',
                'charge-periods.create',
                'charge-periods.update',
                'charge-periods.calculate',
                'charge-periods.issue',

                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.issue',
                'invoices.adjust',

                'payments.view',
                'payments.create',
                'payments.update',
                'payments.verify',
                'payments.refund',

                'expenses.view',
                'expenses.create',
                'expenses.update',

                'incomes.view',
                'incomes.create',
                'incomes.update',

                'financial-transactions.view',
                'financial-transactions.create',

                'accounting-periods.view',
                'accounting-periods.create',
                'accounting-periods.update',

                'financial-reconciliations.view',
                'financial-reconciliations.create',
                'financial-reconciliations.update',

                'wallets.view',
                'building-wallet.view',

                'building-bank-accounts.view',
                'building-bank-accounts.create',

                'wallet-payouts.view',
                'wallet-payouts.create',

                'building-bills.view',
                'building-bills.create',
                'building-bills.complete',
                'building-bills.fail',

                'wallet-accounting.view',

                'loyalty-rewards.view',
                'loyalty-rewards.create',
                'loyalty-rewards.update',
                'loyalty-rewards.delete',

                'generated-reports.view',
                'generated-reports.create',
            ],
        ],

        'operator' => [
            'display_name' =>
                'اپراتور ساختمان',

            'description' =>
                'اپراتور عملیاتی برای مهمان، تردد، رزرو، خدمات و ثبت/پیگیری تیکت‌ها در Scope ساختمان.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'building',

            'permissions' => [
                'reports.dashboard.view',
                'reports.operations.view',

                'buildings.view',
                'units.view',

                'unit-ownerships.view',
                'unit-occupancies.view',

                'guests.view',
                'guests.create',
                'guests.update',

                'guest-visits.view',
                'guest-visits.create',
                'guest-visits.update',

                'facilities.view',

                'facility-reservations.view',
                'facility-reservations.create',
                'facility-reservations.update',
                'facility-reservations.cancel',

                'service-requests.view',
                'service-requests.create',
                'service-requests.update',

                'support-tickets.view',
                'support-tickets.create',
                'support-tickets.update',

                'announcements.view',

                'documents.view',
            ],
        ],

        'support_agent' => [
            'display_name' =>
                'کارشناس پشتیبانی',

            'description' =>
                'رسیدگی به تیکت‌ها، SLA و مشاهده اطلاعات عملیاتی لازم در Scope ساختمان.',

            'is_system' =>
                true,

            'management_access' =>
                true,

            'scope' =>
                'building',

            'permissions' => [
                'reports.dashboard.view',
                'reports.operations.view',

                'buildings.view',
                'units.view',

                'unit-ownerships.view',
                'unit-occupancies.view',

                'support-tickets.view',
                'support-tickets.create',
                'support-tickets.update',

                'support-config.view',

                'service-requests.view',

                'announcements.view',
                'documents.view',
            ],
        ],

        'service_provider' => [
            'display_name' =>
                'ارائه‌دهنده خدمات',

            'description' =>
                'Persona ارائه‌دهنده خدمات؛ از Portal اختصاصی Provider استفاده می‌کند و ورود به Management Dashboard برای آن فعال نیست.',

            'is_system' =>
                true,

            'management_access' =>
                false,

            'scope' =>
                'building',

            'permissions' => [
                'service-requests.view',
                'service-requests.update',
                'service-finance.quote',
                'provider-payouts.view',
            ],
        ],

        'owner' => [
            'display_name' =>
                'مالک',

            'description' =>
                'دسترسی کاربر مالک از UnitOwnership استخراج می‌شود و Portal ساکنین فقط واحدهای مرتبط همان مالک را نمایش می‌دهد؛ این Role عمداً Permission مدیریتی ندارد.',

            'is_system' =>
                true,

            'management_access' =>
                false,

            'scope' =>
                'relationship',

            'permissions' => [],
        ],

        'tenant' => [
            'display_name' =>
                'مستأجر',

            'description' =>
                'دسترسی کاربر مستأجر از UnitOccupancy استخراج می‌شود و Portal ساکنین فقط واحدهای دارای سکونت فعال همان کاربر را نمایش می‌دهد.',

            'is_system' =>
                true,

            'management_access' =>
                false,

            'scope' =>
                'relationship',

            'permissions' => [],
        ],
    ],
];
