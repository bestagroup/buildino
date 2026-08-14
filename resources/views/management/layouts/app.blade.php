<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
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
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-management.css') }}"
    >

    @stack('styles')
</head>

@php
    $ui = $managementUi ?? [];
    $nav = $ui['navigation'] ?? [];
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

<body class="management-body">
<div class="management-shell">
    <aside
        class="sidebar"
        id="managementSidebar"
        aria-label="منوی اصلی مدیریت"
    >
        <div class="sidebar__top">
            <a
                class="sidebar__brand"
                href="{{ route('management.dashboard') }}"
                aria-label="Buildino"
            >
                <div class="brand-mark">
                    <span>B</span>
                </div>

                <div class="brand-copy">
                    <strong>Buildino</strong>
                    <span>سامانه مدیریت هوشمند ساختمان</span>
                </div>
            </a>

            <button
                type="button"
                class="sidebar-collapse-button"
                id="sidebarCollapse"
                aria-label="جمع کردن منو"
                title="جمع کردن منو"
            >
                @include(
                    'management.partials.icon',
                    [
                        'name' => 'chevron',
                        'size' => 18,
                    ]
                )
            </button>
        </div>

        <div class="sidebar__identity">
            <div class="avatar avatar--sidebar">
                {{ mb_substr(
                    $user->first_name
                        ?: $user->last_name
                        ?: 'U',
                    0,
                    1
                ) }}
            </div>

            <div class="sidebar__identity-copy">
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
                            'کاربر سامانه'
                        )
                    }}
                </span>
            </div>

            <span
                class="access-dot"
                title="{{ $ui['access_label'] ?? 'دسترسی فعال' }}"
            ></span>
        </div>

        <div class="sidebar__scope">
            @include(
                'management.partials.icon',
                [
                    'name' => 'shield',
                    'size' => 15,
                ]
            )

            <span>
                {{ $ui['access_label'] ?? 'دسترسی مدیریتی' }}
            </span>
        </div>

        <nav class="sidebar__nav">
            <section class="nav-group is-open" data-nav-group="general">
                <button
                    type="button"
                    class="nav-group__title"
                    data-nav-toggle
                >
                    <span>عمومی</span>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'chevron',
                            'size' => 14,
                        ]
                    )
                </button>

                <div class="nav-group__items">
                    <a
                        href="{{ route('management.dashboard') }}"
                        class="nav-link {{
                            request()->routeIs('management.dashboard')
                                ? 'is-active'
                                : ''
                        }}"
                        data-nav-key="dashboard"
                    >
                        <span class="nav-link__icon">
                            @include(
                                'management.partials.icon',
                                ['name' => 'home']
                            )
                        </span>

                        <span class="nav-link__label">
                            داشبورد
                        </span>
                    </a>

                    @if ($nav['operations'] ?? false)
                        <a
                            href="{{ route('management.operations.index') }}"
                            class="nav-link {{
                                request()->routeIs('management.operations.index')
                                    ? 'is-active'
                                    : ''
                            }}"
                            data-nav-key="operations"
                        >
                            <span class="nav-link__icon">
                                @include(
                                    'management.partials.icon',
                                    ['name' => 'grid']
                                )
                            </span>

                            <span class="nav-link__label">
                                مرکز عملیات
                            </span>
                        </a>
                    @endif
                </div>
            </section>

            @if (
                ($nav['structure'] ?? false)
                || ($nav['residents'] ?? false)
                || ($nav['guests'] ?? false)
                || ($nav['facilities'] ?? false)
            )
                <section
                    class="nav-group {{
                        $resourceActive([
                            'complexes',
                            'buildings',
                            'blocks',
                            'floors',
                            'units',
                            'ownerships',
                            'occupancies',
                            'invitations',
                            'guest-visits',
                            'facilities',
                            'facility-schedules',
                            'facility-time-slots',
                            'facility-rules',
                            'facility-blackouts',
                            'reservations',
                        ])
                            ? 'is-open'
                            : ''
                    }}"
                    data-nav-group="building"
                >
                    <button
                        type="button"
                        class="nav-group__title"
                        data-nav-toggle
                    >
                        <span>ساختمان و ساکنین</span>
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'chevron',
                                'size' => 14,
                            ]
                        )
                    </button>

                    <div class="nav-group__items">
                        @if ($nav['structure'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'complexes'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'complexes',
                                        'buildings',
                                        'blocks',
                                        'floors',
                                        'units',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="structure"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'building']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    ساختار مجتمع
                                </span>
                            </a>
                        @endif

                        @if ($nav['residents'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'occupancies'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'ownerships',
                                        'occupancies',
                                        'invitations',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="residents"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'users']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    مالکین و ساکنین
                                </span>
                            </a>
                        @endif

                        @if ($nav['guests'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'guest-visits'
                                    )
                                }}"
                                class="nav-link {{
                                    $currentResource === 'guest-visits'
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="guests"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'user-plus']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    مهمان و تردد
                                </span>
                            </a>
                        @endif

                        @if ($nav['facilities'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'facilities'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'facilities',
                                        'facility-schedules',
                                        'facility-time-slots',
                                        'facility-rules',
                                        'facility-blackouts',
                                        'reservations',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="facilities"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'calendar']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    امکانات و رزرو
                                </span>
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            @if (
                ($nav['finance'] ?? false)
                || ($nav['services'] ?? false)
                || ($nav['support'] ?? false)
            )
                <section
                    class="nav-group {{
                        $resourceActive([
                            'charge-formulas',
                            'charge-periods',
                            'invoices',
                            'expenses',
                            'incomes',
                            'payments',
                            'bank-accounts',
                            'wallet-payouts',
                            'bill-payments',
                            'service-requests',
                            'support-tickets',
                            'support-categories',
                            'support-sla',
                        ])
                            ? 'is-open'
                            : ''
                    }}"
                    data-nav-group="operations"
                >
                    <button
                        type="button"
                        class="nav-group__title"
                        data-nav-toggle
                    >
                        <span>عملیات سازمانی</span>
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'chevron',
                                'size' => 14,
                            ]
                        )
                    </button>

                    <div class="nav-group__items">
                        @if ($nav['finance'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'invoices'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'charge-formulas',
                                        'charge-periods',
                                        'invoices',
                                        'expenses',
                                        'incomes',
                                        'payments',
                                        'bank-accounts',
                                        'wallet-payouts',
                                        'bill-payments',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="finance"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'wallet']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    مالی و کیف پول
                                </span>
                            </a>
                        @endif

                        @if ($nav['services'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'service-requests'
                                    )
                                }}"
                                class="nav-link {{
                                    $currentResource === 'service-requests'
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="services"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'tools']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    خدمات ساختمان
                                </span>
                            </a>
                        @endif

                        @if ($nav['support'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'support-tickets'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'support-tickets',
                                        'support-categories',
                                        'support-sla',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="support"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'support']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    پشتیبانی و SLA
                                </span>
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            @if (
                ($nav['content'] ?? false)
                || ($nav['reports'] ?? false)
                || ($nav['access'] ?? false)
                || ($nav['system'] ?? false)
            )
                <section
                    class="nav-group {{
                        $resourceActive([
                            'announcements',
                            'documents',
                            'meeting-minutes',
                            'notification-preferences',
                            'report-exports',
                            'users',
                            'roles',
                            'role-assignments',
                        ])
                            ? 'is-open'
                            : ''
                    }}"
                    data-nav-group="administration"
                >
                    <button
                        type="button"
                        class="nav-group__title"
                        data-nav-toggle
                    >
                        <span>مدیریت سامانه</span>
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'chevron',
                                'size' => 14,
                            ]
                        )
                    </button>

                    <div class="nav-group__items">
                        @if ($nav['content'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'announcements'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'announcements',
                                        'documents',
                                        'meeting-minutes',
                                        'notification-preferences',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="content"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'bell']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    اطلاع‌رسانی و اسناد
                                </span>
                            </a>
                        @endif

                        @if ($nav['reports'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'report-exports'
                                    )
                                }}"
                                class="nav-link {{
                                    $currentResource === 'report-exports'
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="reports"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'chart']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    گزارش‌ها
                                </span>
                            </a>
                        @endif

                        @if ($nav['access'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.operations.show',
                                        'users'
                                    )
                                }}"
                                class="nav-link {{
                                    $resourceActive([
                                        'users',
                                        'roles',
                                        'role-assignments',
                                    ])
                                        ? 'is-active'
                                        : ''
                                }}"
                                data-nav-key="access"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'key']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    کاربران و دسترسی
                                </span>
                            </a>
                        @endif

                        @if ($nav['system'] ?? false)
                            <a
                                href="{{
                                    route(
                                        'management.dashboard'
                                    )
                                }}#system"
                                class="nav-link"
                                data-nav-key="system"
                            >
                                <span class="nav-link__icon">
                                    @include(
                                        'management.partials.icon',
                                        ['name' => 'health']
                                    )
                                </span>
                                <span class="nav-link__label">
                                    سلامت و کنترل
                                </span>
                            </a>
                        @endif
                    </div>
                </section>
            @endif
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar__version">
                <span class="status-indicator"></span>
                <div>
                    <strong>Buildino v1.0</strong>
                    <span>سامانه آماده بهره‌برداری</span>
                </div>
            </div>
        </div>
    </aside>

    <button
        type="button"
        class="sidebar-backdrop"
        id="sidebarBackdrop"
        aria-label="بستن منو"
    ></button>

    <main class="main-area">
        <header class="topbar">
            <div class="topbar__start">
                <button
                    type="button"
                    class="icon-button mobile-menu"
                    id="sidebarToggle"
                    aria-label="نمایش منو"
                >
                    @include(
                        'management.partials.icon',
                        ['name' => 'menu']
                    )
                </button>

                <div class="page-heading">
                    <span class="page-heading__eyebrow">
                        BUILDINO MANAGEMENT
                    </span>

                    <h1>
                        @yield(
                            'page-title',
                            'پنل مدیریتی'
                        )
                    </h1>

                    <p>
                        @yield(
                            'page-subtitle',
                            'مدیریت و پایش سامانه Buildino'
                        )
                    </p>
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
                    id="themeToggle"
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

        <div class="page-content">
            @yield('content')
        </div>
    </main>
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
    src="{{ asset('js/buildino-management.js') }}"
    defer
></script>

@stack('scripts')
</body>
</html>
