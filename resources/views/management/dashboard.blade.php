@extends('management.layouts.app')

@section('title', 'داشبورد مدیریتی Buildino')

@section('page-title', 'داشبورد مدیریتی')

@section(
    'page-subtitle',
    $selectedBuilding
        ? 'نمای عملیاتی و مالی ' . $selectedBuilding->title
        : 'نمای کلان پلتفرم و ساختمان‌های تحت مدیریت'
)

@php
    $scope = $dashboard['scope'];
    $counts = $dashboard['counts'];
    $buildingDashboard = $dashboard['building_dashboard'];
    $platformSummary = $dashboard['platform_summary'];
    $currency = $scope['currency'] ?? 'IRR';

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

    $heroKpis = $buildingDashboard
        ? [
            [
                'title' => 'موجودی کیف پول ساختمان',
                'value' => $money($financialKpis['wallet_balance'] ?? 0),
                'suffix' => $currency,
                'icon' => 'wallet',
                'tone' => 'primary',
            ],
            [
                'title' => 'مطالبات باز',
                'value' => $money($financialKpis['receivables_outstanding'] ?? 0),
                'suffix' => $currency,
                'icon' => 'invoice',
                'tone' => 'danger',
            ],
            [
                'title' => 'ورودی نقدی دوره',
                'value' => $money($financialKpis['cash_inflow'] ?? 0),
                'suffix' => $currency,
                'icon' => 'money',
                'tone' => 'success',
            ],
            [
                'title' => 'خروجی نقدی دوره',
                'value' => $money($financialKpis['cash_outflow'] ?? 0),
                'suffix' => $currency,
                'icon' => 'chart',
                'tone' => 'warning',
            ],
        ]
        : [
            [
                'title' => 'ساختمان‌های تحت مدیریت',
                'value' => number_format($counts['buildings']),
                'suffix' => 'ساختمان',
                'icon' => 'building',
                'tone' => 'primary',
            ],
            [
                'title' => 'کاربران فعال',
                'value' => number_format($counts['users_total']),
                'suffix' => 'کاربر',
                'icon' => 'users',
                'tone' => 'success',
            ],
            [
                'title' => 'کمیسیون پلتفرم',
                'value' => $money(
                    data_get(
                        $platformSummary,
                        'service_marketplace.platform_commission',
                        0
                    )
                ),
                'suffix' => $currency,
                'icon' => 'money',
                'tone' => 'warning',
            ],
            [
                'title' => 'مانده کیف پول پلتفرم',
                'value' => $money(
                    data_get(
                        $platformSummary,
                        'platform_wallets.available_balance',
                        0
                    )
                ),
                'suffix' => $currency,
                'icon' => 'wallet',
                'tone' => 'primary',
            ],
        ];

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
<section class="hero-panel" id="overview">
    <div class="hero-panel__copy">
        <span class="eyebrow">
            {{ $platformAccess && ! $selectedBuilding ? 'Platform Overview' : 'Building Overview' }}
        </span>

        <h2>
            @if ($selectedBuilding)
                {{ $selectedBuilding->title }}
            @else
                مرکز مدیریت Buildino
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

<section class="kpi-grid">
    @foreach ($heroKpis as $item)
        <article class="kpi-card kpi-card--{{ $item['tone'] }}">
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

<section class="section-block" id="modules">
    <div class="section-heading">
        <div>
            <span class="eyebrow">System Capabilities</span>
            <h3>ماژول‌های سامانه</h3>
            <p>
                نمای یکپارچه امکانات پیاده‌سازی‌شده در هسته Buildino.
            </p>
        </div>

        <div class="section-heading__meta">
            <strong>{{ count($dashboard['modules']) }}</strong>
            <span>حوزه اصلی</span>
        </div>
    </div>

    <div class="module-grid">
        @foreach ($dashboard['modules'] as $module)
            <article class="module-card">
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
            </article>
        @endforeach
    </div>
</section>

<section class="dashboard-grid dashboard-grid--2" id="financial">
    <article class="panel">
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

    <article class="panel">
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
</section>

<section class="section-block" id="operations">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Operations</span>
            <h3>وضعیت عملیات جاری</h3>
            <p>
                توزیع وضعیت رزروها، خدمات، پشتیبانی و صورتحساب‌ها.
            </p>
        </div>
    </div>

    <div class="operations-grid">
        @foreach ([
            [
                'title' => 'رزرو امکانات',
                'icon' => 'calendar',
                'data' => $dashboard['operations']['reservations'],
            ],
            [
                'title' => 'خدمات ساختمانی',
                'icon' => 'tools',
                'data' => $dashboard['operations']['services'],
            ],
            [
                'title' => 'تیکت پشتیبانی',
                'icon' => 'support',
                'data' => $dashboard['operations']['support'],
            ],
            [
                'title' => 'صورتحساب‌ها',
                'icon' => 'invoice',
                'data' => $dashboard['operations']['invoices'],
            ],
        ] as $operation)
            <article class="operation-card">
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

<section class="section-block" id="recent">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Recent Activity</span>
            <h3>آخرین فعالیت‌ها</h3>
            <p>
                آخرین عملیات مالی و اجرایی در محدوده انتخاب‌شده.
            </p>
        </div>
    </div>

    <div class="dashboard-grid dashboard-grid--2">
        <article class="panel table-panel">
            <div class="panel__header panel__header--compact">
                <h3>پرداخت‌های اخیر</h3>
                <span class="record-count">
                    {{ $dashboard['recent']['payments']->count() }} رکورد
                </span>
            </div>

            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>پرداخت‌کننده</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dashboard['recent']['payments'] as $payment)
                            <tr>
                                <td>{{ $payment->payment_number }}</td>
                                <td>{{ $personName($payment->payerUser) }}</td>
                                <td>
                                    {{ $money($payment->amount) }}
                                    <small>{{ $payment->currency }}</small>
                                </td>
                                <td>
                                    <span class="status-badge {{ $statusClass($payment->status->value) }}">
                                        {{ $statusLabels[$payment->status->value] ?? $payment->status->value }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-empty">
                                    پرداختی ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="panel table-panel">
            <div class="panel__header panel__header--compact">
                <h3>رزروهای اخیر</h3>
                <span class="record-count">
                    {{ $dashboard['recent']['reservations']->count() }} رکورد
                </span>
            </div>

            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>امکان</th>
                            <th>کاربر</th>
                            <th>تاریخ</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dashboard['recent']['reservations'] as $reservation)
                            <tr>
                                <td>
                                    {{ $reservation->buildingFacility?->title ?? '—' }}
                                </td>
                                <td>{{ $personName($reservation->user) }}</td>
                                <td>
                                    {{ optional($reservation->reservation_date)->format('Y-m-d') }}
                                </td>
                                <td>
                                    <span class="status-badge {{ $statusClass($reservation->status->value) }}">
                                        {{ $statusLabels[$reservation->status->value] ?? $reservation->status->value }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-empty">
                                    رزروی ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="panel table-panel">
            <div class="panel__header panel__header--compact">
                <h3>درخواست‌های خدمت</h3>
                <span class="record-count">
                    {{ $dashboard['recent']['services']->count() }} رکورد
                </span>
            </div>

            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>درخواست</th>
                            <th>عنوان</th>
                            <th>مسئول</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dashboard['recent']['services'] as $service)
                            <tr>
                                <td>{{ $service->request_number }}</td>
                                <td>{{ $service->title }}</td>
                                <td>{{ $personName($service->assignedTo) }}</td>
                                <td>
                                    <span class="status-badge {{ $statusClass($service->status->value) }}">
                                        {{ $statusLabels[$service->status->value] ?? $service->status->value }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-empty">
                                    درخواست خدمتی ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="panel table-panel">
            <div class="panel__header panel__header--compact">
                <h3>تیکت‌های اخیر</h3>
                <span class="record-count">
                    {{ $dashboard['recent']['support']->count() }} رکورد
                </span>
            </div>

            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>تیکت</th>
                            <th>موضوع</th>
                            <th>کاربر</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dashboard['recent']['support'] as $ticket)
                            <tr>
                                <td>{{ $ticket->ticket_number }}</td>
                                <td>{{ $ticket->subject }}</td>
                                <td>{{ $personName($ticket->user) }}</td>
                                <td>
                                    <span class="status-badge {{ $statusClass($ticket->status->value) }}">
                                        {{ $statusLabels[$ticket->status->value] ?? $ticket->status->value }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-empty">
                                    تیکتی ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>

<section class="dashboard-grid dashboard-grid--2" id="system">
    <article class="panel">
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
</section>

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
