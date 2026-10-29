(() => {
    'use strict';
    document.querySelectorAll('[data-finance-navigation] a').forEach(link => {
        link.addEventListener('click', () => {
            const selected = document.querySelector('[data-period-building], [data-context-name="building_id"]');
            const url = new URL(link.href, window.location.origin);
            if (selected?.value) url.searchParams.set('building_id', selected.value);
            else url.searchParams.delete('building_id');
            link.href = url.toString();
        });
    });
    document.querySelector('[data-period-invoice-link]')?.addEventListener('click', event => {
        const selected = document.querySelector('[data-period-building]');
        const url = new URL(event.currentTarget.href, window.location.origin);
        if (selected?.value) url.searchParams.set('building_id', selected.value);
        event.currentTarget.href = url.toString();
    });
})();
