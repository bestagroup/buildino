/* Buildino v3.7 - Materialize shell/theme adapter. Presentation only. */
(() => {
    'use strict';

    const root = document.documentElement;
    const body = document.body;
    const storageKeyTheme = 'buildino-materialize-theme';
    const storageKeyCollapsed = 'buildino-materialize-menu-collapsed';
    const isDesktop = () => window.matchMedia('(min-width: 1200px)').matches;

    const safeGet = (key) => {
        try { return window.localStorage.getItem(key); } catch (_) { return null; }
    };
    const safeSet = (key, value) => {
        try { window.localStorage.setItem(key, value); } catch (_) {}
    };

    const themeLinks = {
        core: document.getElementById('template-core-css'),
        theme: document.getElementById('template-theme-css'),
    };

    const assetBase = root.dataset.assetsPath || '/assets/';
    const normalizeAssetBase = assetBase.endsWith('/') ? assetBase : `${assetBase}/`;

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

        if (persist) safeSet(storageKeyTheme, theme);
        document.dispatchEvent(new CustomEvent('buildino:theme-changed', { detail: { theme } }));
    };

    const initialTheme = safeGet(storageKeyTheme)
        || safeGet('buildino-theme')
        || safeGet('buildino-portal-theme')
        || 'light';
    setTheme(initialTheme, false);

    document.querySelectorAll('[data-materialize-theme-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setTheme(root.classList.contains('dark-style') ? 'light' : 'dark');
        });
    });

    const closeMobileMenu = () => root.classList.remove('layout-menu-expanded');
    const openMobileMenu = () => root.classList.add('layout-menu-expanded');
    const toggleMobileMenu = () => root.classList.toggle('layout-menu-expanded');

    document.querySelectorAll('[data-materialize-menu-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (isDesktop()) return;
            toggleMobileMenu();
        });
    });

    document.querySelectorAll('[data-materialize-menu-collapse]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (!isDesktop()) return;
            root.classList.toggle('layout-menu-collapsed');
            safeSet(storageKeyCollapsed, root.classList.contains('layout-menu-collapsed') ? '1' : '0');
        });
    });

    document.querySelectorAll('[data-materialize-overlay]').forEach((overlay) => {
        overlay.addEventListener('click', closeMobileMenu);
    });

    document.querySelectorAll('#layout-menu .menu-link[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) closeMobileMenu();
        });
    });

    if (isDesktop() && safeGet(storageKeyCollapsed) === '1') {
        root.classList.add('layout-menu-collapsed');
    } else {
        root.classList.remove('layout-menu-collapsed');
    }

    // Remove stale states from previous Buildino theme iterations.
    body.classList.remove('sidebar-open', 'portal-sidebar-open', 'sidebar-collapsed');
    root.classList.remove('layout-menu-hover');

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMobileMenu();
    });

    window.addEventListener('resize', () => {
        closeMobileMenu();
        if (!isDesktop()) {
            root.classList.remove('layout-menu-collapsed');
        } else if (safeGet(storageKeyCollapsed) === '1') {
            root.classList.add('layout-menu-collapsed');
        }
    });

    window.BuildinoMaterialize = Object.freeze({
        setTheme,
        openMenu: openMobileMenu,
        closeMenu: closeMobileMenu,
        toggleMenu: toggleMobileMenu,
    });
})();
