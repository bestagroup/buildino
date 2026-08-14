<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'داشبورد مدیریتی Buildino')</title>
    <link rel="stylesheet" href="{{ asset('css/buildino-management.css') }}">
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

            <nav class="sidebar__nav" aria-label="منوی داشبورد">
                <a href="#overview" class="nav-link is-active">
                    @include('management.partials.icon', ['name' => 'home'])
                    <span>نمای کلی</span>
                </a>
                <a href="#modules" class="nav-link">
                    @include('management.partials.icon', ['name' => 'building'])
                    <span>ماژول‌های سامانه</span>
                </a>
                <a href="#financial" class="nav-link">
                    @include('management.partials.icon', ['name' => 'wallet'])
                    <span>مالی و کیف پول</span>
                </a>
                <a href="#operations" class="nav-link">
                    @include('management.partials.icon', ['name' => 'chart'])
                    <span>عملیات جاری</span>
                </a>
                <a href="#recent" class="nav-link">
                    @include('management.partials.icon', ['name' => 'calendar'])
                    <span>آخرین فعالیت‌ها</span>
                </a>
                <a href="#system" class="nav-link">
                    @include('management.partials.icon', ['name' => 'health'])
                    <span>سلامت و API</span>
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

                <form method="POST" action="{{ route('management.logout') }}">
                    @csrf
                    <button class="logout-button" type="submit">
                        @include('management.partials.icon', ['name' => 'logout', 'size' => 18])
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
                        @include('management.partials.icon', ['name' => 'menu'])
                    </button>

                    <div>
                        <h1>@yield('page-title', 'داشبورد مدیریتی')</h1>
                        <p>@yield('page-subtitle', 'نمای یکپارچه مدیریت و پایش سامانه')</p>
                    </div>
                </div>

                <div class="topbar__actions">
                    <span class="api-version">
                        API v{{ $dashboard['api']['version'] ?? '1.0.0' }}
                    </span>

                    <button
                        type="button"
                        class="icon-button"
                        id="themeToggle"
                        aria-label="تغییر پوسته"
                        title="تغییر پوسته"
                    >
                        <span class="theme-icon theme-icon--light">
                            @include('management.partials.icon', ['name' => 'moon'])
                        </span>
                        <span class="theme-icon theme-icon--dark">
                            @include('management.partials.icon', ['name' => 'sun'])
                        </span>
                    </button>
                </div>
            </header>

            <div class="page-content">
                @yield('content')
            </div>
        </main>
    </div>

    <script src="{{ asset('js/buildino-management.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
