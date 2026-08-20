<aside
    id="layout-menu"
    class="sidebar layout-menu menu-vertical menu bg-menu-theme buildino-native-menu"
    aria-label="منوی اصلی مدیریت"
>
    <div class="app-brand buildino-menu-brand">
        <a
            href="{{ route('management.dashboard') }}"
            class="app-brand-link"
            aria-label="Buildino"
        >
            <span class="app-brand-logo buildino-menu-brand__mark">B</span>
            <span class="app-brand-text menu-text fw-bold">Buildino</span>
        </a>

        <button
            type="button"
            class="materialize-menu-collapse"
            data-materialize-menu-collapse
            aria-label="جمع یا باز کردن منو"
            title="جمع یا باز کردن منو"
        >
            @include('management.partials.icon', ['name' => 'chevron', 'size' => 18])
        </button>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">عمومی</span>
        </li>

        <li class="menu-item {{ request()->routeIs('management.dashboard') ? 'active' : '' }}">
            <a
                href="{{ route('management.dashboard') }}"
                class="menu-link"
                data-nav-key="dashboard"
            >
                <span class="menu-icon">@include('management.partials.icon', ['name' => 'home', 'size' => 20])</span>
                <div>داشبورد</div>
            </a>
        </li>

        @if ($nav['operations'] ?? false)
            <li class="menu-item {{ request()->routeIs('management.operations.index') ? 'active' : '' }}">
                <a
                    href="{{ route('management.operations.index') }}"
                    class="menu-link"
                    data-nav-key="operations"
                >
                    <span class="menu-icon">@include('management.partials.icon', ['name' => 'grid', 'size' => 20])</span>
                    <div>مرکز عملیات</div>
                </a>
            </li>
        @endif

        @if (
            ($nav['structure'] ?? false)
            || ($nav['residents'] ?? false)
            || ($nav['guests'] ?? false)
            || ($nav['facilities'] ?? false)
        )
            <li class="menu-header small text-uppercase">
                <span class="menu-header-text">ساختمان و ساکنین</span>
            </li>

            @if ($nav['structure'] ?? false)
                <li class="menu-item {{ $resourceActive(['complexes', 'buildings', 'blocks', 'floors', 'units']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', $structureTarget ?? 'buildings') }}"
                        class="menu-link"
                        data-nav-key="structure"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'building', 'size' => 20])</span>
                        <div>ساختار مجتمع</div>
                    </a>
                </li>
            @endif

            @if ($nav['residents'] ?? false)
                <li class="menu-item {{ $resourceActive(['ownerships', 'occupancies', 'invitations']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'occupancies') }}"
                        class="menu-link"
                        data-nav-key="residents"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'users', 'size' => 20])</span>
                        <div>مالکین و ساکنین</div>
                    </a>
                </li>
            @endif

            @if ($nav['guests'] ?? false)
                <li class="menu-item {{ $currentResource === 'guest-visits' ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'guest-visits') }}"
                        class="menu-link"
                        data-nav-key="guests"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'user-plus', 'size' => 20])</span>
                        <div>مهمان و تردد</div>
                    </a>
                </li>
            @endif

            @if ($nav['facilities'] ?? false)
                <li class="menu-item {{ $resourceActive(['facilities', 'facility-schedules', 'facility-time-slots', 'facility-rules', 'facility-blackouts', 'reservations']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'facilities') }}"
                        class="menu-link"
                        data-nav-key="facilities"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'calendar', 'size' => 20])</span>
                        <div>امکانات و رزرو</div>
                    </a>
                </li>
            @endif
        @endif

        @if (($nav['finance'] ?? false) || ($nav['services'] ?? false) || ($nav['support'] ?? false))
            <li class="menu-header small text-uppercase">
                <span class="menu-header-text">عملیات سازمانی</span>
            </li>

            @if ($nav['finance'] ?? false)
                <li class="menu-item {{ $resourceActive(['charge-formulas', 'charge-periods', 'invoices', 'expenses', 'incomes', 'payments', 'bank-accounts', 'wallet-payouts', 'bill-payments']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'invoices') }}"
                        class="menu-link"
                        data-nav-key="finance"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'wallet', 'size' => 20])</span>
                        <div>مالی و کیف پول</div>
                    </a>
                </li>
            @endif

            @if ($nav['services'] ?? false)
                <li class="menu-item {{ $currentResource === 'service-requests' ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'service-requests') }}"
                        class="menu-link"
                        data-nav-key="services"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'tools', 'size' => 20])</span>
                        <div>خدمات ساختمان</div>
                    </a>
                </li>
            @endif

            @if ($nav['support'] ?? false)
                <li class="menu-item {{ $resourceActive(['support-tickets', 'support-categories', 'support-sla']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'support-tickets') }}"
                        class="menu-link"
                        data-nav-key="support"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'support', 'size' => 20])</span>
                        <div>پشتیبانی و SLA</div>
                    </a>
                </li>
            @endif
        @endif

        @if (($nav['content'] ?? false) || ($nav['reports'] ?? false) || ($nav['access'] ?? false) || ($nav['system'] ?? false))
            <li class="menu-header small text-uppercase">
                <span class="menu-header-text">مدیریت سامانه</span>
            </li>

            @if ($nav['content'] ?? false)
                <li class="menu-item {{ $resourceActive(['announcements', 'documents', 'meeting-minutes', 'notification-preferences']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'announcements') }}"
                        class="menu-link"
                        data-nav-key="content"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'bell', 'size' => 20])</span>
                        <div>اطلاع‌رسانی و اسناد</div>
                    </a>
                </li>
            @endif

            @if ($nav['reports'] ?? false)
                <li class="menu-item {{ $currentResource === 'report-exports' ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'report-exports') }}"
                        class="menu-link"
                        data-nav-key="reports"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'chart', 'size' => 20])</span>
                        <div>گزارش‌ها</div>
                    </a>
                </li>
            @endif

            @if ($nav['access'] ?? false)
                <li class="menu-item {{ $resourceActive(['users', 'roles', 'role-assignments']) ? 'active' : '' }}">
                    <a
                        href="{{ route('management.operations.show', 'users') }}"
                        class="menu-link"
                        data-nav-key="access"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'key', 'size' => 20])</span>
                        <div>کاربران و دسترسی</div>
                    </a>
                </li>
            @endif

            @if ($nav['system'] ?? false)
                <li class="menu-item">
                    <a
                        href="{{ route('management.dashboard') }}#system"
                        class="menu-link"
                        data-nav-key="system"
                    >
                        <span class="menu-icon">@include('management.partials.icon', ['name' => 'health', 'size' => 20])</span>
                        <div>سلامت و کنترل</div>
                    </a>
                </li>
            @endif
        @endif
    </ul>

    <div class="buildino-menu-footer">
        <span class="status-indicator"></span>
        <div class="menu-text">
            <strong>Buildino v1.0</strong>
            <small>سامانه آماده بهره‌برداری</small>
        </div>
    </div>
</aside>
