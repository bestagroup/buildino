<!doctype html>
<html
    lang="fa"
    dir="rtl"
    class="light-style layout-menu-fixed"
    data-theme="theme-default"
    data-assets-path="{{ asset('assets/') }}/"
    data-template="vertical-menu-template"
>
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

    <!-- Materialize RTL dashboard system extracted from the supplied UI reference -->
    <link id="template-core-css" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}">
    <link id="template-theme-css" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/materialize-demo.css') }}">

    <link
        rel="stylesheet"
        href="{{ config('management_ui.libraries.sweetalert2.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ config('management_ui.libraries.datatables.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ config('management_ui.libraries.datatables.responsive_css') }}"
    >

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

    <!-- Final Materio component polish; intentionally loaded last. -->
    <link rel="stylesheet" href="{{ asset('css/buildino-materialize.css') }}">
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
    class="portal-body materialize-buildino"
    data-portal-user-id="{{ $portalUser?->getKey() }}"
>
<div class="portal-shell layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('portal.layouts.partials.sidebar')

        <div
            class="layout-overlay"
            id="portalLayoutOverlay"
            data-materialize-overlay
            aria-hidden="true"
            role="button"
            tabindex="-1"
            aria-label="بستن منو"
        ></div>

        <div class="portal-main layout-page">
        <header
            class="portal-topbar layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
            id="layout-navbar"
        >
            <div class="portal-topbar__title">
                <button
                    type="button"
                    class="portal-icon-button portal-mobile-menu"
                    id="portalLayoutMenuToggle" data-materialize-menu-toggle
                    aria-expanded="false"
                    aria-controls="layout-menu"
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
                    id="portalMaterializeThemeToggle" data-materialize-theme-toggle
                    aria-pressed="false"
                    aria-label="تغییر پوسته"
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

        <div class="content-wrapper">
            <main class="portal-content container-xxl flex-grow-1 container-p-y">
                <div class="materialize-content-header">
                    <div class="materialize-content-header__copy">
                        <h1>@yield('page-title', 'پرتال کاربری')</h1>
                        <p>{{ $activeArea === 'provider' ? 'مرکز عملیات ارائه‌دهنده خدمات' : 'مدیریت زندگی و خدمات ساختمان' }}</p>
                    </div>
                    <div class="materialize-content-header__breadcrumb">
                        Buildino / <b>@yield('page-title', 'پرتال')</b>
                    </div>
                </div>

                @yield('content')
            </main>
        </div>
        </div>
    </div>
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
    src="{{ config('management_ui.libraries.datatables.js') }}"
    defer
></script>

<script
    src="{{ config('management_ui.libraries.datatables.bootstrap5_js') }}"
    defer
></script>

<script
    src="{{ config('management_ui.libraries.datatables.responsive_js') }}"
    defer
></script>

<script
    src="{{ config('management_ui.libraries.datatables.responsive_bootstrap5_js') }}"
    defer
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

<script src="{{ asset('js/buildino-materialize.js') }}" defer></script>
</body>
</html>
