@extends('management.layouts.app')

@section('title', 'داشبورد مدیریتی Buildino')

@section('page-title', $roleDashboard['profile']['short_title'] ?? 'داشبورد مدیریتی')

@section(
    'page-subtitle',
    ($roleDashboard['profile']['description'] ?? 'مدیریت و پایش سامانه Buildino')
)

@php
    $scope = $dashboard['scope'];
    $counts = $dashboard['counts'];
    $buildingDashboard = $dashboard['building_dashboard'];
    $platformSummary = $dashboard['platform_summary'];
    $currency = $scope['currency'] ?? 'IRR';
    $headerWallet =
        $managementHeader['wallet'] ?? [];
    $headerNotifications =
        $managementHeader['notifications'] ?? [];
    $currentRole =
        data_get(
            $managementUi ?? [],
            'primary_role.display_name',
            'کاربر سامانه'
        );
    $accessLabel =
        data_get(
            $managementUi ?? [],
            'access_label',
            'دسترسی فعال'
        );

    $roleProfile =
        $roleDashboard['profile'] ?? [];
    $roleSections =
        $roleDashboard['sections'] ?? [];
    $roleQuickActions =
        $roleDashboard['quick_actions'] ?? [];
    $roleModules =
        $roleDashboard['modules'] ?? [];
    $roleOperationKeys =
        $roleDashboard['operation_keys'] ?? [];
    $roleRecentKeys =
        $roleDashboard['recent_keys'] ?? [];

    $money = static fn ($value) =>
        number_format((int) ($value ?? 0));

    $personName = static function ($user): string {
        if (! $user) {
            return '—';
        }

        $name = trim(
            ($user->first_name ?? '')
            . ' '
            . ($user->last_name ?? '')
        );

        return $name !== ''
            ? $name
            : ($user->mobile ?? '—');
    };

    $statusLabels = [
        'pending' => 'در انتظار',
        'payment_pending' => 'در انتظار پرداخت',
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
        'confirmed' => 'قطعی',
        'cancelled' => 'لغوشده',
        'completed' => 'تکمیل‌شده',
        'expired' => 'منقضی',
        'open' => 'باز',
        'assigned' => 'تخصیص‌یافته',
        'in_progress' => 'در حال انجام',
        'awaiting_confirmation' => 'منتظر تأیید',
        'waiting_user' => 'منتظر کاربر',
        'resolved' => 'حل‌شده',
        'closed' => 'بسته',
        'draft' => 'پیش‌نویس',
        'issued' => 'صادرشده',
        'partial' => 'پرداخت جزئی',
        'paid' => 'پرداخت‌شده',
        'overdue' => 'معوق',
        'void' => 'باطل',
        'failed' => 'ناموفق',
        'processing' => 'در حال پردازش',
        'refunded' => 'بازپرداخت',
        'partial_refund' => 'بازپرداخت جزئی',
    ];

    $statusClass = static function ($value): string {
        return match ($value) {
            'paid', 'completed', 'confirmed', 'resolved', 'closed', 'approved' =>
                'status-badge--success',
            'pending', 'payment_pending', 'open', 'assigned', 'processing',
            'waiting_user', 'awaiting_confirmation', 'partial' =>
                'status-badge--warning',
            'failed', 'rejected', 'overdue' =>
                'status-badge--danger',
            'cancelled', 'expired', 'void' =>
                'status-badge--muted',
            default =>
                'status-badge--info',
        };
    };


    $financialKpis = $buildingDashboard['kpis'] ?? [];

    $heroKpis =
        collect(
            $roleDashboard['kpis'] ?? []
        )
            ->map(
                static function (
                    array $item
                ) use ($money): array {
                    return [
                        ...$item,
                        'value' =>
                            ($item['money'] ?? false)
                                ? $money($item['value'] ?? 0)
                                : number_format(
                                    (int) (
                                        $item['value']
                                        ?? 0
                                    )
                                ),
                        'suffix' =>
                            $item['unit']
                            ?? '',
                    ];
                }
            )
            ->all();

    $aging = $buildingDashboard['receivables_aging'] ?? [];
    $agingMax = max(
        1,
        ...array_map(
            static fn ($value) => (int) $value,
            array_values($aging ?: [0])
        )
    );

    $healthStatus = $dashboard['health']['status'] ?? 'unknown';
@endphp

@section('content')
<section class="hero-panel card mb-4 buildino-dashboard-overview" id="overview">
    <div class="hero-panel__copy">
        <span class="eyebrow">
            {{ $roleProfile['eyebrow'] ?? 'MANAGEMENT WORKSPACE' }}
        </span>

        <h2>
            {{ $roleProfile['title'] ?? 'داشبورد مدیریتی' }}

            @if ($selectedBuilding)
                <small class="hero-role-scope">
                    {{ $selectedBuilding->title }}
                </small>
            @endif
        </h2>

        <p>
            داده‌ها برای بازه
            <strong>{{ $dashboard['period']['from'] }}</strong>
            تا
            <strong>{{ $dashboard['period']['to'] }}</strong>
            نمایش داده می‌شوند.
        </p>
    </div>

    <form
        method="GET"
        action="{{ route('management.dashboard') }}"
        class="dashboard-filter"
    >
        <div class="filter-heading">
            @include('management.partials.icon', ['name' => 'filter', 'size' => 18])
            <span>فیلتر داشبورد</span>
        </div>

        <label>
            <span>محدوده</span>
            <select name="building_id">
                @if ($platformAccess)
                    <option value="">نمای کل پلتفرم</option>
                @endif

                @foreach ($buildings as $building)
                    <option
                        value="{{ $building->id }}"
                        @selected(
                            $selectedBuilding
                            && $selectedBuilding->id === $building->id
                        )
                    >
                        {{ $building->title }}
                        @if ($building->complex)
                            — {{ $building->complex->title }}
                        @endif
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            <span>از تاریخ</span>
            <input
                type="date"
                name="from"
                value="{{ $dashboard['period']['from'] }}"
            >
        </label>

        <label>
            <span>تا تاریخ</span>
            <input
                type="date"
                name="to"
                value="{{ $dashboard['period']['to'] }}"
            >
        </label>

        <button class="filter-submit" type="submit">
            اعمال فیلتر
        </button>
    </form>
</section>

<section
    class="role-workspace card mb-4 buildino-dashboard-role role-workspace--{{ $roleProfile['tone'] ?? 'blue' }}"
    aria-label="فضای کاری نقش جاری"
>
    <div class="role-workspace__identity">
        <span class="role-workspace__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => $roleProfile['icon'] ?? 'home',
                    'size' => 24,
                ]
            )
        </span>

        <div>
            <span class="eyebrow">
                فضای کاری شما
            </span>
            <h3>
                {{ $roleProfile['short_title'] ?? $currentRole }}
            </h3>
            <p>
                {{ $roleProfile['description'] ?? '' }}
            </p>
        </div>
    </div>

    @if ($roleQuickActions)
        <div class="role-workspace__actions">
            @foreach ($roleQuickActions as $action)
                <a
                    class="role-quick-action"
                    href="{{
                        route(
                            'management.operations.show',
                            $action['resource']
                        )
                        . (
                            ($action['create'] ?? false)
                                ? '?create=1'
                                : ''
                        )
                    }}"
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => $action['icon'] ?? 'grid',
                            'size' => 17,
                        ]
                    )
                    <span>
                        {{ $action['title'] }}
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</section>

<section class="dashboard-glance-grid buildino-stat-grid mb-4" aria-label="خلاصه حساب جاری">
    <article class="glance-card card h-100 glance-card--wallet">
        <span class="glance-card__icon">
            @include('management.partials.icon', ['name' => 'wallet', 'size' => 20])
        </span>
        <div>
            <span>کیف پول شخصی</span>
            <strong
                data-money-value="{{ (int) ($headerWallet['available_balance'] ?? 0) }}"
            >
                {{ number_format((int) ($headerWallet['available_balance'] ?? 0)) }}
            </strong>
            <small>{{ $headerWallet['currency'] ?? 'IRR' }} قابل استفاده</small>
        </div>
    </article>

    <article class="glance-card card h-100 glance-card--notification">
        <span class="glance-card__icon">
            @include('management.partials.icon', ['name' => 'bell', 'size' => 20])
        </span>
        <div>
            <span>اعلان‌های جدید</span>
            <strong>
                {{ number_format((int) ($headerNotifications['unread_count'] ?? 0)) }}
            </strong>
            <small>پیام خوانده‌نشده</small>
        </div>
    </article>

    <article class="glance-card card h-100 glance-card--role">
        <span class="glance-card__icon">
            @include('management.partials.icon', ['name' => 'shield', 'size' => 20])
        </span>
        <div>
            <span>نقش فعال</span>
            <strong class="glance-card__text-value">{{ $currentRole }}</strong>
            <small>{{ $accessLabel }}</small>
        </div>
    </article>

    <article class="glance-card card h-100 glance-card--date">
        <span class="glance-card__icon">
            @include('management.partials.icon', ['name' => 'calendar', 'size' => 20])
        </span>
        <div>
            <span>تاریخ امروز</span>
            <strong
                class="glance-card__text-value"
                data-jdate="{{ now()->toISOString() }}"
            >
                {{ now()->format('Y/m/d') }}
            </strong>
            <small>تقویم شمسی</small>
        </div>
    </article>
</section>

@if ($errors->any())
    <div class="alert alert--danger dashboard-alert">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if ($dashboard['building_report_error'] || $dashboard['platform_report_error'])
    <div class="alert alert--warning dashboard-alert">
        بخشی از داده‌های گزارش در این لحظه قابل دریافت نبود؛
        سایر شاخص‌های داشبورد بدون توقف نمایش داده شده‌اند.
    </div>
@endif

<section class="kpi-grid buildino-kpi-grid mb-4">
    @foreach ($heroKpis as $item)
        <article class="kpi-card card h-100 kpi-card--{{ $item['tone'] }}">
            <div class="kpi-card__icon">
                @include(
                    'management.partials.icon',
                    [
                        'name' => $item['icon'],
                        'size' => 24,
                    ]
                )
            </div>

            <div class="kpi-card__content">
                <span>{{ $item['title'] }}</span>
                <strong>{{ $item['value'] }}</strong>
                <small>{{ $item['suffix'] }}</small>
            </div>
        </article>
    @endforeach
</section>

@if ($roleSections['modules'] ?? true)
<section class="section-block card mb-4 buildino-section-card" id="modules">
    <div class="section-heading">
        <div>
            <span class="eyebrow">System Capabilities</span>
            <h3>ماژول‌های سامانه</h3>
            <p>
                نمای یکپارچه امکانات پیاده‌سازی‌شده در هسته Buildino.
            </p>
        </div>

        <div class="section-heading__meta">
            <strong>{{ count($roleModules) }}</strong>
            <span>حوزه اصلی</span>
        </div>
    </div>

    <div class="module-grid">
        @foreach ($roleModules as $module)
            <a
                class="module-card card h-100"
                href="{{
                    route(
                        'management.operations.show',
                        $module['target_resource']
                    )
                }}"
            >
                <div class="module-card__top">
                    <div class="module-icon">
                        @include(
                            'management.partials.icon',
                            [
                                'name' => $module['icon'],
                                'size' => 22,
                            ]
                        )
                    </div>

                    <span class="module-state">
                        فعال
                    </span>
                </div>

                <h4>{{ $module['title'] }}</h4>

                <p>{{ $module['description'] }}</p>

                <div class="module-card__footer">
                    <strong>
                        @if ($module['money'] ?? false)
                            {{ $money($module['count']) }}
                        @else
                            {{ number_format((int) $module['count']) }}
                        @endif
                    </strong>
                    <span>{{ $module['unit'] }}</span>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

@if (
    ($roleSections['finance'] ?? false)
    || ($roleSections['receivables'] ?? false)
)
<section class="dashboard-grid dashboard-grid--2 buildino-dashboard-grid mb-4" id="financial">
    @if ($roleSections['finance'] ?? false)
    <article class="panel card h-100">
        <div class="panel__header">
            <div>
                <span class="eyebrow">Finance</span>
                <h3>
                    {{ $selectedBuilding ? 'تصویر مالی ساختمان' : 'تصویر مالی پلتفرم' }}
                </h3>
            </div>

            <div class="panel-icon">
                @include('management.partials.icon', ['name' => 'wallet'])
            </div>
        </div>

        @if ($buildingDashboard)
            @php
                $cashIn = (int) ($financialKpis['cash_inflow'] ?? 0);
                $cashOut = (int) ($financialKpis['cash_outflow'] ?? 0);
                $cashMax = max(1, $cashIn, $cashOut);
            @endphp

            <div class="metric-list">
                <div class="metric-row">
                    <div class="metric-row__label">
                        <span>ورودی نقدی</span>
                        <strong>{{ $money($cashIn) }}</strong>
                    </div>
                    <div class="progress-track">
                        <span
                            class="progress-bar progress-bar--success"
                            data-value="{{ $cashIn }}"
                            data-max="{{ $cashMax }}"
                        ></span>
                    </div>
                </div>

                <div class="metric-row">
                    <div class="metric-row__label">
                        <span>خروجی نقدی</span>
                        <strong>{{ $money($cashOut) }}</strong>
                    </div>
                    <div class="progress-track">
                        <span
                            class="progress-bar progress-bar--warning"
                            data-value="{{ $cashOut }}"
                            data-max="{{ $cashMax }}"
                        ></span>
                    </div>
                </div>
            </div>

            <div class="mini-stat-grid">
                <div>
                    <span>خالص جریان نقد</span>
                    <strong>
                        {{ $money($financialKpis['net_cash_flow'] ?? 0) }}
                    </strong>
                </div>
                <div>
                    <span>وصول شارژ</span>
                    <strong>
                        {{ $money($financialKpis['charge_collections'] ?? 0) }}
                    </strong>
                </div>
                <div>
                    <span>درآمد Facility</span>
                    <strong>
                        {{ $money($financialKpis['facility_paid_amount'] ?? 0) }}
                    </strong>
                </div>
                <div>
                    <span>GMV خدمات</span>
                    <strong>
                        {{ $money($financialKpis['service_gmv'] ?? 0) }}
                    </strong>
                </div>
            </div>
        @elseif ($platformSummary)
            <div class="mini-stat-grid mini-stat-grid--platform">
                <div>
                    <span>GMV بازار خدمات</span>
                    <strong>
                        {{ $money(
                            data_get(
                                $platformSummary,
                                'service_marketplace.gmv',
                                0
                            )
                        ) }}
                    </strong>
                </div>
                <div>
                    <span>سهم ارائه‌دهندگان</span>
                    <strong>
                        {{ $money(
                            data_get(
                                $platformSummary,
                                'service_marketplace.provider_amount',
                                0
                            )
                        ) }}
                    </strong>
                </div>
                <div>
                    <span>تسویه Provider</span>
                    <strong>
                        {{ $money(
                            data_get(
                                $platformSummary,
                                'provider_payouts.net_amount',
                                0
                            )
                        ) }}
                    </strong>
                </div>
                <div>
                    <span>مغایرت Reconciliation</span>
                    <strong>
                        {{ number_format(
                            data_get(
                                $platformSummary,
                                'platform_wallets.reconciliation_mismatch_count',
                                0
                            )
                        ) }}
                    </strong>
                </div>
            </div>
        @else
            <div class="empty-state">
                داده مالی برای این محدوده موجود نیست.
            </div>
        @endif
    </article>
    @endif

    @if ($roleSections['receivables'] ?? false)
    <article class="panel card h-100">
        <div class="panel__header">
            <div>
                <span class="eyebrow">Receivables</span>
                <h3>سن مطالبات</h3>
            </div>

            <div class="panel-icon">
                @include('management.partials.icon', ['name' => 'invoice'])
            </div>
        </div>

        @if ($buildingDashboard && $aging)
            @php
                $agingLabels = [
                    'not_due' => 'سررسید نشده',
                    'days_1_30' => '۱ تا ۳۰ روز',
                    'days_31_60' => '۳۱ تا ۶۰ روز',
                    'days_61_90' => '۶۱ تا ۹۰ روز',
                    'days_90_plus' => 'بیش از ۹۰ روز',
                ];
            @endphp

            <div class="aging-chart">
                @foreach ($agingLabels as $key => $label)
                    @php
                        $value = (int) ($aging[$key] ?? 0);
                    @endphp

                    <div class="aging-row">
                        <span class="aging-row__label">{{ $label }}</span>
                        <div class="aging-row__bar">
                            <span
                                class="progress-bar"
                                data-value="{{ $value }}"
                                data-max="{{ $agingMax }}"
                            ></span>
                        </div>
                        <strong>{{ $money($value) }}</strong>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                Aging مطالبات پس از انتخاب یک ساختمان نمایش داده می‌شود.
            </div>
        @endif
    </article>
    @endif
</section>
@endif

@if ($roleSections['operations'] ?? false)
<section class="section-block card mb-4 buildino-section-card" id="operations">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Operations</span>
            <h3>وضعیت عملیات جاری</h3>
            <p>
                توزیع وضعیت رزروها، خدمات، پشتیبانی و صورتحساب‌ها.
            </p>
        </div>
    </div>

    @php
        $operationDefinitions = collect([
            'reservations' => [
                'title' => 'رزرو امکانات',
                'icon' => 'calendar',
                'data' => $dashboard['operations']['reservations'],
            ],
            'services' => [
                'title' => 'خدمات ساختمانی',
                'icon' => 'tools',
                'data' => $dashboard['operations']['services'],
            ],
            'support' => [
                'title' => 'تیکت پشتیبانی',
                'icon' => 'support',
                'data' => $dashboard['operations']['support'],
            ],
            'invoices' => [
                'title' => 'صورتحساب‌ها',
                'icon' => 'invoice',
                'data' => $dashboard['operations']['invoices'],
            ],
        ])->only($roleOperationKeys);
    @endphp

    <div class="operations-grid">
        @foreach ($operationDefinitions as $operation)
            <article class="operation-card card h-100">
                <div class="operation-card__header">
                    <div class="operation-icon">
                        @include(
                            'management.partials.icon',
                            [
                                'name' => $operation['icon'],
                                'size' => 20,
                            ]
                        )
                    </div>
                    <h4>{{ $operation['title'] }}</h4>
                </div>

                <div class="status-list">
                    @forelse ($operation['data'] as $status => $count)
                        @if ((int) $count > 0)
                            <div class="status-list__item">
                                <span
                                    class="status-dot {{ $statusClass($status) }}"
                                ></span>
                                <span>
                                    {{ $statusLabels[$status] ?? $status }}
                                </span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @endif
                    @empty
                        <div class="empty-inline">داده‌ای ثبت نشده است.</div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

@if ($roleSections['recent'] ?? false)
@php
    $managementDtQuery =
        array_filter(
            [
                'building_id' =>
                    $selectedBuilding
                        ?->getKey(),

                'from' =>
                    data_get(
                        $dashboard,
                        'period.from'
                    ),

                'to' =>
                    data_get(
                        $dashboard,
                        'period.to'
                    ),
            ],
            static fn ($value): bool =>
                $value !== null
                && $value !== ''
        );

    $managementDtUrl =
        static fn (
            string $table
        ): string =>
            route(
                'management.datatables',
                [
                    'table' =>
                        $table,
                ]
            )
            . (
                $managementDtQuery
                    ? '?'
                        . http_build_query(
                            $managementDtQuery
                        )
                    : ''
            );

    $managementRecentTables = [
        'payments' => [
            'title' =>
                'پرداخت‌های اخیر',
            'count_id' =>
                'management-payments-count',
            'columns' => [
                [
                    'data' =>
                        'payment_number',
                    'title' =>
                        'شماره',
                ],
                [
                    'data' =>
                        'payer_name',
                    'title' =>
                        'پرداخت‌کننده',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'amount_formatted',
                    'title' =>
                        'مبلغ',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'status_label',
                    'title' =>
                        'وضعیت',
                    'orderable' =>
                        false,
                    'status' =>
                        true,
                ],
                [
                    'data' =>
                        'created_at_jalali',
                    'title' =>
                        'زمان',
                    'orderable' =>
                        false,
                ],
            ],
        ],

        'reservations' => [
            'title' =>
                'رزروهای اخیر',
            'count_id' =>
                'management-reservations-count',
            'columns' => [
                [
                    'data' =>
                        'facility_title',
                    'title' =>
                        'امکان',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'user_name',
                    'title' =>
                        'کاربر',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'reservation_date_jalali',
                    'title' =>
                        'تاریخ',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'status_label',
                    'title' =>
                        'وضعیت',
                    'orderable' =>
                        false,
                    'status' =>
                        true,
                ],
            ],
        ],

        'services' => [
            'title' =>
                'درخواست‌های خدمت',
            'count_id' =>
                'management-services-count',
            'columns' => [
                [
                    'data' =>
                        'request_number',
                    'title' =>
                        'درخواست',
                ],
                [
                    'data' =>
                        'title',
                    'title' =>
                        'عنوان',
                ],
                [
                    'data' =>
                        'assigned_name',
                    'title' =>
                        'مسئول',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'status_label',
                    'title' =>
                        'وضعیت',
                    'orderable' =>
                        false,
                    'status' =>
                        true,
                ],
                [
                    'data' =>
                        'created_at_jalali',
                    'title' =>
                        'زمان',
                    'orderable' =>
                        false,
                ],
            ],
        ],

        'support' => [
            'title' =>
                'تیکت‌های اخیر',
            'count_id' =>
                'management-support-count',
            'columns' => [
                [
                    'data' =>
                        'ticket_number',
                    'title' =>
                        'تیکت',
                ],
                [
                    'data' =>
                        'subject',
                    'title' =>
                        'موضوع',
                ],
                [
                    'data' =>
                        'user_name',
                    'title' =>
                        'کاربر',
                    'orderable' =>
                        false,
                ],
                [
                    'data' =>
                        'status_label',
                    'title' =>
                        'وضعیت',
                    'orderable' =>
                        false,
                    'status' =>
                        true,
                ],
                [
                    'data' =>
                        'created_at_jalali',
                    'title' =>
                        'زمان',
                    'orderable' =>
                        false,
                ],
            ],
        ],
    ];
@endphp

<section class="section-block" id="recent">
    <div class="section-heading">
        <div>
            <span class="eyebrow">
                Server-side Activity
            </span>
            <h3>
                آخرین فعالیت‌ها
            </h3>
            <p>
                جستجو، مرتب‌سازی و صفحه‌بندی این جداول مستقیماً
                روی Query پایگاه داده انجام می‌شود.
            </p>
        </div>
    </div>

    <div class="dashboard-grid dashboard-grid--2">
        @foreach (
            $roleRecentKeys
            as $recentKey
        )
            @continue(
                ! isset(
                    $managementRecentTables[
                        $recentKey
                    ]
                )
            )

            @php
                $table =
                    $managementRecentTables[
                        $recentKey
                    ];
            @endphp

            <article class="panel table-panel">
                <div class="panel__header panel__header--compact">
                    <h3>
                        {{ $table['title'] }}
                    </h3>

                    <span
                        class="record-count"
                        id="{{ $table['count_id'] }}"
                    >
                        —
                    </span>
                </div>

                @include(
                    'shared.server-datatable',
                    [
                        'id' =>
                            "management-{$recentKey}-table",

                        'url' =>
                            $managementDtUrl(
                                $recentKey
                            ),

                        'columns' =>
                            $table['columns'],

                        'pageLength' =>
                            10,

                        'countTarget' =>
                            '#'
                            . $table[
                                'count_id'
                            ],
                    ]
                )
            </article>
        @endforeach
    </div>
</section>
@endif

@if (
    ($roleSections['system'] ?? false)
    || ($roleSections['api'] ?? false)
)
<section class="dashboard-grid dashboard-grid--2" id="system">
    @if ($roleSections['system'] ?? false)
    <article class="panel card h-100">
        <div class="panel__header">
            <div>
                <span class="eyebrow">System Health</span>
                <h3>سلامت سامانه</h3>
            </div>

            <span class="health-pill health-pill--{{ $healthStatus }}">
                <span></span>
                {{ match($healthStatus) {
                    'ok' => 'سالم',
                    'degraded' => 'کاهش کیفیت',
                    'not_ready' => 'عدم آمادگی',
                    default => 'نامشخص',
                } }}
            </span>
        </div>

        <div class="health-summary">
            <div>
                <span>Readiness</span>
                <strong>
                    {{ ($dashboard['health']['ready'] ?? false) ? 'Ready' : 'Not Ready' }}
                </strong>
            </div>

            @if (isset($dashboard['health']['checks']))
                @foreach (
                    [
                        'database' => 'Database',
                        'cache' => 'Cache',
                        'storage' => 'Storage',
                        'scheduler' => 'Scheduler',
                        'queues' => 'Queue',
                    ] as $key => $label
                )
                    @php
                        $check = $dashboard['health']['checks'][$key] ?? null;
                    @endphp

                    @if ($check)
                        <div>
                            <span>{{ $label }}</span>
                            <strong class="health-text health-text--{{ $check['status'] ?? 'unknown' }}">
                                {{ strtoupper($check['status'] ?? 'unknown') }}
                            </strong>
                        </div>
                    @endif
                @endforeach
            @else
                <div>
                    <span>سطح نمایش</span>
                    <strong>Readiness عمومی</strong>
                </div>
            @endif
        </div>
    </article>
    @endif

    @if ($roleSections['api'] ?? false)
    <article class="panel api-card">
        <div class="panel__header">
            <div>
                <span class="eyebrow">API Contract</span>
                <h3>وضعیت رابط برنامه‌نویسی</h3>
            </div>

            <div class="panel-icon">
                @include('management.partials.icon', ['name' => 'api'])
            </div>
        </div>

        <div class="api-stat-grid">
            <div>
                <span>نسخه</span>
                <strong>v{{ $dashboard['api']['version'] }}</strong>
            </div>
            <div>
                <span>Path</span>
                <strong>{{ number_format($dashboard['api']['paths']) }}</strong>
            </div>
            <div>
                <span>Contract</span>
                <strong>{{ number_format($dashboard['api']['contracts']) }}</strong>
            </div>
            <div>
                <span>Protected</span>
                <strong>{{ number_format($dashboard['api']['protected_paths']) }}</strong>
            </div>
        </div>

        <p class="api-note">
            این داشبورد روی همان Domain Model، Service و Permissionهای Backend
            نهایی اجرا می‌شود؛ بنابراین داده نمایشی جداگانه یا Mock ندارد.
        </p>
    </article>
    @endif
</section>
@endif

<footer class="dashboard-footer">
    <div>
        Buildino Management Dashboard
        <span>•</span>
        Backend/API v{{ $dashboard['api']['version'] }}
    </div>

    <div>
        آخرین تولید داده:
        {{ \Carbon\Carbon::parse($dashboard['generated_at'])->format('Y-m-d H:i') }}
    </div>
</footer>
@endsection
