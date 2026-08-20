/* Buildino Materio shell bridge. Presentation behavior only. */
(() => {
    'use strict';

    const html = document.documentElement;
    const body = document.body;
    const storage = window.localStorage;

    const safeGet = (key) => {
        try { return storage.getItem(key); } catch (_) { return null; }
    };
    const safeSet = (key, value) => {
        try { storage.setItem(key, value); } catch (_) {}
    };

    const applyTheme = (theme) => {
        const normalized = theme === 'dark' ? 'dark' : 'light';
        html.dataset.theme = normalized;
        html.style.colorScheme = normalized;
        safeSet('buildino-theme', normalized);
        document.dispatchEvent(new CustomEvent('buildino:theme-changed', { detail: { theme: normalized } }));
    };

    applyTheme(safeGet('buildino-theme') || html.dataset.theme || 'light');

    ['themeToggle', 'portalThemeToggle'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();
            applyTheme(html.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    });

    const closeManagementSidebar = () => body.classList.remove('sidebar-open');
    const closePortalSidebar = () => body.classList.remove('portal-sidebar-open');

    document.getElementById('sidebarToggle')?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        body.classList.toggle('sidebar-open');
    });
    document.getElementById('sidebarBackdrop')?.addEventListener('click', closeManagementSidebar);

    document.getElementById('portalSidebarToggle')?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        body.classList.toggle('portal-sidebar-open');
    });
    document.getElementById('portalSidebarBackdrop')?.addEventListener('click', closePortalSidebar);

    const collapse = document.getElementById('sidebarCollapse');
    if (safeGet('buildino-sidebar-collapsed') === '1') {
        body.classList.add('sidebar-collapsed');
    }
    collapse?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        body.classList.toggle('sidebar-collapsed');
        safeSet('buildino-sidebar-collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
    });

    document.querySelectorAll('.nav-group__title').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.closest('.nav-group');
            if (!group) return;
            group.classList.toggle('is-open');
        });
    });

    document.querySelectorAll('[data-popover]').forEach((popover) => {
        const trigger = popover.querySelector('[data-popover-trigger]');
        trigger?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            document.querySelectorAll('[data-popover].is-open').forEach((other) => {
                if (other !== popover) other.classList.remove('is-open');
            });
            popover.classList.toggle('is-open');
        });
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-popover].is-open').forEach((popover) => {
            if (!popover.contains(event.target)) popover.classList.remove('is-open');
        });
    });

    const palette = document.getElementById('commandPalette');
    const paletteBackdrop = document.getElementById('commandBackdrop');
    const commandInput = document.getElementById('commandSearch');
    const commandResults = document.getElementById('commandResults');
    const resources = window.BuildinoManagementUi?.resources || [];

    const renderCommands = (term = '') => {
        if (!commandResults) return;
        const query = term.trim().toLocaleLowerCase('fa');
        const matches = resources.filter((item) => {
            if (!query) return true;
            return [item.title, item.description, item.group, item.key]
                .filter(Boolean)
                .some((value) => String(value).toLocaleLowerCase('fa').includes(query));
        }).slice(0, 12);

        commandResults.replaceChildren();
        matches.forEach((item) => {
            const link = document.createElement('a');
            link.href = item.url;
            link.className = 'nav-link';
            const title = document.createElement('strong');
            title.textContent = item.title || item.key;
            const meta = document.createElement('small');
            meta.textContent = item.group || item.description || '';
            meta.style.marginInlineStart = 'auto';
            meta.style.color = 'var(--buildino-muted)';
            link.append(title, meta);
            commandResults.appendChild(link);
        });
    };

    const openPalette = () => {
        if (!palette || !paletteBackdrop) return;
        palette.classList.add('is-open');
        paletteBackdrop.classList.add('is-open');
        palette.setAttribute('aria-hidden', 'false');
        renderCommands(commandInput?.value || '');
        window.setTimeout(() => commandInput?.focus(), 20);
    };
    const closePalette = () => {
        palette?.classList.remove('is-open');
        paletteBackdrop?.classList.remove('is-open');
        palette?.setAttribute('aria-hidden', 'true');
    };

    document.getElementById('commandTrigger')?.addEventListener('click', openPalette);
    document.getElementById('commandClose')?.addEventListener('click', closePalette);
    paletteBackdrop?.addEventListener('click', closePalette);
    commandInput?.addEventListener('input', () => renderCommands(commandInput.value));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePalette();
            closeManagementSidebar();
            closePortalSidebar();
            document.querySelectorAll('[data-popover].is-open').forEach((item) => item.classList.remove('is-open'));
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openPalette();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1200) {
            closeManagementSidebar();
            closePortalSidebar();
        }
    });
})();
