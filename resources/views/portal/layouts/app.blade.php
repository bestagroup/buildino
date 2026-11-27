<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/buildino-mark.svg') }}">

    <title>
        @yield('title', 'پرتال Buildino')
    </title>

    <link rel="stylesheet" href="{{ asset('css/buildino-fonts.css') }}">

    <link rel="stylesheet" href="{{ \App\Support\UiAssets::url('bootstrap.css') }}" crossorigin="anonymous">

    <link rel="stylesheet" href="{{ \App\Support\UiAssets::url('sweetalert2.css') }}">

    @vite('resources/js/app.js')

    <link rel="stylesheet" href="{{ asset('css/buildino-datatables.css') }}">

    <link rel="stylesheet" href="{{ asset('css/buildino-foundation.css') }}">

    <link rel="stylesheet" href="{{ asset('css/buildino-portal.css') }}">

    @stack('styles')

    <link rel="stylesheet" href="{{ asset('css/buildino-design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-action-layout.css') }}?v={{ filemtime(public_path('css/buildino-action-layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-field-focus.css') }}?v={{ filemtime(public_path('css/buildino-field-focus.css')) }}">

    <link rel="stylesheet" href="{{ asset('css/buildino-brand.css') }}?v={{ filemtime(public_path('css/buildino-brand.css')) }}">

    <link rel="stylesheet" href="{{ asset('css/buildino-core-flows.css') }}">
    @stack('portal-theme')
    @if(($portalData['area'] ?? 'resident') === 'resident')
    <link rel="stylesheet" href="{{ asset('css/buildino-resident-workspace.css') }}?v={{ filemtime(public_path('css/buildino-resident-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-facility-booking.css') }}?v={{ filemtime(public_path('css/buildino-facility-booking.css')) }}">
    @else
    <link rel="stylesheet" href="{{ asset('css/buildino-provider-workspace.css') }}?v={{ filemtime(public_path('css/buildino-provider-workspace.css')) }}">
    @endif
</head>

@php
    $portalUser =
        auth('web')->user()
        ?? request()->user();

    $header =
        $portalData['header']
        ?? [];

    $wallet =
        $header['wallet']
        ?? [];

    $notifications =
        $header['notifications']
        ?? [];

    $notificationItems =
        $notifications['items']
        ?? [];

    $unreadCount =
        (int) (
            $notifications[
                'unread_count'
            ]
            ?? 0
        );

    $activeArea =
        $portalData['area']
        ?? 'resident';

    /*
    |--------------------------------------------------------------------------
    | Portal JavaScript Bootstrap Values
    |--------------------------------------------------------------------------
    |
    | Keep Blade @json directives simple. Complex nested expressions inside
    | multiline @json(...) directives can be compiled incorrectly by Blade.
    |
    */

    $portalCsrfToken =
        csrf_token();

    $portalNotificationReadUrl =
        url(
            '/api/v1/notifications'
        );

    $portalNotificationReadAllUrl =
        url(
            '/api/v1/notifications/read-all'
        );

    $portalDefaultGateway =
        data_get(
            $portalData,
            'payment_gateway.default',
            config(
                'payment_gateways.default',
                'generic'
            )
        );

    $portalGatewayEnabled =
        (bool) data_get(
            $portalData,
            'payment_gateway.enabled',
            false
        );
@endphp

<body
    class="portal-body {{ $activeArea === 'resident' ? 'portal-resident' : 'portal-provider' }} @yield('portal-body-class')"
    data-portal-user-id="{{ $portalUser?->getKey() }}"
>
@include('shared.login-success')
<a class="buildino-skip-link" href="#mainContent">پرش به محتوای اصلی</a>
<div class="portal-shell">
    <aside
        class="portal-sidebar"
        id="portalSidebar"
        aria-label="منوی پرتال"
    >
        <button type="button" class="resident-sidebar-close" data-resident-sidebar-close aria-label="بستن منو"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
        <a
            href="{{ route('portal.dashboard') }}"
            class="portal-brand"
        >
            <img class="buildino-brand-image buildino-brand-image--horizontal-light" src="{{ asset('images/buildino-logo-horizontal-light.svg') }}" alt="بیلدینو Buildino" decoding="async">
            <span class="buildino-brand-context">پرتال کاربران</span>
        </a>

        <div class="portal-user-card">
            <div class="portal-avatar">
                {{
                    mb_substr(
                        $portalUser->first_name
                        ?: $portalUser->last_name
                        ?: 'U',
                        0,
                        1
                    )
                }}
            </div>

            <div>
                <strong>
                    {{
                        trim(
                            ($portalUser->first_name ?? '')
                            . ' '
                            . ($portalUser->last_name ?? '')
                        )
                        ?: $portalUser->mobile
                    }}
                </strong>

                <span>
                    @if ($activeArea === 'provider')
                        ارائه‌دهنده خدمات
                    @else
                        مالک / ساکن
                    @endif
                </span>
            </div>
        </div>

        <nav class="portal-nav">
            @if ($portalAreas['resident'] ?? false)
                <a
                    href="{{ route('portal.resident.dashboard') }}"
                    class="{{
                        $activeArea === 'resident' && request()->routeIs('portal.resident.dashboard')
                            ? 'is-active'
                            : ''
                    }}"
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'home',
                            'size' => 19,
                        ]
                    )

                    <span>
                        خانه من
                    </span>
                </a>
            @endif

            @if ($portalAreas['provider'] ?? false)
                <a
                    href="{{ route('portal.provider.dashboard') }}"
                    class="{{
                        $activeArea === 'provider' && request()->routeIs('portal.provider.dashboard')
                            ? 'is-active'
                            : ''
                    }}"
                    @if($activeArea === 'provider' && request()->routeIs('portal.provider.dashboard')) aria-current="page" @endif
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'tools',
                            'size' => 19,
                        ]
                    )

                    <span>
                        پنل ارائه‌دهنده
                    </span>
                </a>
            @endif

            @if($activeArea === 'resident')
                @include('portal.resident.navigation')
            @else
                @include('portal.provider.navigation')
            @endif
        </nav>

        <div class="portal-sidebar__footer">
            <div class="portal-sidebar__security">
                @include(
                    'management.partials.icon',
                    [
                        'name' => 'shield',
                        'size' => 16,
                    ]
                )

                <span>
                    دسترسی امن و محدود به اطلاعات حساب شما
                </span>
            </div>

            @if ($portalUser && app(\App\Services\Web\ManagementDashboardAccessService::class)->hasAnyAccess($portalUser))
                <a
                    href="{{ route('management.dashboard') }}"
                    class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                    data-management-dashboard-link
                >
                    @include('management.partials.icon', ['name' => 'building', 'size' => 17])
                    <span>داشبورد مدیریت</span>
                </a>
            @endif

            <form
                method="POST"
                action="{{ route('portal.logout') }}"
            >
                @csrf

                <button
                    type="submit"
                    class="portal-logout"
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'logout',
                            'size' => 17,
                        ]
                    )

                    خروج از حساب
                </button>
            </form>
        </div>
    </aside>

    <button
        type="button"
        class="portal-sidebar-backdrop"
        id="portalSidebarBackdrop"
        aria-label="بستن منو"
    ></button>

    <main class="portal-main" id="mainContent" tabindex="-1">
        <header class="portal-topbar">
            <div class="portal-topbar__title">
                <button
                    type="button"
                    class="portal-icon-button portal-mobile-menu"
                    id="portalSidebarToggle"
                    aria-controls="portalSidebar"
                    aria-expanded="false"
                    aria-label="نمایش منو"
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'menu',
                            'size' => 20,
                        ]
                    )
                </button>

                <div>
                    <span>
                        پرتال BUILDINO
                    </span>

                    <h1>
                        @yield(
                            'page-title',
                            'پرتال کاربری'
                        )
                    </h1>
                </div>
            </div>

            <div class="portal-topbar__actions">
                @if(isset($residentWalletHeader))
                    <div class="dropdown">
                        <button type="button" class="portal-wallet-chip" data-bs-toggle="dropdown" aria-expanded="false" aria-label="کیف پول واحدهای من">
                            <span class="portal-wallet-chip__icon">@include('management.partials.icon', ['name' => 'wallet', 'size' => 18])</span>
                            <span>
                                <small>{{ $residentWalletHeader['selected']['label'] ?? 'کیف پول واحدهای من' }}</small>
                                <strong>
                                    @if($residentWalletHeader['selected'])
                                        {{ number_format($residentWalletHeader['selected']['available_balance']) }}
                                        <em>{{ $residentWalletHeader['selected']['currency'] === 'IRT' ? 'تومان' : 'ریال' }}</em>
                                    @else
                                        {{ count($residentWalletHeader['wallets']) ? 'انتخاب واحد' : 'واحد فعالی ندارید' }}
                                    @endif
                                </strong>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @forelse($residentWalletHeader['wallets'] as $unitWallet)
                                <li><a class="dropdown-item" href="{{ route('portal.resident.operations.index', ['resource' => 'wallet', 'unit_id' => $unitWallet['unit_id']]) }}">
                                    {{ $unitWallet['label'] }} — {{ number_format($unitWallet['available_balance']) }} {{ $unitWallet['currency'] === 'IRT' ? 'تومان' : 'ریال' }}
                                </a></li>
                            @empty
                                <li class="dropdown-item-text">واحد فعالی به حساب شما متصل نیست.</li>
                            @endforelse
                        </ul>
                    </div>
                @else
                <div class="portal-wallet-chip">
                    <span class="portal-wallet-chip__icon">
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'wallet',
                                'size' => 18,
                            ]
                        )
                    </span>

                    <div>
                        <small>
                            موجودی شخصی
                        </small>

                        <strong>
                            {{
                                number_format(
                                    (int) (
                                        $wallet[
                                            'available_balance'
                                        ]
                                        ?? 0
                                    )
                                )
                            }}
                            <em>
                                {{
                                    $wallet['currency']
                                    ?? 'IRR'
                                }}
                            </em>
                        </strong>
                    </div>
                </div>

                @endif

                <div class="dropdown">
                    <button
                        type="button"
                        class="portal-icon-button portal-notification-button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        title="اعلان‌ها"
                    >
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'bell',
                                'size' => 19,
                            ]
                        )

                        @if ($unreadCount > 0)
                            <span class="portal-notification-badge">
                                {{
                                    $unreadCount > 99
                                        ? '99+'
                                        : $unreadCount
                                }}
                            </span>
                        @endif
                    </button>

                    <div class="dropdown-menu portal-notification-menu">
                        <div class="portal-notification-menu__header">
                            <div>
                                <strong>
                                    اعلان‌ها
                                </strong>
                                <span>
                                    {{ number_format($unreadCount) }}
                                    خوانده‌نشده
                                </span>
                            </div>

                            @if ($unreadCount > 0)
                                <button
                                    type="button"
                                    data-portal-read-all
                                >
                                    خواندن همه
                                </button>
                            @endif
                        </div>

                        <div class="portal-notification-list">
                            @forelse ($notificationItems as $notification)
                                <button
                                    type="button"
                                    class="portal-notification-item {{
                                        ! ($notification['is_read'] ?? false)
                                            ? 'is-unread'
                                            : ''
                                    }}"
                                    data-notification-id="{{
                                        $notification['id']
                                    }}"
                                    @if (! empty($notification['href']))
                                        data-notification-href="{{
                                            $notification['href']
                                        }}"
                                    @endif
                                >
                                    <span class="portal-notification-item__dot"></span>

                                    <span>
                                        <strong>
                                            {{
                                                $notification['title']
                                                ?: 'اعلان Buildino'
                                            }}
                                        </strong>

                                        <small>
                                            {{
                                                \Illuminate\Support\Str::limit(
                                                    $notification['message']
                                                    ?? '',
                                                    90
                                                )
                                            }}
                                        </small>

                                        <em>
                                            {{
                                                $notification[
                                                    'created_at_jalali'
                                                ]
                                                ?? '—'
                                            }}
                                        </em>
                                    </span>
                                </button>
                            @empty
                                <div class="portal-empty-mini">
                                    اعلان جدیدی ندارید.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="portal-icon-button"
                    id="portalThemeToggle"
                    title="تغییر پوسته"
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'moon',
                            'size' => 18,
                        ]
                    )
                </button>
            </div>
        </header>

        <div class="portal-content">
            <section class="core-flow" data-pending-invitation data-resident-url="{{ route('portal.resident.dashboard') }}" hidden>
                <h2>دعوت به واحد</h2>
                <details data-invitation-entry>
                    <summary>کد دعوت دارید؟</summary>
                    <form data-invitation-code-form>
                        <div class="portal-form-grid">
                            <label class="portal-field">
                                <span>کد دعوت</span>
                                <input data-invitation-code name="invitation_code" type="text" inputmode="numeric" dir="ltr" autocomplete="off" required>
                            </label>
                        </div>
                        <div class="core-flow__actions"><button type="submit" class="is-primary">بررسی دعوت</button></div>
                    </form>
                </details>
                <div data-invitation-details></div>
                <p role="status"></p>
                <p class="core-flow__error" role="alert"></p>
                <div class="core-flow__actions">
                    <button class="is-primary" type="button" data-invitation-accept hidden>پذیرش دعوت</button>
                    <button type="button" data-invitation-retry hidden>تلاش مجدد</button>
                    <button type="button" data-invitation-dismiss>فعلاً ادامه نمی‌دهم</button>
                </div>
            </section>
            @yield('content')
        </div>
    </main>
</div>

<script>
    window.BuildinoPortal = {
        csrfToken: @json($portalCsrfToken),
        area: @json($activeArea),
        notificationReadUrl: @json($portalNotificationReadUrl),
        notificationReadAllUrl: @json($portalNotificationReadAllUrl),
        defaultGateway: @json($portalDefaultGateway),
        gatewayEnabled: @json($portalGatewayEnabled),
        invitationCodeDigits: {{ (int) config('unit_invitations.code_digits', 8) }}
    };
</script>

<script src="{{ \App\Support\UiAssets::url('bootstrap.js') }}" integrity="{{ \App\Support\UiAssets::integrity('bootstrap.js') }}" crossorigin="anonymous"></script>

<script src="{{ \App\Support\UiAssets::url('sweetalert2.js') }}"></script>

<script src="{{ asset('js/buildino-numeric.js') }}?v={{ filemtime(public_path('js/buildino-numeric.js')) }}" defer></script>
<script src="{{ asset('js/buildino-foundation.js') }}"></script>

<script src="{{ \App\Support\UiAssets::url('jdate.js') }}"></script>

<script src="{{ asset('js/buildino-datatables.js') }}" defer></script>

<script src="{{ asset('js/buildino-portal.js') }}?v={{ filemtime(public_path('js/buildino-portal.js')) }}" defer></script>

@stack('scripts')
@include('shared.core-flow-config')
<script src="{{ asset('js/buildino-core-invitation.js') }}?v={{ filemtime(public_path('js/buildino-core-invitation.js')) }}" defer></script>
<script src="{{ asset('js/buildino-resident-accessibility.js') }}?v={{ filemtime(public_path('js/buildino-resident-accessibility.js')) }}" defer></script>
@if($activeArea === 'resident')
<script src="{{ asset('js/buildino-facility-booking.js') }}?v={{ filemtime(public_path('js/buildino-facility-booking.js')) }}" defer></script>
<script src="{{ asset('js/buildino-resident-workspace.js') }}?v={{ filemtime(public_path('js/buildino-resident-workspace.js')) }}" defer></script>
@else
<script src="{{ asset('js/buildino-provider-workspace.js') }}?v={{ filemtime(public_path('js/buildino-provider-workspace.js')) }}" defer></script>
@endif
</body>
</html>
