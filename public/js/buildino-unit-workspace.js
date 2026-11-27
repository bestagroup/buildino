(() => {
    'use strict';
    if (window.parent === window || !document.querySelector('[data-glance-embedded]')) return;
    let touched = false, pending = 0;
    const editorVisible = () => [...document.querySelectorAll('[data-ownership-editor], [data-occupancy-editor], .finance-editor, dialog[open]')].some(node => !node.closest('[hidden]') && getComputedStyle(node).display !== 'none');
    const notify = changed => window.parent.postMessage({ type: 'buildino:unit-workspace', dirty: touched && editorVisible(), busy: pending > 0, changed }, location.origin);
    document.addEventListener('input', event => {
        if (event.target.closest('[data-deposit-form]')) return;
        if (event.target.closest('form') && editorVisible()) { touched = true; notify(false); }
    }, true);
    document.addEventListener('change', event => {
        if (event.target.closest('[data-deposit-form]')) return;
        if (event.target.closest('form') && editorVisible()) { touched = true; notify(false); }
    }, true);
    // Reuse the existing process APIs. Notify the parent only about writes;
    // this also refreshes the report after an uncertain server response.
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = async (input, options) => {
        const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
        const method = String(options?.method || input?.method || 'GET').toUpperCase();
        const write = url.origin === location.origin && url.pathname.startsWith('/api/') && !['GET', 'HEAD', 'OPTIONS'].includes(method);
        if (!write) return fetchOriginal(input, options);
        pending++; notify(false);
        try { return await fetchOriginal(input, options); }
        finally { pending--; notify(true); }
    };
    new MutationObserver(() => {
        if (!editorVisible() && touched) { touched = false; notify(false); }
    }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'open', 'class'] });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link) return;
        const url = new URL(link.href, location.href);
        if (url.origin === location.origin && (url.pathname.startsWith('/api/') || link.hash && url.pathname === location.pathname)) return;
        // Process navigation belongs to the enclosing unit modal.
        event.preventDefault();
    }, true);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || document.querySelector('dialog[open], .select2-container--open, .swal2-container')) return;
        window.parent.postMessage({ type: 'buildino:unit-workspace-close' }, location.origin);
    });
    notify(false);
})();
