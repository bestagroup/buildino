@extends('portal.layouts.app')

@section('title', 'خانه من | Buildino')
@section('page-title', 'خانه من')

@section('sidebar-links')
    <a href="#units">
        @include(
            'management.partials.icon',
            [
                'name' => 'building',
                'size' => 18,
            ]
        )
        <span>واحدهای من</span>
    </a>

    <a href="#finance">
        @include(
            'management.partials.icon',
            [
                'name' => 'invoice',
                'size' => 18,
            ]
        )
        <span>صورتحساب و کیف پول</span>
    </a>

    <a href="#wallet-history">
        @include(
            'management.partials.icon',
            [
                'name' => 'wallet',
                'size' => 18,
            ]
        )
        <span>گردش کیف پول</span>
    </a>

    <a href="#loyalty">
        @include(
            'management.partials.icon',
            [
                'name' => 'star',
                'size' => 18,
            ]
        )
        <span>باشگاه وفاداری</span>
    </a>

    <a href="#activity">
        @include(
            'management.partials.icon',
            [
                'name' => 'calendar',
                'size' => 18,
            ]
        )
        <span>فعالیت‌های من</span>
    </a>
@endsection

@php
    $portalUser =
        auth('web')->user()
        ?? request()->user();

    $stats =
        $portalData['stats'];

    $money =
        static fn (
            int|float|string|null $value
        ): string =>
            number_format(
                (int) ($value ?? 0)
            );

    $statusLabel =
        static function (
            mixed $value
        ): string {
            $status =
                is_object($value)
                    ? (
                        $value->value
                        ?? (string) $value
                    )
                    : (string) $value;

            return match ($status) {
                'open' => 'باز',
                'assigned' => 'تخصیص داده شده',
                'in_progress' => 'در حال انجام',
                'awaiting_confirmation' => 'در انتظار تأیید',
                'completed' => 'تکمیل شده',
                'cancelled' => 'لغو شده',
                'pending' => 'در انتظار',
                'payment_pending' => 'در انتظار پرداخت',
                'approved' => 'تأیید شده',
                'confirmed' => 'قطعی',
                'rejected' => 'رد شده',
                'expired' => 'منقضی',
                'issued' => 'صادر شده',
                'partial' => 'پرداخت ناقص',
                'paid' => 'پرداخت شده',
                'overdue' => 'سررسید گذشته',
                'draft' => 'پیش‌نویس',
                'waiting_user' => 'در انتظار کاربر',
                'resolved' => 'حل شده',
                'closed' => 'بسته شده',
                'invited' => 'دعوت شده',
                'entered' => 'وارد شده',
                'exited' => 'خارج شده',
                default => $status ?: '—',
            };
        };

    $statusTone =
        static function (
            mixed $value
        ): string {
            $status =
                is_object($value)
                    ? (
                        $value->value
                        ?? (string) $value
                    )
                    : (string) $value;

            return match ($status) {
                'paid',
                'completed',
                'confirmed',
                'approved',
                'resolved',
                'closed',
                'exited' => 'success',

                'pending',
                'payment_pending',
                'partial',
                'open',
                'assigned',
                'in_progress',
                'awaiting_confirmation',
                'waiting_user',
                'invited',
                'entered' => 'warning',

                'overdue',
                'rejected' => 'danger',

                'cancelled',
                'expired' => 'muted',

                default => 'info',
            };
        };
@endphp

<section class="portal-hero portal-hero--resident">
    <div class="portal-hero__copy">
        <span class="portal-eyebrow">
            RESIDENT HOME
        </span>

        <h2>
            سلام
            {{ $portalUser->first_name ?: 'کاربر Buildino' }}
        </h2>

        <p>
            وضعیت واحد، صورتحساب‌ها، مهمان‌ها، رزرو امکانات،
            خدمات و پشتیبانی خود را از همین صفحه مدیریت کنید.
        </p>

        <div class="portal-hero__meta">
            <span>
                {{
                    count(
                        $portalData['units']
                    )
                }}
                واحد مرتبط
            </span>

            <span>
                بروزرسانی:
                {{
                    $portalData[
                        'generated_at_jalali'
                    ]
                }}
            </span>
        </div>
    </div>

    <div class="portal-hero__actions">
        <button
            type="button"
            class="portal-action-button"
            data-bs-toggle="modal"
            data-bs-target="#guestModal"
        >
            @include(
                'management.partials.icon',
                [
                    'name' => 'user-plus',
                    'size' => 18,
                ]
            )
            ثبت مهمان
        </button>

        <button
            type="button"
            class="portal-action-button"
            data-bs-toggle="modal"
            data-bs-target="#serviceModal"
        >
            @include(
                'management.partials.icon',
                [
                    'name' => 'tools',
                    'size' => 18,
                ]
            )
            درخواست خدمت
        </button>

        <button
            type="button"
            class="portal-action-button"
            data-bs-toggle="modal"
            data-bs-target="#supportModal"
        >
            @include(
                'management.partials.icon',
                [
                    'name' => 'support',
                    'size' => 18,
                ]
            )
            تیکت پشتیبانی
        </button>

        <button
            type="button"
            class="portal-action-button portal-action-button--primary"
            data-bs-toggle="modal"
            data-bs-target="#reservationModal"
        >
            @include(
                'management.partials.icon',
                [
                    'name' => 'calendar',
                    'size' => 18,
                ]
            )
            رزرو امکانات
        </button>
    </div>
</section>

<section class="portal-stat-grid">
    <article class="portal-stat-card">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'building',
                    'size' => 21,
                ]
            )
        </span>
        <div>
            <span>واحدهای من</span>
            <strong>
                {{
                    number_format(
                        $stats['units']
                    )
                }}
            </strong>
            <small>واحد فعال</small>
        </div>
    </article>

    <article class="portal-stat-card portal-stat-card--wallet">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'wallet',
                    'size' => 21,
                ]
            )
        </span>
        <div>
            <span>موجودی کیف پول واحدها</span>
            <strong>
                {{
                    $money(
                        $stats[
                            'unit_wallet_available'
                        ]
                    )
                }}
            </strong>
            <small>IRR</small>
        </div>
    </article>

    <article class="portal-stat-card portal-stat-card--danger">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'invoice',
                    'size' => 21,
                ]
            )
        </span>
        <div>
            <span>مطالبات باز</span>
            <strong>
                {{
                    $money(
                        $stats[
                            'outstanding_total'
                        ]
                    )
                }}
            </strong>
            <small>IRR</small>
        </div>
    </article>

    <article class="portal-stat-card portal-stat-card--success">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'calendar',
                    'size' => 21,
                ]
            )
        </span>
        <div>
            <span>رزرو فعال</span>
            <strong>
                {{
                    number_format(
                        $stats[
                            'active_reservations'
                        ]
                    )
                }}
            </strong>
            <small>رزرو</small>
        </div>
    </article>
</section>

<section
    class="portal-section"
    id="units"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                MY UNITS
            </span>
            <h3>
                واحدهای من
            </h3>
        </div>
    </div>

    <div class="portal-unit-grid">
        @foreach ($portalData['units'] as $unit)
            <article
                class="portal-unit-card"
                data-unit-id="{{ $unit['id'] }}"
                data-building-id="{{ $unit['building_id'] }}"
            >
                <div class="portal-unit-card__top">
                    <span class="portal-unit-card__role">
                        {{
                            $unit[
                                'relationship'
                            ]['label']
                        }}
                    </span>

                    <span>
                        {{
                            $unit['building_title']
                        }}
                    </span>
                </div>

                <h4>
                    {{ $unit['title'] }}
                </h4>

                <p>
                    {{
                        $unit['complex_title']
                        ?: '—'
                    }}
                    •
                    {{
                        $unit['block_title']
                        ?: '—'
                    }}
                    •
                    {{
                        $unit['floor_title']
                        ?: '—'
                    }}
                </p>

                <div class="portal-unit-card__facts">
                    <div>
                        <span>متراژ</span>
                        <strong>
                            {{
                                number_format(
                                    $unit['area'],
                                    0
                                )
                            }}
                            متر
                        </strong>
                    </div>

                    <div>
                        <span>کیف پول واحد</span>
                        <strong>
                            {{
                                $money(
                                    data_get(
                                        $unit,
                                        'wallet.available_balance'
                                    )
                                )
                            }}
                        </strong>
                    </div>
                </div>

                @if (
                    $portalData[
                        'payment_gateway'
                    ]['enabled']
                )
                    <button
                        type="button"
                        class="portal-inline-action"
                        data-open-topup
                        data-unit-id="{{ $unit['id'] }}"
                        data-building-id="{{ $unit['building_id'] }}"
                        data-unit-title="{{ $unit['title'] }}"
                    >
                        افزایش موجودی
                    </button>
                @endif
            </article>
        @endforeach
    </div>
</section>

<section
    class="portal-section"
    id="finance"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                SERVER-SIDE FINANCE
            </span>
            <h3>
                صورتحساب‌ها
            </h3>
        </div>

        <div class="portal-section__actions">
            @if (
                ! $portalData[
                    'payment_gateway'
                ]['enabled']
            )
                <span class="portal-section__note">
                    درگاه پرداخت هنوز در محیط جاری فعال نشده است.
                </span>
            @endif

            <a
                href="{{
                    route(
                        'portal.resident.operations.index',
                        [
                            'resource' =>
                                'invoices',
                        ]
                    )
                }}"
                class="portal-section-link"
            >
                مشاهده همه / پرداخت آنلاین
            </a>
        </div>
    </div>

    <div class="portal-datatable-card">
        @include(
            'shared.server-datatable',
            [
                'id' =>
                    'resident-dashboard-invoices',

                'url' =>
                    route(
                        'portal.resident.datatables',
                        [
                            'table' =>
                                'invoices',
                        ]
                    ),

                'columns' =>
                    config(
                        'portal_operations.resident.invoices.columns',
                        []
                    ),

                'pageLength' =>
                    5,
            ]
        )
    </div>
</section>

<section class="portal-section" id="loyalty">
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">LOYALTY</span>
            <h3>باشگاه وفاداری</h3>
        </div>

        <div class="portal-section__actions">
            <span class="portal-section__note">
                امتیاز قابل استفاده:
                <strong data-loyalty-balance>
                    {{ number_format(data_get($portalData, 'loyalty.balance', 0)) }}
                </strong>
            </span>
        </div>
    </div>

    <div class="portal-unit-grid">
        @forelse (data_get($portalData, 'loyalty.rewards', []) as $reward)
            <article class="portal-unit-card">
                <div class="portal-unit-card__top">
                    <span class="portal-unit-card__role">
                        {{ number_format($reward['required_points']) }} امتیاز
                    </span>
                    <span>جایزه</span>
                </div>

                <h4>{{ $reward['title'] }}</h4>
                <p>{{ $reward['description'] ?: 'بدون توضیح تکمیلی' }}</p>

                <button
                    type="button"
                    class="portal-inline-action"
                    data-loyalty-claim
                    data-reward-id="{{ $reward['id'] }}"
                    @disabled(! $reward['can_claim'])
                >
                    {{ $reward['can_claim'] ? 'دریافت جایزه' : 'امتیاز ناکافی' }}
                </button>
            </article>
        @empty
            <article class="portal-unit-card">
                <h4>جایزه فعالی وجود ندارد</h4>
                <p>جوایز فعال ساختمان در این بخش نمایش داده می‌شوند.</p>
            </article>
        @endforelse
    </div>

    @if (data_get($portalData, 'loyalty.claims', []))
        <div class="portal-detail-timeline mt-4">
            @foreach (data_get($portalData, 'loyalty.claims', []) as $claim)
                <article>
                    <span class="portal-detail-timeline__dot"></span>
                    <div>
                        <strong>{{ $claim['title'] }}</strong>
                        <p>
                            {{ number_format($claim['points']) }} امتیاز
                            •
                            {{ $statusLabel($claim['status']) }}
                        </p>
                        <time>{{ $claim['claimed_at_jalali'] ?: '—' }}</time>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section
    class="portal-section"
    id="wallet-history"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                WALLET HISTORY
            </span>
            <h3>
                تاریخچه کیف پول
            </h3>
        </div>

        <div class="portal-section__actions">
            <span class="portal-section__note">
                آخرین تراکنش‌های کیف پول شخصی و واحدهای مرتبط
            </span>

            <a
                href="{{
                    route(
                        'portal.resident.operations.index',
                        [
                            'resource' =>
                                'wallet',
                        ]
                    )
                }}"
                class="portal-section-link"
            >
                مشاهده همه تراکنش‌ها
            </a>
        </div>
    </div>

    <div class="portal-wallet-history-grid">
        <article class="portal-wallet-history-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'wallet',
                            'size' => 19,
                        ]
                    )

                    <div>
                        <strong>
                            کیف پول شخصی
                        </strong>

                        <span>
                            موجودی قابل استفاده:
                            {{
                                $money(
                                    data_get(
                                        $portalData,
                                        'personal_wallet.available_balance',
                                        0
                                    )
                                )
                            }}
                            {{
                                data_get(
                                    $portalData,
                                    'personal_wallet.currency',
                                    'IRR'
                                )
                            }}
                        </span>
                    </div>
                </div>
            </header>

            <div class="portal-wallet-entry-list">
                @forelse (
                    data_get(
                        $portalData,
                        'personal_wallet.entries',
                        []
                    )
                    as $entry
                )
                    <div class="portal-wallet-entry">
                        <span class="portal-wallet-entry__type portal-wallet-entry__type--{{
                            $entry['entry_type']
                        }}">
                            {{
                                $entry['entry_type']
                                    === 'credit'
                                        ? '+'
                                        : '-'
                            }}
                        </span>

                        <div>
                            <strong>
                                {{
                                    $money(
                                        $entry['amount']
                                    )
                                }}
                            </strong>

                            <span>
                                {{
                                    $entry['description']
                                    ?: 'تراکنش کیف پول'
                                }}
                            </span>
                        </div>

                        <time>
                            {{
                                $entry[
                                    'created_at_jalali'
                                ]
                                ?: '—'
                            }}
                        </time>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        تراکنشی در کیف پول شخصی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        </article>

        @foreach ($portalData['units'] as $unit)
            <article class="portal-wallet-history-card">
                <header>
                    <div>
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'building',
                                'size' => 19,
                            ]
                        )

                        <div>
                            <strong>
                                کیف پول
                                {{ $unit['title'] }}
                            </strong>

                            <span>
                                موجودی قابل استفاده:
                                {{
                                    $money(
                                        data_get(
                                            $unit,
                                            'wallet.available_balance',
                                            0
                                        )
                                    )
                                }}
                                {{
                                    data_get(
                                        $unit,
                                        'wallet.currency',
                                        'IRR'
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </header>

                <div class="portal-wallet-entry-list">
                    @forelse (
                        data_get(
                            $unit,
                            'wallet.entries',
                            []
                        )
                        as $entry
                    )
                        <div class="portal-wallet-entry">
                            <span class="portal-wallet-entry__type portal-wallet-entry__type--{{
                                $entry['entry_type']
                            }}">
                                {{
                                    $entry['entry_type']
                                        === 'credit'
                                            ? '+'
                                            : '-'
                                }}
                            </span>

                            <div>
                                <strong>
                                    {{
                                        $money(
                                            $entry['amount']
                                        )
                                    }}
                                </strong>

                                <span>
                                    {{
                                        $entry['description']
                                        ?: 'تراکنش کیف پول واحد'
                                    }}
                                </span>
                            </div>

                            <time>
                                {{
                                    $entry[
                                        'created_at_jalali'
                                    ]
                                    ?: '—'
                                }}
                            </time>
                        </div>
                    @empty
                        <div class="portal-empty-mini">
                            تراکنشی برای این کیف پول ثبت نشده است.
                        </div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
</section>

<section
    class="portal-section"
    id="activity"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                ACTIVITY
            </span>
            <h3>
                عملیات و درخواست‌های من
            </h3>
        </div>
    </div>

    <div class="portal-activity-grid">
        <article class="portal-list-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'calendar',
                            'size' => 19,
                        ]
                    )
                    <strong>رزروها</strong>
                </div>

                <a
                    href="{{ route('portal.resident.operations.index', ['resource' => 'reservations']) }}"
                    class="portal-mini-link"
                >
                    همه
                </a>

                <span>
                    {{
                        $stats[
                            'active_reservations'
                        ]
                    }}
                    فعال
                </span>
            </header>

            <div class="portal-list">
                @forelse (
                    $portalData[
                        'reservations'
                    ]
                    as $reservation
                )
                    @php
                        $reservationStatus =
                            is_object(
                                $reservation->status
                            )
                                ? $reservation
                                    ->status
                                    ->value
                                : $reservation
                                    ->status;
                    @endphp

                    <div class="portal-list-item portal-list-item--actions">
                        <div>
                            <strong>
                                {{
                                    $reservation
                                        ->buildingFacility
                                        ?->title
                                    ?: 'امکانات ساختمان'
                                }}
                            </strong>

                            <span>
                                {{
                                    $reservation
                                        ->unit
                                        ?->title
                                    ?: $reservation
                                        ->unit
                                        ?->unit_number
                                }}
                                •
                                {{
                                    $reservation
                                        ->reservation_date
                                        ?->format(
                                            'Y-m-d'
                                        )
                                }}
                            </span>

                            <div class="portal-row-actions">
                                @if (
                                    $reservationStatus
                                    === 'payment_pending'
                                )
                                    <button
                                        type="button"
                                        data-reservation-pay
                                        data-reservation-id="{{
                                            $reservation->id
                                        }}"
                                        data-reservation-title="{{
                                            $reservation
                                                ->buildingFacility
                                                ?->title
                                            ?: 'رزرو'
                                        }}"
                                    >
                                        پرداخت از کیف پول
                                    </button>
                                @endif

                                @if (
                                    in_array(
                                        $reservationStatus,
                                        [
                                            'pending',
                                            'payment_pending',
                                            'approved',
                                            'confirmed',
                                        ],
                                        true
                                    )
                                )
                                    <button
                                        type="button"
                                        class="is-danger"
                                        data-reservation-cancel
                                        data-reservation-id="{{
                                            $reservation->id
                                        }}"
                                    >
                                        لغو رزرو
                                    </button>
                                @endif
                            </div>
                        </div>

                        <span class="portal-status portal-status--{{
                            $statusTone(
                                $reservation
                                    ->status
                            )
                        }}">
                            {{
                                $statusLabel(
                                    $reservation
                                        ->status
                                )
                            }}
                        </span>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        رزروی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        </article>

        <article class="portal-list-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'user-plus',
                            'size' => 19,
                        ]
                    )
                    <strong>مهمان‌ها</strong>
                </div>

                <a
                    href="{{ route('portal.resident.operations.index', ['resource' => 'guests']) }}"
                    class="portal-mini-link"
                >
                    همه
                </a>

                <span>
                    {{
                        $stats[
                            'active_guests'
                        ]
                    }}
                    فعال
                </span>
            </header>

            <div class="portal-list">
                @forelse (
                    $portalData[
                        'guest_visits'
                    ]
                    as $visit
                )
                    <div class="portal-list-item">
                        <div>
                            <strong>
                                {{
                                    trim(
                                        (
                                            $visit
                                                ->guest
                                                ?->first_name
                                            ?? ''
                                        )
                                        . ' '
                                        . (
                                            $visit
                                                ->guest
                                                ?->last_name
                                            ?? ''
                                        )
                                    )
                                    ?: 'مهمان'
                                }}
                            </strong>

                            <span>
                                {{
                                    $visit
                                        ->unit
                                        ?->title
                                    ?: $visit
                                        ->unit
                                        ?->unit_number
                                }}
                                •
                                {{
                                    $visit
                                        ->expected_entry_at
                                        ?->format(
                                            'Y-m-d H:i'
                                        )
                                    ?: 'بدون زمان مشخص'
                                }}
                            </span>

                            @php
                                $visitStatus =
                                    is_object(
                                        $visit->status
                                    )
                                        ? $visit
                                            ->status
                                            ->value
                                        : $visit
                                            ->status;
                            @endphp

                            @if (
                                $visitStatus
                                === 'invited'
                            )
                                <div class="portal-row-actions">
                                    <button
                                        type="button"
                                        class="is-danger"
                                        data-guest-cancel
                                        data-guest-visit-id="{{
                                            $visit->id
                                        }}"
                                    >
                                        لغو دعوت
                                    </button>
                                </div>
                            @endif
                        </div>

                        <span class="portal-status portal-status--{{
                            $statusTone(
                                $visit->status
                            )
                        }}">
                            {{
                                $statusLabel(
                                    $visit->status
                                )
                            }}
                        </span>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        مهمانی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        </article>

        <article class="portal-list-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'tools',
                            'size' => 19,
                        ]
                    )
                    <strong>خدمات</strong>
                </div>

                <a
                    href="{{ route('portal.resident.operations.index', ['resource' => 'services']) }}"
                    class="portal-mini-link"
                >
                    همه
                </a>

                <span>
                    {{
                        $stats[
                            'active_services'
                        ]
                    }}
                    فعال
                </span>
            </header>

            <div class="portal-list">
                @forelse (
                    $portalData[
                        'service_requests'
                    ]
                    as $service
                )
                    @php
                        $serviceStatus =
                            is_object(
                                $service->status
                            )
                                ? $service
                                    ->status
                                    ->value
                                : $service
                                    ->status;

                        $pendingQuote =
                            $service
                                ->quotes
                                ->first(
                                    static fn ($quote): bool =>
                                        (
                                            is_object(
                                                $quote->status
                                            )
                                                ? $quote
                                                    ->status
                                                    ->value
                                                : $quote
                                                    ->status
                                        )
                                        === 'pending'
                                );
                    @endphp

                    <div class="portal-list-item portal-list-item--actions">
                        <div>
                            <strong>
                                {{ $service->title }}
                            </strong>

                            <span>
                                {{
                                    $service
                                        ->assignedTo
                                        ? (
                                            'مجری: '
                                            . trim(
                                                $service
                                                    ->assignedTo
                                                    ->first_name
                                                . ' '
                                                . $service
                                                    ->assignedTo
                                                    ->last_name
                                            )
                                        )
                                        : 'در انتظار تخصیص'
                                }}
                            </span>

                            @if ($pendingQuote)
                                <div class="portal-quote-inline">
                                    <span>
                                        پیشنهاد:
                                        {{
                                            $money(
                                                $pendingQuote
                                                    ->amount
                                            )
                                        }}
                                        IRR
                                    </span>

                                    <small>
                                        {{
                                            trim(
                                                (
                                                    $pendingQuote
                                                        ->provider
                                                        ?->first_name
                                                    ?? ''
                                                )
                                                . ' '
                                                . (
                                                    $pendingQuote
                                                        ->provider
                                                        ?->last_name
                                                    ?? ''
                                                )
                                            )
                                            ?: 'ارائه‌دهنده'
                                        }}
                                    </small>
                                </div>
                            @endif

                            <div class="portal-row-actions">
                                @if ($pendingQuote)
                                    <button
                                        type="button"
                                        data-service-quote-accept
                                        data-quote-id="{{
                                            $pendingQuote->id
                                        }}"
                                        data-quote-amount="{{
                                            $pendingQuote->amount
                                        }}"
                                    >
                                        پذیرش پیشنهاد
                                    </button>
                                @endif

                                @if (
                                    $serviceStatus
                                    === 'awaiting_confirmation'
                                )
                                    <button
                                        type="button"
                                        class="is-success"
                                        data-service-confirm
                                        data-service-id="{{
                                            $service->id
                                        }}"
                                    >
                                        تأیید پایان خدمت
                                    </button>
                                @endif

                                @if (
                                    ! in_array(
                                        $serviceStatus,
                                        [
                                            'completed',
                                            'cancelled',
                                        ],
                                        true
                                    )
                                )
                                    <button
                                        type="button"
                                        class="is-danger"
                                        data-service-cancel
                                        data-service-id="{{
                                            $service->id
                                        }}"
                                    >
                                        لغو درخواست
                                    </button>
                                @endif
                            </div>
                        </div>

                        <span class="portal-status portal-status--{{
                            $statusTone(
                                $service->status
                            )
                        }}">
                            {{
                                $statusLabel(
                                    $service->status
                                )
                            }}
                        </span>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        درخواست خدمتی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        </article>

        <article class="portal-list-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'support',
                            'size' => 19,
                        ]
                    )
                    <strong>پشتیبانی</strong>
                </div>

                <a
                    href="{{ route('portal.resident.operations.index', ['resource' => 'support']) }}"
                    class="portal-mini-link"
                >
                    همه
                </a>

                <span>
                    {{
                        $stats[
                            'active_tickets'
                        ]
                    }}
                    فعال
                </span>
            </header>

            <div class="portal-list">
                @forelse (
                    $portalData[
                        'support_tickets'
                    ]
                    as $ticket
                )
                    @php
                        $ticketStatus =
                            is_object(
                                $ticket->status
                            )
                                ? $ticket
                                    ->status
                                    ->value
                                : $ticket
                                    ->status;
                    @endphp

                    <div class="portal-list-item portal-list-item--actions">
                        <div>
                            <strong>
                                {{ $ticket->subject }}
                            </strong>

                            <span>
                                {{
                                    $ticket
                                        ->ticket_number
                                }}
                                •
                                {{
                                    $ticket
                                        ->supportCategory
                                        ?->title
                                    ?: 'عمومی'
                                }}
                            </span>

                            <div class="portal-row-actions">
                                <button
                                    type="button"
                                    data-ticket-conversation
                                    data-ticket-id="{{
                                        $ticket->id
                                    }}"
                                    data-ticket-number="{{
                                        $ticket
                                            ->ticket_number
                                    }}"
                                    data-ticket-subject="{{
                                        $ticket->subject
                                    }}"
                                    data-ticket-status="{{
                                        $ticketStatus
                                    }}"
                                >
                                    گفتگو
                                </button>

                                @if (
                                    in_array(
                                        $ticketStatus,
                                        [
                                            'resolved',
                                            'closed',
                                        ],
                                        true
                                    )
                                )
                                    <button
                                        type="button"
                                        data-ticket-reopen
                                        data-ticket-id="{{
                                            $ticket->id
                                        }}"
                                    >
                                        بازگشایی
                                    </button>
                                @endif
                            </div>
                        </div>

                        <span class="portal-status portal-status--{{
                            $statusTone(
                                $ticket->status
                            )
                        }}">
                            {{
                                $statusLabel(
                                    $ticket->status
                                )
                            }}
                        </span>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        تیکتی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        </article>
    </div>
</section>

{{-- Guest Modal --}}
<div
    class="modal fade"
    id="guestModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="guest"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    ثبت مهمان
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>نام</span>
                    <input
                        name="first_name"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>نام خانوادگی</span>
                    <input
                        name="last_name"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>موبایل</span>
                    <input
                        name="mobile"
                        dir="ltr"
                    >
                </label>

                <label class="portal-field">
                    <span>پلاک خودرو</span>
                    <input
                        name="vehicle_plate"
                    >
                </label>

                <label class="portal-field">
                    <span>زمان ورود مورد انتظار</span>
                    <input
                        type="datetime-local"
                        name="expected_entry_at"
                    >
                </label>

                <label class="portal-field">
                    <span>زمان خروج مورد انتظار</span>
                    <input
                        type="datetime-local"
                        name="expected_exit_at"
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>توضیحات</span>
                    <textarea
                        name="description"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت مهمان
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Service Modal --}}
<div
    class="modal fade"
    id="serviceModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="service"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    درخواست خدمات ساختمان
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>نوع خدمت</span>
                    <select
                        name="type"
                        required
                    >
                        <option value="electrical">
                            برق
                        </option>
                        <option value="plumbing">
                            آب و فاضلاب
                        </option>
                        <option value="elevator">
                            آسانسور / درب
                        </option>
                        <option value="heating">
                            موتورخانه / گرمایش
                        </option>
                        <option value="cleaning">
                            نظافت
                        </option>
                        <option value="garden">
                            فضای سبز
                        </option>
                        <option value="other">
                            سایر
                        </option>
                    </select>
                </label>

                <label class="portal-field">
                    <span>اولویت</span>
                    <select name="priority">
                        <option value="normal">عادی</option>
                        <option value="low">کم</option>
                        <option value="high">زیاد</option>
                        <option value="urgent">فوری</option>
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>عنوان</span>
                    <input
                        name="title"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>شرح درخواست</span>
                    <textarea
                        name="description"
                        rows="4"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت درخواست
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Support Modal --}}
<div
    class="modal fade"
    id="supportModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="support"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    ثبت تیکت پشتیبانی
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>دسته‌بندی</span>
                    <select name="support_category_id">
                        <option value="">
                            عمومی
                        </option>
                        @foreach (
                            $portalData[
                                'support_categories'
                            ]
                            as $category
                        )
                            <option
                                value="{{ $category->id }}"
                            >
                                {{ $category->title }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>اولویت</span>
                    <select name="priority">
                        <option value="medium">عادی</option>
                        <option value="low">کم</option>
                        <option value="high">زیاد</option>
                        <option value="urgent">فوری</option>
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>موضوع</span>
                    <input
                        name="subject"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>شرح مشکل</span>
                    <textarea
                        name="description"
                        rows="4"
                        required
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت تیکت
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Reservation Modal --}}
<div
    class="modal fade"
    id="reservationModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="reservation"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    رزرو امکانات
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>امکانات</span>
                    <select
                        name="facility_id"
                        required
                    >
                        @foreach (
                            $portalData[
                                'facilities'
                            ]
                            as $facility
                        )
                            <option
                                value="{{
                                    $facility->id
                                }}"
                                data-building-id="{{
                                    $facility
                                        ->building_id
                                }}"
                            >
                                {{
                                    $facility
                                        ->building
                                        ?->title
                                }}
                                /
                                {{
                                    $facility
                                        ->title
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>تاریخ رزرو</span>
                    <input
                        type="date"
                        name="reservation_date"
                        min="{{ now()->toDateString() }}"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>ساعت شروع</span>
                    <input
                        type="time"
                        name="start_time"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>ساعت پایان</span>
                    <input
                        type="time"
                        name="end_time"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>توضیحات</span>
                    <textarea
                        name="description"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                    @disabled(
                        $portalData[
                            'facilities'
                        ]->isEmpty()
                    )
                >
                    ثبت رزرو
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Reservation Wallet Payment Modal --}}
<div
    class="modal fade"
    id="reservationPaymentModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="reservation-payment"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    پرداخت رزرو از کیف پول
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <input
                    type="hidden"
                    name="reservation_id"
                >

                <div class="portal-topup-target portal-field--wide">
                    <span>
                        رزرو:
                    </span>
                    <strong data-reservation-payment-title>
                        —
                    </strong>
                </div>

                <label class="portal-field portal-field--wide">
                    <span>
                        منبع پرداخت
                    </span>

                    <select
                        name="payer_source"
                        required
                    >
                        <option value="unit_wallet">
                            کیف پول واحد
                        </option>
                        <option value="user_wallet">
                            کیف پول شخصی
                        </option>
                    </select>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    پرداخت رزرو
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Ticket Conversation Modal --}}
<div
    class="modal fade"
    id="ticketConversationModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content portal-modal">
            <div class="modal-header">
                <div>
                    <span class="portal-eyebrow">
                        SUPPORT CHAT
                    </span>
                    <h5
                        class="modal-title"
                        data-ticket-conversation-title
                    >
                        گفتگو با پشتیبانی
                    </h5>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body">
                <div
                    class="portal-ticket-conversation"
                    data-ticket-message-list
                >
                    <div class="portal-empty-mini">
                        در حال دریافت پیام‌ها...
                    </div>
                </div>

                <form
                    class="portal-ticket-reply"
                    data-ticket-reply-form
                >
                    <input
                        type="hidden"
                        name="ticket_id"
                    >

                    <textarea
                        name="message"
                        rows="3"
                        placeholder="پیام خود را بنویسید..."
                        required
                    ></textarea>

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        ارسال پیام
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@if (
    $portalData[
        'payment_gateway'
    ]['enabled']
)
<div
    class="modal fade"
    id="topUpModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="topup"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    افزایش موجودی کیف پول
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <input
                    type="hidden"
                    name="unit_id"
                >
                <input
                    type="hidden"
                    name="building_id"
                >

                <div class="portal-topup-target portal-field--wide">
                    <span>
                        کیف پول:
                    </span>
                    <strong data-topup-title>
                        واحد
                    </strong>
                </div>

                <label class="portal-field portal-field--wide">
                    <span>مبلغ</span>
                    <input
                        type="number"
                        name="amount"
                        min="1"
                        required
                        dir="ltr"
                    >
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ادامه پرداخت
                </button>
            </div>
        </form>
    </div>
</div>
@endif
