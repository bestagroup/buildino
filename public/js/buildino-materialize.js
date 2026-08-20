/* Buildino v3.10 - Materialize shell/theme adapter. Presentation only. */
(() => {
    'use strict';

    const root = document.documentElement;
    const body = document.body;
    const storageKeyTheme = 'buildino-materialize-theme';
    const storageKeyCollapsed = 'buildino-materialize-menu-collapsed';
    const desktopQuery = window.matchMedia('(min-width: 1200px)');
    const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const isDesktop = () => desktopQuery.matches;

    const safeGet = (key) => {
        try {
            return window.localStorage.getItem(key);
        } catch (_) {
            return null;
        }
    };

    const safeSet = (key, value) => {
        try {
            window.localStorage.setItem(key, value);
        } catch (_) {
            // Storage can be unavailable in private/restricted contexts.
        }
    };

    const addMediaListener = (query, listener) => {
        if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', listener);
            return;
        }

        // Safari compatibility for older WebKit engines.
        if (typeof query.addListener === 'function') {
            query.addListener(listener);
        }
    };

    const themeLinks = {
        core: document.getElementById('template-core-css'),
        theme: document.getElementById('template-theme-css'),
    };

    const assetBase = root.dataset.assetsPath || '/assets/';
    const normalizeAssetBase = assetBase.endsWith('/')
        ? assetBase
        : `${assetBase}/`;

    const themeToggles = () => Array.from(
        document.querySelectorAll('[data-materialize-theme-toggle]')
    );

    const syncThemeControls = (theme) => {
        const dark = theme === 'dark';

        themeToggles().forEach((button) => {
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute(
                'title',
                dark ? 'فعال‌کردن حالت روشن' : 'فعال‌کردن حالت تیره'
            );
        });
    };

    const setTheme = (requestedTheme, persist = true) => {
        const theme = requestedTheme === 'dark' ? 'dark' : 'light';
        const dark = theme === 'dark';

        root.classList.toggle('dark-style', dark);
        root.classList.toggle('light-style', !dark);
        root.dataset.theme = dark ? 'theme-default-dark' : 'theme-default';
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        root.style.colorScheme = theme;

        if (themeLinks.core) {
            themeLinks.core.href = `${normalizeAssetBase}vendor/css/rtl/${dark ? 'core-dark.css' : 'core.css'}`;
        }

        if (themeLinks.theme) {
            themeLinks.theme.href = `${normalizeAssetBase}vendor/css/rtl/${dark ? 'theme-default-dark.css' : 'theme-default.css'}`;
        }

        syncThemeControls(theme);

        if (persist) {
            safeSet(storageKeyTheme, theme);
        }

        document.dispatchEvent(new CustomEvent(
            'buildino:theme-changed',
            { detail: { theme } }
        ));
    };

    const explicitTheme = safeGet(storageKeyTheme)
        || safeGet('buildino-theme')
        || safeGet('buildino-portal-theme');
    const initialTheme = explicitTheme || (darkQuery.matches ? 'dark' : 'light');
    setTheme(initialTheme, false);

    themeToggles().forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setTheme(root.classList.contains('dark-style') ? 'light' : 'dark');
        });
    });

    addMediaListener(darkQuery, (event) => {
        if (!safeGet(storageKeyTheme)) {
            setTheme(event.matches ? 'dark' : 'light', false);
        }
    });

    const menu = document.getElementById('layout-menu');
    const overlay = document.querySelector('[data-materialize-overlay]');
    const menuToggles = Array.from(
        document.querySelectorAll('[data-materialize-menu-toggle]')
    );
    let lastMenuTrigger = null;

    const syncMenuA11y = () => {
        const expanded = !isDesktop()
            && root.classList.contains('layout-menu-expanded');

        menuToggles.forEach((button) => {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });

        if (menu) {
            menu.setAttribute('aria-hidden', !isDesktop() && !expanded ? 'true' : 'false');
            menu.setAttribute('tabindex', '-1');
        }

        if (overlay) {
            overlay.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        }

        body.classList.toggle('buildino-scroll-locked', expanded);
    };

    const closeMobileMenu = ({ restoreFocus = false } = {}) => {
        root.classList.remove('layout-menu-expanded');
        syncMenuA11y();

        if (restoreFocus && lastMenuTrigger instanceof HTMLElement) {
            lastMenuTrigger.focus({ preventScroll: true });
        }
    };

    const openMobileMenu = (trigger = null) => {
        if (isDesktop()) {
            return;
        }

        if (trigger instanceof HTMLElement) {
            lastMenuTrigger = trigger;
        }

        root.classList.add('layout-menu-expanded');
        syncMenuA11y();

        window.requestAnimationFrame(() => {
            const target = menu?.querySelector('.menu-link[href]') || menu;
            if (target instanceof HTMLElement) {
                target.focus({ preventScroll: true });
            }
        });
    };

    const toggleMobileMenu = (trigger = null) => {
        if (isDesktop()) {
            return;
        }

        if (trigger instanceof HTMLElement) {
            lastMenuTrigger = trigger;
        }

        const expanded = root.classList.toggle('layout-menu-expanded');
        syncMenuA11y();

        if (!expanded) {
            if (lastMenuTrigger instanceof HTMLElement) {
                lastMenuTrigger.focus({ preventScroll: true });
            }
            return;
        }

        window.requestAnimationFrame(() => {
            const target = menu?.querySelector('.menu-link[href]') || menu;
            if (target instanceof HTMLElement) {
                target.focus({ preventScroll: true });
            }
        });
    };

    menuToggles.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (isDesktop()) {
                return;
            }
            toggleMobileMenu(button);
        });
    });

    document.querySelectorAll('[data-materialize-menu-collapse]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (!isDesktop()) {
                return;
            }

            root.classList.toggle('layout-menu-collapsed');
            const collapsed = root.classList.contains('layout-menu-collapsed');
            safeSet(storageKeyCollapsed, collapsed ? '1' : '0');
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    });

    if (overlay) {
        overlay.addEventListener('click', () => closeMobileMenu({ restoreFocus: true }));
    }

    document.querySelectorAll('#layout-menu .menu-link[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                closeMobileMenu();
            }
        });
    });

    const syncDesktopState = () => {
        closeMobileMenu();

        if (isDesktop() && safeGet(storageKeyCollapsed) === '1') {
            root.classList.add('layout-menu-collapsed');
        } else {
            root.classList.remove('layout-menu-collapsed');
        }

        document.querySelectorAll('[data-materialize-menu-collapse]').forEach((button) => {
            button.setAttribute(
                'aria-expanded',
                root.classList.contains('layout-menu-collapsed') ? 'false' : 'true'
            );
        });

        syncMenuA11y();
    };

    // Remove stale states from previous Buildino theme iterations.
    body.classList.remove('sidebar-open', 'portal-sidebar-open', 'sidebar-collapsed');
    root.classList.remove('layout-menu-hover');
    syncDesktopState();

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('layout-menu-expanded')) {
            event.preventDefault();
            closeMobileMenu({ restoreFocus: true });
        }
    });

    let resizeFrame = null;
    window.addEventListener('resize', () => {
        if (resizeFrame !== null) {
            window.cancelAnimationFrame(resizeFrame);
        }

        resizeFrame = window.requestAnimationFrame(() => {
            resizeFrame = null;
            syncDesktopState();
        });
    }, { passive: true });

    window.addEventListener('pagehide', () => {
        body.classList.remove('buildino-scroll-locked');
    });

    window.BuildinoMaterialize = Object.freeze({
        setTheme,
        openMenu: openMobileMenu,
        closeMenu: closeMobileMenu,
        toggleMenu: toggleMobileMenu,
    });
})();
