/* Complements the shared sidebar click handlers; resident page only. */
(() => {
    const body = document.body;
    if (!body.classList.contains('portal-resident') && !body.classList.contains('resident-glass')) return;
    const menu = document.getElementById('portalSidebar');
    const toggle = document.getElementById('portalSidebarToggle');
    const mobile = window.matchMedia('(max-width: 980px)');
    if (!menu || !toggle) return;
    const open = () => mobile.matches && body.classList.contains('portal-sidebar-open');
    const sync = () => {
        toggle.setAttribute('aria-expanded', String(open()));
        menu.inert = mobile.matches && !open();
        const main = document.querySelector('.portal-main');
        if (main) main.inert = open();
    };
    const close = () => { body.classList.remove('portal-sidebar-open'); sync(); toggle.focus({ preventScroll: true }); };
    document.querySelector('[data-resident-sidebar-close]')?.addEventListener('click', close);
    document.getElementById('portalSidebarBackdrop')?.addEventListener('click', close);
    toggle.addEventListener('click', () => { sync(); if (open()) menu.querySelector('[data-resident-sidebar-close], a[href]')?.focus(); });
    new MutationObserver(sync).observe(body, { attributes: true, attributeFilter: ['class'] });
    mobile.addEventListener('change', () => { body.classList.remove('portal-sidebar-open'); sync(); });
    menu.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a[href], [data-invitation-open]')) close();
    });
    document.addEventListener('keydown', (event) => {
        if (!open()) return;
        if (event.key === 'Escape') {
            event.preventDefault(); close();
        }
        if (event.key !== 'Tab') return;
        const items = [...menu.querySelectorAll('a[href],button:not(:disabled)')]
            .filter(el => el.getClientRects().length);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (!menu.contains(document.activeElement)) {
            event.preventDefault(); (event.shiftKey ? last : first).focus();
        } else if (event.shiftKey && document.activeElement === first) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first.focus();
        }
    });
    sync();
})();
