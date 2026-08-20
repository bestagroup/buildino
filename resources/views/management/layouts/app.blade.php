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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">

    <title>@yield('title', 'پنل مدیریتی Buildino')</title>

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
        href="{{ asset('css/buildino-management.css') }}"
    >

    @stack('styles')
    <!-- Canonical Materialize adapter; loaded after operational/page styles. -->
    <link rel="stylesheet" href="{{ asset('css/buildino-materialize.css') }}">
</head>

@php
    $ui = $managementUi ?? [];
    $nav = $ui['navigation'] ?? [];
    $header = $managementHeader ?? [];
    $personalWallet = $header['wallet'] ?? [];
    $headerNotifications =
        $header['notifications'] ?? [];
    $headerNotificationItems =
        $headerNotifications['items'] ?? [];
    $headerUnreadCount =
        (int) (
            $headerNotifications[
                'unread_count'
            ] ?? 0
        );
    $currentResource = request()->route('resource');

    $resourceActive = static function (
        array $resources
    ) use ($currentResource): bool {
        return in_array(
            $currentResource,
            $resources,
            true
        );
    };

    $visibleResources = collect(
        config('management_crud.resources', [])
    )
        ->filter(
            fn (
                array $resource,
                string $key
            ): bool =>
                in_array(
                    $key,
                    $ui['visible_resources'] ?? [],
                    true
                )
        );

    $structureTarget =
        collect([
            'complexes',
            'buildings',
            'blocks',
            'floors',
            'units',
        ])->first(
            fn (string $key): bool =>
                $visibleResources->has(
                    $key
                )
        );

    $quickCreate = collect([
        'complexes',
        'buildings',
        'users',
        'guest-visits',
        'reservations',
        'invoices',
        'service-requests',
        'support-tickets',
    ])->filter(
        fn (string $key): bool =>
            $visibleResources->has($key)
            && ! empty(
                $visibleResources[$key]['create']
                ?? null
            )
    );

    $commandResources =
        $visibleResources
            ->map(
                fn (
                    array $resource,
                    string $key
                ): array => [
                    'key' => $key,
                    'title' => $resource['title'],
                    'description' =>
                        $resource['description']
                        ?? '',
                    'group' =>
                        data_get(
                            config(
                                'management_crud.groups'
                            ),
                            ($resource['group'] ?? '')
                            . '.title',
                            'عملیات'
                        ),
                    'url' =>
                        route(
                            'management.operations.show',
                            $key
                        ),
                ]
            )
            ->values();
@endphp

<body class="management-body materialize-buildino">
<div class="management-shell layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('management.layouts.partials.sidebar')

        <div
            class="layout-overlay"
            id="layoutOverlay"
            data-materialize-overlay
            aria-hidden="true"
            role="button"
            tabindex="-1"
            aria-label="بستن منو"
        ></div>

        <div class="main-area layout-page">
        <header
            class="topbar layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
            id="layout-navbar"
        >
            <div class="topbar__start layout-navbar-nav-left">
                <button
                    type="button"
                    class="materialize-mobile-toggle nav-link d-xl-none"
                    data-materialize-menu-toggle
                    aria-expanded="false"
                    aria-controls="layout-menu"
                    aria-label="نمایش منو"
                >
                    @include('management.partials.icon', ['name' => 'menu', 'size' => 22])
                </button>

                <div class="materialize-navbar-context">
                    <strong>@yield('page-title', 'پنل مدیریتی')</strong>
                    <small>{{ $ui['access_label'] ?? 'Buildino Management' }}</small>
                </div>
            </div>

            <div class="topbar__center">
                <button
                    type="button"
                    class="command-trigger"
                    id="commandTrigger"
                >
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'search',
                            'size' => 18,
                        ]
                    )

                    <span>
                        جستجو در عملیات و فرم‌ها
                    </span>

                    <kbd>Ctrl K</kbd>
                </button>
            </div>

            <div class="topbar__actions">
                <div
                    class="topbar-menu"
                    data-popover
                >
                    <button
                        type="button"
                        class="wallet-summary-trigger"
                        data-popover-trigger
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        title="کیف پول شخصی"
                    >
                        <span class="wallet-summary-trigger__icon">
                            @include(
                                'management.partials.icon',
                                [
                                    'name' => 'wallet',
                                    'size' => 18,
                                ]
                            )
                        </span>

                        <span class="wallet-summary-trigger__copy">
                            <small>موجودی قابل استفاده</small>
                            <strong
                                data-money-value="{{
                                    (int) (
                                        $personalWallet[
                                            'available_balance'
                                        ] ?? 0
                                    )
                                }}"
                            >
                                {{
                                    number_format(
                                        (int) (
                                            $personalWallet[
                                                'available_balance'
                                            ] ?? 0
                                        )
                                    )
                                }}
                            </strong>
                        </span>

                        <span class="wallet-summary-trigger__currency">
                            {{
                                $personalWallet['currency']
                                ?? 'IRR'
                            }}
                        </span>
                    </button>

                    <div
                        class="popover-menu popover-menu--wallet"
                        data-popover-menu
                    >
                        <div class="wallet-popover__hero">
                            <span>
                                @include(
                                    'management.partials.icon',
                                    [
                                        'name' => 'wallet',
                                        'size' => 22,
                                    ]
                                )
                            </span>

                            <div>
                                <small>
                                    کیف پول شخصی شما
                                </small>

                                <strong
                                    data-money-value="{{
                                        (int) (
                                            $personalWallet[
                                                'available_balance'
                                            ] ?? 0
                                        )
                                    }}"
                                >
                                    {{
                                        number_format(
                                            (int) (
                                                $personalWallet[
                                                    'available_balance'
                                                ] ?? 0
                                            )
                                        )
                                    }}
                                </strong>

                                <em>
                                    {{
                                        $personalWallet[
                                            'currency'
                                        ] ?? 'IRR'
                                    }}
                                </em>
                            </div>
                        </div>

                        <div class="wallet-popover__stats">
                            <div>
                                <span>مانده کل</span>
                                <strong
                                    data-money-value="{{
                                        (int) (
                                            $personalWallet[
                                                'balance'
                                            ] ?? 0
                                        )
                                    }}"
                                >
                                    {{
                                        number_format(
                                            (int) (
                                                $personalWallet[
                                                    'balance'
                                                ] ?? 0
                                            )
                                        )
                                    }}
                                </strong>
                            </div>

                            <div>
                                <span>مانده قفل‌شده</span>
                                <strong
                                    data-money-value="{{
                                        (int) (
                                            $personalWallet[
                                                'locked_balance'
                                            ] ?? 0
                                        )
                                    }}"
                                >
                                    {{
                                        number_format(
                                            (int) (
                                                $personalWallet[
                                                    'locked_balance'
                                                ] ?? 0
                                            )
                                        )
                                    }}
                                </strong>
                            </div>
                        </div>

                        @if (! ($personalWallet['exists'] ?? false))
                            <div class="wallet-popover__note">
                                کیف پول شخصی برای این حساب هنوز Provision نشده است.
                            </div>
                        @endif
                    </div>
                </div>

                <div
                    class="topbar-menu"
                    data-popover
                >
                    <button
                        type="button"
                        class="notification-trigger"
                        data-popover-trigger
                        aria-label="اعلان‌ها"
                        title="اعلان‌ها"
                    >
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'bell',
                                'size' => 19,
                            ]
                        )

                        <span
                            class="notification-trigger__badge"
                            id="managementNotificationBadge"
                            @if ($headerUnreadCount < 1)
                                hidden
                            @endif
                        >
                            {{
                                $headerUnreadCount > 99
                                    ? '99+'
                                    : $headerUnreadCount
                            }}
                        </span>
                    </button>

                    <div
                        class="popover-menu popover-menu--notifications"
                        data-popover-menu
                        id="managementNotificationMenu"
                    >
                        <div class="notification-menu__header">
                            <div>
                                <strong>اعلان‌ها</strong>
                                <span>
                                    <b id="managementNotificationUnreadText">
                                        {{
                                            number_format(
                                                $headerUnreadCount
                                            )
                                        }}
                                    </b>
                                    خوانده‌نشده
                                </span>
                            </div>

                            <button
                                type="button"
                                id="managementNotificationsReadAll"
                                @disabled($headerUnreadCount < 1)
                            >
                                خواندن همه
                            </button>
                        </div>

                        <div
                            class="notification-menu__list"
                            id="managementNotificationList"
                        >
                            @forelse ($headerNotificationItems as $notification)
                                <button
                                    type="button"
                                    class="notification-item {{
                                        $notification['is_read']
                                            ? ''
                                            : 'is-unread'
                                    }}"
                                    data-management-notification
                                    data-notification-id="{{
                                        $notification['id']
                                    }}"
                                    @if ($notification['href'])
                                        data-notification-href="{{
                                            $notification['href']
                                        }}"
                                    @endif
                                >
                                    <span class="notification-item__marker"></span>

                                    <span class="notification-item__body">
                                        <strong>
                                            {{
                                                $notification[
                                                    'title'
                                                ]
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                \Illuminate\Support\Str::limit(
                                                    $notification[
                                                        'message'
                                                    ],
                                                    115
                                                )
                                            }}
                                        </span>

                                        <small
                                            @if ($notification['created_at'])
                                                data-jdate="{{
                                                    $notification[
                                                        'created_at'
                                                    ]
                                                }}"
                                            @endif
                                        >
                                            {{
                                                $notification[
                                                    'created_at_jalali'
                                                ]
                                                ?? '—'
                                            }}
                                        </small>
                                    </span>
                                </button>
                            @empty
                                <div
                                    class="notification-menu__empty"
                                    id="managementNotificationEmpty"
                                >
                                    @include(
                                        'management.partials.icon',
                                        [
                                            'name' => 'bell',
                                            'size' => 26,
                                        ]
                                    )

                                    <strong>
                                        اعلان جدیدی ندارید
                                    </strong>

                                    <span>
                                        پیام‌های سیستمی و عملیاتی اینجا نمایش داده می‌شوند.
                                    </span>
                                </div>
                            @endforelse
                        </div>

                        <div class="notification-menu__footer">
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'notification-preferences'
                                    )
                                }}"
                            >
                                تنظیمات اعلان‌ها
                            </a>
                        </div>
                    </div>
                </div>

                @if ($quickCreate->isNotEmpty())
                    <div class="topbar-menu" data-popover>
                        <button
                            type="button"
                            class="quick-create-button"
                            data-popover-trigger
                        >
                            @include(
                                'management.partials.icon',
                                [
                                    'name' => 'plus',
                                    'size' => 17,
                                ]
                            )
                            <span>ثبت سریع</span>
                        </button>

                        <div
                            class="popover-menu popover-menu--quick"
                            data-popover-menu
                        >
                            <div class="popover-menu__heading">
                                ثبت رکورد جدید
                            </div>

                            @foreach ($quickCreate as $key)
                                @php
                                    $item =
                                        $visibleResources[$key];
                                @endphp

                                <a
                                    href="{{
                                        route(
                                            'management.operations.show',
                                            $key
                                        )
                                    }}?create=1"
                                >
                                    <span>
                                        {{ $item['title'] }}
                                    </span>
                                    <small>
                                        {{ $item['description'] ?? '' }}
                                    </small>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <button
                    type="button"
                    class="icon-button"
                    id="materializeThemeToggle" data-materialize-theme-toggle
                    aria-pressed="false"
                    aria-label="تغییر پوسته"
                    title="تغییر پوسته"
                >
                    <span class="theme-icon theme-icon--light">
                        @include(
                            'management.partials.icon',
                            ['name' => 'moon']
                        )
                    </span>

                    <span class="theme-icon theme-icon--dark">
                        @include(
                            'management.partials.icon',
                            ['name' => 'sun']
                        )
                    </span>
                </button>

                <div class="topbar-menu" data-popover>
                    <button
                        type="button"
                        class="user-menu-trigger"
                        data-popover-trigger
                    >
                        <div class="avatar avatar--topbar">
                            {{ mb_substr(
                                $user->first_name
                                    ?: $user->last_name
                                    ?: 'U',
                                0,
                                1
                            ) }}
                        </div>

                        <div class="user-menu-trigger__copy">
                            <strong>
                                {{
                                    trim(
                                        ($user->first_name ?? '')
                                        . ' '
                                        . ($user->last_name ?? '')
                                    )
                                    ?: $user->mobile
                                }}
                            </strong>

                            <span>
                                {{
                                    data_get(
                                        $ui,
                                        'primary_role.display_name',
                                        'کاربر'
                                    )
                                }}
                            </span>
                        </div>

                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'chevron-down',
                                'size' => 14,
                            ]
                        )
                    </button>

                    <div
                        class="popover-menu popover-menu--user"
                        data-popover-menu
                    >
                        <div class="user-popover__identity">
                            <div class="avatar avatar--popover">
                                {{ mb_substr(
                                    $user->first_name
                                        ?: $user->last_name
                                        ?: 'U',
                                    0,
                                    1
                                ) }}
                            </div>

                            <div>
                                <strong>
                                    {{
                                        trim(
                                            ($user->first_name ?? '')
                                            . ' '
                                            . ($user->last_name ?? '')
                                        )
                                        ?: $user->mobile
                                    }}
                                </strong>

                                <span dir="ltr">
                                    {{ $user->mobile ?: $user->email }}
                                </span>
                            </div>
                        </div>

                        <div class="user-popover__meta">
                            <div>
                                <span>سطح دسترسی</span>
                                <strong>
                                    {{ $ui['access_label'] ?? '—' }}
                                </strong>
                            </div>

                            <div>
                                <span>نقش اصلی</span>
                                <strong>
                                    {{
                                        data_get(
                                            $ui,
                                            'primary_role.display_name',
                                            '—'
                                        )
                                    }}
                                </strong>
                            </div>
                        </div>

                        @if (! empty($ui['roles']))
                            <div class="role-chip-list">
                                @foreach ($ui['roles'] as $role)
                                    <span class="role-chip">
                                        <strong>
                                            {{ $role['display_name'] }}
                                        </strong>
                                        <small>
                                            {{ $role['scope_label'] }}
                                        </small>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('management.logout') }}"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="popover-logout"
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
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <main class="page-content container-xxl flex-grow-1 container-p-y">
                <div class="materialize-content-header">
                    <div class="materialize-content-header__copy">
                        <h1>@yield('page-title', 'پنل مدیریتی')</h1>
                        <p>@yield('page-subtitle', 'مدیریت و پایش سامانه Buildino')</p>
                    </div>
                    <div class="materialize-content-header__breadcrumb">
                        Buildino / <b>@yield('page-title', 'مدیریت')</b>
                    </div>
                </div>

                @yield('content')
            </main>
        </div>
        </div>
    </div>
</div>

<div
    class="command-backdrop"
    id="commandBackdrop"
></div>

<section
    class="command-palette"
    id="commandPalette"
    aria-hidden="true"
    aria-label="جستجوی عملیات"
>
    <div class="command-palette__search">
        @include(
            'management.partials.icon',
            [
                'name' => 'search',
                'size' => 20,
            ]
        )

        <input
            type="search"
            id="commandSearch"
            placeholder="مثلاً: مجتمع، کاربر، صورتحساب، رزرو..."
            autocomplete="off"
        >

        <button
            type="button"
            id="commandClose"
            aria-label="بستن"
        >
            ESC
        </button>
    </div>

    <div
        class="command-palette__results"
        id="commandResults"
    ></div>

    <div class="command-palette__footer">
        <span>
            ↑ ↓ انتخاب
        </span>
        <span>
            Enter ورود
        </span>
        <span>
            Esc بستن
        </span>
    </div>
</section>

<script>
    window.BuildinoManagementUi = {
        resources:
            {{ \Illuminate\Support\Js::from($commandResources) }},
        accessLabel:
            {{ \Illuminate\Support\Js::from($ui['access_label'] ?? '') }},
        role:
            {{ \Illuminate\Support\Js::from(
                data_get(
                    $ui,
                    'primary_role.display_name',
                    ''
                )
            ) }}
    };
</script>

<script
    src="{{ config('management_ui.libraries.bootstrap.js') }}"
    integrity="{{ config('management_ui.libraries.bootstrap.js_integrity') }}"
    crossorigin="anonymous"
    defer
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
    defer
></script>

<script
    src="{{ asset('js/buildino-foundation.js') }}"
    defer
></script>

<script
    src="{{ config('management_ui.libraries.jdate.js') }}"
    defer
></script>

<script
    src="{{ asset('js/buildino-datatables.js') }}"
    defer
></script>

<script
    src="{{ asset('js/buildino-management.js') }}"
    defer
></script>

@stack('scripts')

<script src="{{ asset('js/buildino-materialize.js') }}" defer></script>
</body>
</html>
