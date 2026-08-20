/* Buildino v3.6 - canonical Materio shell state controller. */
(() => {
    'use strict';

    const root = document.documentElement;
    const body = document.body;
    const isDesktop = () => window.matchMedia('(min-width: 1200px)').matches;

    const closeMenu = () => root.classList.remove('layout-menu-expanded');
    const toggleMenu = () => root.classList.toggle('layout-menu-expanded');

    // v3.6 intentionally uses one deterministic desktop width. This removes the
    // three competing pre-v3.6 collapse implementations and their stale storage.
    root.classList.remove('layout-menu-collapsed', 'layout-menu-hover');
    body.classList.remove('sidebar-open', 'portal-sidebar-open', 'sidebar-collapsed');

    ['layoutMenuToggle', 'portalLayoutMenuToggle'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', (event) => {
            event.preventDefault();
            if (!isDesktop()) {
                toggleMenu();
            }
        });
    });

    ['layoutOverlay', 'portalLayoutOverlay'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', closeMenu);
    });

    document.querySelectorAll('.buildino-native-menu .menu-link[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                closeMenu();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        closeMenu();
        root.classList.remove('layout-menu-collapsed', 'layout-menu-hover');
        body.classList.remove('sidebar-open', 'portal-sidebar-open', 'sidebar-collapsed');
    });

    window.BuildinoShell = Object.freeze({
        close: closeMenu,
        toggle: toggleMenu,
    });
})();
