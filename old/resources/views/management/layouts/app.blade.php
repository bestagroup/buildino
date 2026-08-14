<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'پنل مدیریتی Buildino')</title>
    <link rel="stylesheet" href="{{ asset('css/buildino-management.css') }}">
    @stack('styles')
</head>
<body>
    <div class="management-shell">
        <aside class="sidebar" id="managementSidebar">
            <div class="sidebar__brand">
                <div class="brand-mark">B</div>
                <div>
                    <strong>Buildino</strong>
                    <span>Management Center</span>
                </div>
            </div>

            <nav class="sidebar__nav" aria-label="منوی مدیریت">
                <a
                    href="{{ route('management.dashboard') }}"
                    class="nav-link {{ request()->routeIs('management.dashboard') ? 'is-active' : '' }}"
                >
                    @include('management.partials.icon', ['name' => 'home'])
                    <span>داشبورد</span>
                </a>

                <a
                    href="{{ route('management.operations.index') }}"
                    class="nav-link {{ request()->routeIs('management.operations.*') ? 'is-active' : '' }}"
                >
                    @include('management.partials.icon', ['name' => 'tools'])
                    <span>عملیات و فرم‌ها</span>
                </a>

                <a
                    href="{{ route('management.operations.show', 'complexes') }}"
                    class="nav-link"
                >
                    @include('management.partials.icon', ['name' => 'building'])
                    <span>ساختار ساختمان</span>
                </a>

                <a
                    href="{{ route('management.operations.show', 'users') }}"
                    class="nav-link"
                >
                    @include('management.partials.icon', ['name' => 'users'])
                    <span>کاربران و دسترسی</span>
                </a>

                <a
                    href="{{ route('management.operations.show', 'invoices') }}"
                    class="nav-link"
                >
                    @include('management.partials.icon', ['name' => 'wallet'])
                    <span>مالی و صورتحساب</span>
                </a>

                <a
                    href="{{ route('management.operations.show', 'reservations') }}"
                    class="nav-link"
                >
                    @include('management.partials.icon', ['name' => 'calendar'])
                    <span>امکانات و رزرو</span>
                </a>

                <a
                    href="{{ route('management.operations.show', 'service-requests') }}"
                    class="nav-link"
                >
                    @include('management.partials.icon', ['name' => 'tools'])
                    <span>خدمات</span>
                </a>

                <a
                    href="{{ route('management.operations.show', 'support-tickets') }}"
                    class="nav-link"
                >
                    @include('management.partials.icon', ['name' => 'support'])
                    <span>پشتیبانی</span>
                </a>
            </nav>

            <div class="sidebar__footer">
                <div class="current-user">
                    <div class="avatar">
                        {{ mb_substr($user->first_name ?: 'U', 0, 1) }}
                    </div>

                    <div class="current-user__meta">
                        <strong>
                            {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->mobile }}
                        </strong>
                        <span>{{ $user->mobile ?: $user->email }}</span>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('management.logout') }}"
                >
                    @csrf
                    <button
                        class="logout-button"
                        type="submit"
                    >
                        @include(
                            'management.partials.icon',
                            [
                                'name' => 'logout',
                                'size' => 18,
                            ]
                        )
                        خروج از پنل
                    </button>
                </form>
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
                <div class="topbar__right">
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

                    <div>
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

                <div class="topbar__actions">
                    <span class="api-version">
                        API v{{
                            data_get(
                                $dashboard ?? [],
                                'api.version',
                                config(
                                    'api_contract_v1.version',
                                    '1.0.0'
                                )
                            )
                        }}
                    </span>

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
                </div>
            </header>

            <div class="page-content">
                @yield('content')
            </div>
        </main>
    </div>

    <script
        src="{{ asset('js/buildino-management.js') }}"
        defer
    ></script>

    @stack('scripts')
</body>
</html>
