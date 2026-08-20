<aside
    id="layout-menu"
    class="portal-sidebar layout-menu menu-vertical menu bg-menu-theme buildino-native-menu"
    aria-label="منوی پرتال"
>
    <div class="app-brand buildino-menu-brand">
        <a
            href="{{ route('portal.dashboard') }}"
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
            <span class="menu-header-text">پرتال کاربری</span>
        </li>

        @if ($portalAreas['resident'] ?? false)
            <li class="menu-item {{ $activeArea === 'resident' ? 'active' : '' }}">
                <a href="{{ route('portal.resident.dashboard') }}" class="menu-link">
                    <span class="menu-icon">@include('management.partials.icon', ['name' => 'home', 'size' => 20])</span>
                    <div>خانه من</div>
                </a>
            </li>
        @endif

        @if ($portalAreas['provider'] ?? false)
            <li class="menu-item {{ $activeArea === 'provider' ? 'active' : '' }}">
                <a href="{{ route('portal.provider.dashboard') }}" class="menu-link">
                    <span class="menu-icon">@include('management.partials.icon', ['name' => 'tools', 'size' => 20])</span>
                    <div>پنل ارائه‌دهنده</div>
                </a>
            </li>
        @endif

        <li class="menu-header small text-uppercase buildino-context-menu-header">
            <span class="menu-header-text">دسترسی سریع</span>
        </li>

        @yield('sidebar-links')
    </ul>

    <div class="buildino-menu-footer buildino-menu-footer--portal">
        <div class="buildino-menu-user menu-text">
            <div class="portal-avatar">
                {{ mb_substr($portalUser->first_name ?: $portalUser->last_name ?: 'U', 0, 1) }}
            </div>
            <div>
                <strong>{{ trim(($portalUser->first_name ?? '') . ' ' . ($portalUser->last_name ?? '')) ?: $portalUser->mobile }}</strong>
                <small>{{ $activeArea === 'provider' ? 'ارائه‌دهنده خدمات' : 'مالک / ساکن' }}</small>
            </div>
        </div>

        <form method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <button type="submit" class="portal-logout">
                @include('management.partials.icon', ['name' => 'logout', 'size' => 17])
                <span class="menu-text">خروج از حساب</span>
            </button>
        </form>
    </div>
</aside>
