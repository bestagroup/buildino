/* Complements the shared sidebar click handlers; resident page only. */
(() => {
    const body = document.body;
    if (!body.classList.contains('resident-glass')) return;
    const menu = document.getElementById('portalSidebar');
    const toggle = document.getElementById('portalSidebarToggle');
    const mobile = window.matchMedia('(max-width: 980px)');
    if (!menu || !toggle) return;
    const open = () => mobile.matches && body.classList.contains('portal-sidebar-open');
    const sync = () => {
        toggle.setAttribute('aria-expanded', String(open()));
        menu.inert = mobile.matches && !open();
    };
    new MutationObserver(sync).observe(body, { attributes: true, attributeFilter: ['class'] });
    mobile.addEventListener('change', sync);
    menu.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a[href]')) body.classList.remove('portal-sidebar-open');
    });
    document.addEventListener('keydown', (event) => {
        if (!open()) return;
        if (event.key === 'Escape') {
            event.preventDefault(); body.classList.remove('portal-sidebar-open'); toggle.focus();
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
