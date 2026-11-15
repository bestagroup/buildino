(() => {
    'use strict';
    const root = document.querySelector('[data-glance-page]');
    if (!root) return;
    const filters = root.querySelector('[data-glance-filters]');
    if (!filters) return;
    const search = root.querySelector('[data-glance-search]'), block = root.querySelector('[data-glance-block]'), status = root.querySelector('[data-glance-status]');
    let floors = [...root.querySelectorAll('[data-glance-floor]')], units = [...root.querySelectorAll('[data-glance-unit]')];
    const normalize = value => String(value || '').trim().toLocaleLowerCase('fa').replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))).replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit))).replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ');
    let searchable = new Map(units.map(unit => [unit, normalize(unit.dataset.search)]));
    const number = value => value.toLocaleString('fa-IR');
    const update = () => {
        const term = normalize(search.value), state = status.value;
        let count = 0;
        units.forEach(unit => {
            const data = unit.dataset;
            const match = (!term || searchable.get(unit).includes(term)) && (!block.value || data.block === block.value) && (
                !state || state === 'shortfall' && Number(data.shortfall) > 0 || state === 'debt' && Number(data.debt) > 0 || state === 'surplus' && Number(data.surplus) > 0 || state === 'vacant' && data.occupied === '0' || state === 'inactive' && data.active === '0'
            );
            unit.hidden = !match;
            if (match) count++;
        });
        floors.forEach(floor => {
            const visible = [...floor.querySelectorAll('[data-glance-unit]')].filter(unit => !unit.hidden).length;
            floor.hidden = visible === 0;
            floor.querySelector('[data-glance-floor-count]').textContent = number(visible);
        });
        root.querySelector('[data-glance-result]').textContent = number(count) + ' واحد از ' + number(units.length) + ' واحد نمایش داده می‌شود';
        root.querySelector('[data-glance-empty]').hidden = count !== 0;
    };
    search.addEventListener('input', update);
    block.addEventListener('change', update);
    status.addEventListener('change', update);
    root.querySelector('[data-glance-reset]').addEventListener('click', () => { search.value = ''; block.value = ''; status.value = ''; update(); search.focus({ preventScroll: true }); });
    filters.hidden = false;
    update();
    const dialog = root.querySelector('[data-glance-dialog]');
    if (!dialog) return;
    const overview = dialog.querySelector('[data-glance-overview]'), workspace = dialog.querySelector('[data-glance-workspace]');
    const frameHost = dialog.querySelector('[data-glance-frames]'), loading = dialog.querySelector('[data-glance-loading]'), error = dialog.querySelector('[data-glance-frame-error]');
    const closeButton = dialog.querySelector('[data-glance-close]'), discard = dialog.querySelector('[data-glance-discard]');
    const frames = new Map();
    let selected = null, trigger = null, active = 'overview', changed = false, refreshing = false, overflow = '', revision = 0, opened = false;
    const busy = () => [...frames.values()].some(item => item.busy);
    const syncBusy = () => { closeButton.disabled = busy(); };
    const mountOverview = () => {
        overview.replaceChildren(selected.querySelector('[data-glance-detail]').content.cloneNode(true));
        overview.querySelector('footer')?.remove();
    };
    const refreshReport = async () => {
        if (refreshing || !changed) return;
        refreshing = true;
        const version = revision;
        try {
            const response = await fetch(location.href, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } });
            if (!response.ok) throw Error();
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const directory = page.querySelector('.glance-directory');
            if (!directory) throw Error();
            const id = selected?.dataset.unitId;
            for (const selector of ['.glance-stats', '.glance-period', '.glance-updated']) {
                const fresh = page.querySelector(selector), old = root.querySelector(selector);
                if (fresh && old) old.replaceWith(fresh);
            }
            root.querySelectorAll('[data-glance-floor]').forEach(floor => floor.remove());
            directory.querySelectorAll('[data-glance-floor]').forEach(floor => root.querySelector('.glance-directory').append(floor));
            floors = [...root.querySelectorAll('[data-glance-floor]')]; units = [...root.querySelectorAll('[data-glance-unit]')];
            searchable = new Map(units.map(unit => [unit, normalize(unit.dataset.search)]));
            selected = units.find(unit => unit.dataset.unitId === id) || null;
            if (selected) { trigger = selected.querySelector('[data-glance-open]'); if (active === 'overview') mountOverview(); }
            update(); changed = revision !== version;
            if (!dialog.open && document.activeElement === document.body) trigger?.focus({ preventScroll: true });
            root.querySelectorAll('[data-glance-refresh-error], [data-glance-modal-refresh-error]').forEach(notice => { notice.hidden = true; });
        } catch { root.querySelectorAll('[data-glance-refresh-error], [data-glance-modal-refresh-error]').forEach(notice => { notice.hidden = false; }); }
        finally { refreshing = false; }
    };
    const close = () => {
        if (busy()) return;
        dialog.close();
        finishClose();
    };
    const requestClose = () => {
        if (busy()) return;
        if ([...frames.values()].some(item => item.dirty)) {
            discard.hidden = false;
            discard.querySelector('[data-glance-keep]').focus();
        } else close();
    };
    const showFrameState = () => {
        const current = frames.get(active);
        loading.hidden = !current || current.ready || current.failed;
        error.hidden = !current?.failed;
        for (const [key, item] of frames) item.frame.hidden = key !== active || !item.ready || item.failed;
    };
    const createFrame = (key, url) => {
        const frame = document.createElement('iframe');
        frame.title = dialog.querySelector(`[data-glance-section="${key}"]`).textContent.trim() + ' · ' + selected.dataset.unitTitle;
        frame.hidden = true;
        const item = { frame, dirty: false, busy: false, ready: false, failed: false, timer: null };
        frames.set(key, item);
        item.timer = setTimeout(() => { if (!item.ready) { item.failed = true; showFrameState(); } }, 20000);
        frame.addEventListener('load', () => {
            clearTimeout(item.timer);
            try { item.ready = Boolean(frame.contentDocument.querySelector('[data-glance-embedded]')); } catch { item.ready = false; }
            item.failed = !item.ready; item.busy = false; syncBusy(); showFrameState();
        });
        url.searchParams.set('glance_embed', '1');
        frame.src = url.href;
        frameHost.append(frame);
        showFrameState();
    };
    const showSection = key => {
        if (!selected || !dialog.querySelector(`[data-glance-section="${key}"]`)) return;
        active = key;
        dialog.querySelectorAll('[data-glance-section]').forEach(button => {
            if (button.dataset.glanceSection === key) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
        });
        overview.hidden = key !== 'overview'; workspace.hidden = key === 'overview';
        if (key === 'overview') { if (changed) void refreshReport(); return; }
        if (!frames.has(key)) {
            const link = [...selected.querySelector('[data-glance-detail]').content.querySelectorAll('footer a')].find(link => new URL(link.href, location.href).pathname.endsWith('/' + key));
            if (!link) return;
            createFrame(key, new URL(link.href, location.href));
        }
        showFrameState();
    };
    root.addEventListener('click', event => {
        if (event.target.closest('[data-glance-refresh]')) { changed = true; void refreshReport(); }
        const open = event.target.closest('[data-glance-open]');
        if (open) {
            selected = open.closest('[data-glance-unit]'); trigger = open;
            dialog.querySelector('#glance-unit-title').textContent = selected.dataset.unitTitle;
            mountOverview(); showSection('overview');
            overflow = document.documentElement.style.overflow;
            document.documentElement.style.overflow = 'hidden';
            opened = true;
            dialog.showModal(); dialog.querySelector('#glance-unit-title').focus();
        }
        const section = event.target.closest('[data-glance-section]');
        if (section) showSection(section.dataset.glanceSection);
        const link = event.target.closest('[data-glance-overview] footer a');
        if (link) { event.preventDefault(); showSection(new URL(link.href).pathname.split('/').pop()); }
    });
    closeButton.addEventListener('click', requestClose);
    dialog.addEventListener('cancel', event => { event.preventDefault(); requestClose(); });
    const finishClose = () => {
        if (!opened) return;
        opened = false;
        for (const item of frames.values()) clearTimeout(item.timer);
        frames.clear(); frameHost.replaceChildren(); discard.hidden = true;
        document.documentElement.style.overflow = overflow;
        trigger?.focus({ preventScroll: true });
        void refreshReport();
    };
    dialog.addEventListener('close', finishClose);
    dialog.querySelector('[data-glance-keep]').addEventListener('click', () => { discard.hidden = true; closeButton.focus(); });
    dialog.querySelector('[data-glance-leave]').addEventListener('click', close);
    dialog.querySelector('[data-glance-retry]').addEventListener('click', () => {
        const current = frames.get(active); if (!current || current.busy) return;
        const url = new URL(current.frame.src); clearTimeout(current.timer); current.frame.remove(); frames.delete(active); createFrame(active, url);
    });
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || !dialog.open) return;
        const item = [...frames.values()].find(item => item.frame.contentWindow === event.source);
        if (!item) return;
        if (event.data?.type === 'buildino:unit-workspace-close') { requestClose(); return; }
        if (event.data?.type !== 'buildino:unit-workspace') return;
        item.dirty = event.data.dirty === true; item.busy = event.data.busy === true;
        if (event.data.changed === true) { changed = true; revision++; }
        syncBusy();
    });
    window.addEventListener('beforeunload', event => {
        if (!busy() && ![...frames.values()].some(item => item.dirty)) return;
        event.preventDefault(); event.returnValue = '';
    });
})();
