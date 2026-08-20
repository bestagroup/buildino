<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >
    <meta
        name="robots"
        content="noindex,nofollow"
    >
    <meta
        name="color-scheme"
        content="light dark"
    >

    <title>
        @yield('title', 'پرتال Buildino')
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-fonts.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ config('management_ui.libraries.bootstrap.css') }}"
        integrity="{{ config('management_ui.libraries.bootstrap.css_integrity') }}"
        crossorigin="anonymous"
    >

    <link
        rel="stylesheet"
        href="{{ config('management_ui.libraries.sweetalert2.css') }}"
    >

    @vite('resources/js/app.js')

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-datatables.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-foundation.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-portal.css') }}"
    >

    @stack('styles')
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
    class="portal-body"
    data-portal-user-id="{{ $portalUser?->getKey() }}"
>
<div class="portal-shell">
    <aside
        class="portal-sidebar"
        id="portalSidebar"
    >
        <a
            href="{{ route('portal.dashboard') }}"
            class="portal-brand"
        >
            <span class="portal-brand__mark">
                B
            </span>

            <span class="portal-brand__copy">
                <strong>Buildino</strong>
                <small>
                    پرتال کاربران
                </small>
            </span>
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
                        $activeArea === 'resident'
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
                        $activeArea === 'provider'
                            ? 'is-active'
                            : ''
                    }}"
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

            @yield('sidebar-links')
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

    <main class="portal-main">
        <header class="portal-topbar">
            <div class="portal-topbar__title">
                <button
                    type="button"
                    class="portal-icon-button portal-mobile-menu"
                    id="portalSidebarToggle"
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
                        BUILDINO PORTAL
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
        gatewayEnabled: @json($portalGatewayEnabled)
    };
</script>

<script
    src="{{ config('management_ui.libraries.bootstrap.js') }}"
    integrity="{{ config('management_ui.libraries.bootstrap.js_integrity') }}"
    crossorigin="anonymous"
></script>

<script
    src="{{ config('management_ui.libraries.sweetalert2.js') }}"
></script>

<script
    src="{{ asset('js/buildino-foundation.js') }}"
></script>

<script
    src="{{ config('management_ui.libraries.jdate.js') }}"
></script>

<script
    src="{{ asset('js/buildino-datatables.js') }}"
    defer
></script>

<script
    src="{{ asset('js/buildino-portal.js') }}"
    defer
></script>

@stack('scripts')
</body>
</html>
