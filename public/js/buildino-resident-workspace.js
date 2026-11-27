(() => {
    'use strict';
    if (!document.body.classList.contains('portal-resident')) return;
    const root = document.querySelector('[data-resident-workspace]');
    const resource = root?.dataset.residentResource || '';
    const digits = value => String(value ?? '').replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
    const money = value => Number(value || 0).toLocaleString('fa-IR');
    const faText = value => digits(value).replace(/\bIRR\b/g, 'ریال').replace(/\bIRT\b/g, 'تومان');
    const normalizeText = node => {
        if (node?.nodeType === Node.TEXT_NODE) {
            if (!node.parentElement || node.parentElement.closest('script,style,textarea,input,code,svg')) return;
            const formatted = faText(node.nodeValue);
            if (formatted !== node.nodeValue) node.nodeValue = formatted;
            return;
        }
        if (!node || node.nodeType !== Node.ELEMENT_NODE || node.closest('script,style,textarea,input,code,svg')) return;
        const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const text = walker.currentNode;
            if (text.parentElement?.closest('script,style,textarea,input,code,svg')) continue;
            const formatted = faText(text.nodeValue);
            if (formatted !== text.nodeValue) text.nodeValue = formatted;
        }
    };
    normalizeText(document.body);
    new MutationObserver(records => {
        for (const record of records) {
            if (record.type === 'characterData') normalizeText(record.target);
            else record.addedNodes.forEach(normalizeText);
        }
    }).observe(document.body, { childList: true, characterData: true, subtree: true });

    const clearErrors = form => {
        form.querySelectorAll('.resident-form-error').forEach(node => node.remove());
        form.querySelectorAll('[data-resident-invalid]').forEach(field => {
            field.removeAttribute('aria-invalid'); field.removeAttribute('data-resident-invalid');
            const descriptions = (field.getAttribute('aria-describedby') || '').split(' ').filter(id => !id.startsWith('resident-error-'));
            if (descriptions.length) field.setAttribute('aria-describedby', descriptions.join(' ')); else field.removeAttribute('aria-describedby');
            window.BuildinoSelect2?.refresh(field);
        });
    };
    document.addEventListener('buildino:portal-form-error', event => {
        const { form, error } = event.detail;
        if (!form) return;
        clearErrors(form);
        const summary = document.createElement('div');
        summary.className = 'resident-form-error'; summary.tabIndex = -1; summary.setAttribute('role', 'alert');
        summary.textContent = error.message;
        (form.querySelector('.modal-body') || form).prepend(summary);
        let first = null;
        for (const [name, messages] of Object.entries(error.payload?.errors || {})) {
            const field = form.elements.namedItem(name) || form.elements.namedItem(name.replace(/^guest\./, ''));
            if (!(field instanceof HTMLElement)) continue;
            const message = document.createElement('small'); message.className = 'resident-form-error resident-field-error';
            message.id = 'resident-error-' + name.replace(/[^\w-]/g, '-'); message.textContent = [].concat(messages).join('؛ ');
            field.setAttribute('aria-invalid', 'true'); field.setAttribute('data-resident-invalid', ''); field.setAttribute('aria-describedby', ((field.getAttribute('aria-describedby') || '') + ' ' + message.id).trim());
            (field.closest('.portal-field') || field.parentElement).append(message); window.BuildinoSelect2?.refresh(field); first ||= field;
        }
        if (error.uncertain) {
            form.dataset.residentUncertain = '1';
            const link = document.createElement('a'); link.className = 'portal-action-button'; link.href = location.pathname + location.search;
            link.textContent = 'بررسی اطلاعات ثبت‌شده'; summary.append(document.createElement('br'), link);
        }
        const target = first?.matches('.select2-hidden-accessible') ? first.nextElementSibling?.querySelector('.select2-selection') : first;
        if (target && target.getClientRects().length) target.focus(); else summary.focus();
    });

    document.querySelectorAll('form[data-portal-form], form[data-ticket-reply-form]').forEach(form => {
        form.querySelectorAll('[required]').forEach(field => {
            const label = field.closest('.portal-field')?.querySelector(':scope > span');
            if (label && !label.querySelector('b')) { const star = document.createElement('b'); star.textContent = ' *'; star.setAttribute('aria-hidden', 'true'); label.append(star); }
        });
        form.querySelectorAll('[name="mobile"]').forEach(field => { field.type = 'tel'; field.inputMode = 'tel'; field.autocomplete = 'tel'; });
        const unit = form.elements.unit_id;
        const queryUnit = new URLSearchParams(location.search).get('unit_id');
        if (form.dataset.portalForm === 'reservation' && queryUnit) {
            const unitOption = [...unit.options].find(option => option.value === queryUnit);
            const facility = form.elements.facility_id;
            const option = [...facility.options].find(option => option.dataset.buildingId === unitOption?.dataset.buildingId);
            if (option) { facility.value = option.value; facility.dispatchEvent(new Event('change', { bubbles: true })); }
        }
        if (unit instanceof HTMLSelectElement && queryUnit && [...unit.options].some(option => option.value === queryUnit && !option.disabled)) unit.value = queryUnit;
        const modal = form.closest('.modal');
        let initial = '', allowClose = false;
        const values = () => JSON.stringify([...new FormData(form)].filter(([key]) => !key.startsWith('_')));
        modal?.addEventListener('shown.bs.modal', () => { initial = values(); });
        modal?.addEventListener('hide.bs.modal', event => {
            if (form.dataset.portalBusy === '1' && form.dataset.residentSaved !== '1') { event.preventDefault(); return; }
            if (allowClose || form.dataset.residentSaved === '1' || !initial || initial === values()) { allowClose = false; return; }
            event.preventDefault();
            const exit = () => { allowClose = true; window.bootstrap.Modal.getOrCreateInstance(modal).hide(); };
            if (window.Swal) void window.Swal.fire({ title: 'خروج از فرم؟', text: 'اطلاعات واردشده هنوز ثبت نشده است.', icon: 'question', showCancelButton: true, confirmButtonText: 'خروج از فرم', cancelButtonText: 'ادامه ویرایش' }).then(result => { if (result.isConfirmed) exit(); });
            else if (window.confirm('اطلاعات ثبت نشده است. از فرم خارج می‌شوید؟')) exit();
        });
        form.addEventListener('input', event => {
            const field = event.target;
            field.closest('.portal-field')?.querySelector('.resident-field-error')?.remove();
            field.removeAttribute('aria-invalid');
        });
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('form[data-portal-form], [data-ticket-reply-form]')) return;
        if (form.dataset.portalBusy === '1' || form.dataset.residentUncertain === '1') { event.preventDefault(); event.stopImmediatePropagation(); return; }
        clearErrors(form);
    }, true);

    const reservation = document.querySelector('[data-portal-form="reservation"]');
    if (reservation) {
        const facility = reservation.elements.facility_id, date = reservation.elements.reservation_date, description = reservation.elements.description;
        const start = reservation.elements.start_time, end = reservation.elements.end_time, submit = reservation.querySelector('[type="submit"]');
        const body = reservation.querySelector('.modal-body'), footer = reservation.querySelector('.modal-footer'), modal = reservation.closest('.modal');
        reservation.noValidate = true;
        modal.classList.add('resident-booking-modal'); body.classList.remove('portal-form-grid');
        const make = (tag, cls, text) => { const el = document.createElement(tag); el.className = cls || ''; if (text) el.textContent = text; return el; };
        const progress = make('ol', 'facility-booking-progress'); progress.setAttribute('aria-label', 'مراحل رزرو');
        for (const label of ['۱. انتخاب زمان', '۲. بررسی و ثبت']) progress.append(make('li', '', label));
        const panels = [make('section', 'portal-form-grid'), make('section', 'portal-form-grid')];
        panels.forEach((panel, index) => { panel.dataset.residentBookingStep = index; });
        for (const field of [...body.children]) {
            const name = field.querySelector('[name]')?.name;
            panels[['unit_id', 'description'].includes(name) ? 1 : 0].append(field);
        }
        const note = description.closest('.portal-field'), extra = make('details', 'resident-booking-extra');
        extra.append(make('summary', '', 'توضیحات (اختیاری)'), note); panels[1].append(extra);
        const review = make('div', 'resident-booking-review portal-field--wide'); review.setAttribute('role', 'status'); panels[1].prepend(review);
        body.append(progress, ...panels);
        const next = make('button', 'portal-primary-button', 'ادامه'); next.type = 'button'; next.dataset.residentBookingNext = '';
        const back = make('button', 'portal-action-button', 'مرحله قبل'); back.type = 'button'; back.dataset.residentBookingBack = '';
        footer.prepend(back); footer.append(next);
        const slotId = document.createElement('input'); slotId.type = 'hidden'; slotId.name = 'facility_time_slot_id'; reservation.append(slotId);
        const group = make('fieldset', 'portal-field portal-field--wide resident-slot-group');
        const legend = make('legend', '', 'زمان‌های رزرو'), status = make('p', 'resident-slot-status'); status.setAttribute('role', 'status');
        const slots = make('div', 'facility-booking-slots resident-slot-list'), policy = make('p', 'resident-slot-status');
        group.append(legend, status, slots, policy); date.closest('.portal-field').after(group);
        const shortcuts = make('div', 'resident-booking-days portal-field--wide');
        for (const [label, offset] of [['امروز', 0], ['فردا', 1]]) {
            const button = make('button', 'portal-action-button', label); button.type = 'button';
            button.addEventListener('click', () => { if (reservation.dataset.portalBusy === '1') return; const day = new Date(today() + 'T12:00:00Z'); day.setUTCDate(day.getUTCDate() + offset); setDate(day.toISOString().slice(0, 10)); load(); }); shortcuts.append(button);
        }
        date.closest('.portal-field').before(shortcuts);
        let step = 0, refreshAfterWrite = false;
        const locked = () => reservation.dataset.portalBusy === '1' || reservation.dataset.residentUncertain === '1';
        const sync = () => {
            if (refreshAfterWrite && !locked()) { refreshAfterWrite = false; load(); return; }
            next.disabled = !picker.selection || locked(); submit.disabled = step !== 1 || !picker.selection || locked(); back.disabled = locked();
            facility.disabled = locked(); window.BuildinoSelect2?.refresh(facility);
        };
        const go = value => {
            step = value; panels.forEach((panel, index) => panel.hidden = index !== step);
            [...progress.children].forEach((el, index) => { if (index === step) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current'); });
            next.hidden = step !== 0; submit.hidden = step !== 1; back.hidden = step === 0; sync();
        };
        const formatMoney = value => money(value) + ' ' + (facility.selectedOptions[0]?.dataset.currency === 'IRT' ? 'تومان' : 'ریال');
        const picker = window.BuildinoFacilityBooking.create({ request: (...args) => window.BuildinoPortalApi(...args), change: state => {
            slotId.value = state.selection?.slotId || '';
            status.textContent = window.BuildinoFacilityBooking.status(state);
            for (const field of [start, end]) { field.closest('.portal-field').hidden = !state.manualAllowed; field.disabled = !state.manualAllowed; }
            window.BuildinoFacilityBooking.render(slots, state, { money: formatMoney, pick: id => picker.pick(id), name: 'resident_slot' });
            if (state.phase === 'error') { const retry = make('button', 'portal-action-button', 'تلاش دوباره'); retry.type = 'button'; retry.addEventListener('click', load); slots.append(retry); }
            sync();
        } });
        const today = () => {
            const timezone = facility.selectedOptions[0]?.dataset.timezone || 'Asia/Tehran';
            const parts = new Intl.DateTimeFormat('en', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
            return ['year', 'month', 'day'].map(type => parts.find(part => part.type === type).value).join('-');
        };
        const setDate = value => {
            if (date.dataset.buildinoGregorianValue) { date.previousElementSibling.value = window.BuildinoJalaliDatepicker.serverToJalali(value, 'date'); date.previousElementSibling.dispatchEvent(new Event('input', { bubbles: true })); }
            else date.value = value;
        };
        const load = () => {
            if (locked()) return;
            go(0); start.value = ''; end.value = ''; const option = facility.selectedOptions[0]; policy.textContent = option?.dataset.policy || '';
            picker.load({ id: Number(facility.value), default_price: Number(option?.dataset.price || 0), requires_payment: option?.dataset.requiresPayment === '1' }, date.value);
        };
        const proceed = () => {
            if (!picker.selection || locked()) return;
            const selected = picker.selection; review.replaceChildren(make('strong', '', facility.selectedOptions[0]?.textContent.trim()));
            const dateText = new Date(selected.date + 'T12:00:00').toLocaleDateString('fa-IR');
            review.append(make('span', '', dateText + ' · ' + digits(String(selected.start_time).slice(0, 5) + ' تا ' + String(selected.end_time).slice(0, 5))));
            review.append(make('strong', '', facility.selectedOptions[0]?.dataset.requiresPayment === '1' && selected.price > 0 ? formatMoney(selected.price) : 'رایگان'));
            review.append(make('p', '', policy.textContent)); go(1); review.tabIndex = -1; review.focus();
        };
        next.addEventListener('click', proceed); back.addEventListener('click', () => { if (!locked()) { go(0); next.focus(); } });
        for (const field of [start, end]) field.addEventListener('input', () => picker.manual(start.value, end.value));
        facility.addEventListener('change', load); date.addEventListener('change', load);
        reservation.elements.unit_id.addEventListener('change', () => { if (step === 1) proceed(); });
        reservation.addEventListener('submit', event => {
            if (step !== 1 || !picker.selection || locked()) { event.preventDefault(); event.stopImmediatePropagation(); if (step === 0) proceed(); }
            else {
                const visibleFields = [...reservation.elements].filter(field => !field.disabled && !field.closest('[data-resident-booking-step]')?.hidden);
                const invalidField = visibleFields.find(field => !field.checkValidity());
                if (invalidField) { event.preventDefault(); event.stopImmediatePropagation(); invalidField.reportValidity(); }
            }
        }, true);
        document.addEventListener('buildino:portal-form-error', event => {
            if (event.detail.form !== reservation) return;
            const keys = Object.keys(event.detail.error.payload?.errors || {});
            if (keys.some(key => ['unit_id', 'description'].includes(key))) {
                go(1); if (keys.includes('description')) extra.open = true;
            } else if (keys.some(key => ['reservation_date', 'start_time', 'end_time', 'facility_time_slot_id'].includes(key))) { refreshAfterWrite = true; picker.reset(); go(0); }
        });
        modal.addEventListener('shown.bs.modal', () => { if (!date.value) setDate(today()); load(); });
        new MutationObserver(sync).observe(reservation, { attributes: true, attributeFilter: ['data-portal-busy', 'data-resident-uncertain'] });
        go(0);
    }

    const stateKeys = ['unit_id','status','from','to','search','page'];
    const rememberFilters = () => {
        const url = new URL(location.href);
        root?.querySelectorAll('[data-dt-filter]').forEach(field => field.value ? url.searchParams.set(field.dataset.dtFilter, field.value) : url.searchParams.delete(field.dataset.dtFilter));
        url.searchParams.delete('page'); history.replaceState(history.state, '', url);
    };
    root?.addEventListener('change', event => { if (event.target.matches('[data-dt-filter]')) rememberFilters(); }, true);
    root?.querySelector('[data-dt-reset]')?.addEventListener('click', () => queueMicrotask(rememberFilters));
    root?.addEventListener('click', event => {
        const link = event.target.closest('a.buildino-dt-action');
        if (!link) return;
        const url = new URL(link.href, location.href), current = new URL(location.href);
        if (url.origin !== location.origin) return;
        stateKeys.forEach(key => { if (current.searchParams.has(key)) url.searchParams.set(key, current.searchParams.get(key)); }); link.href = url.href;
    });
    const fields = {
        invoices: ['invoice_number', [['unit_title','واحد'],['outstanding_amount_formatted','مانده'],['due_date_jalali','سررسید']]],
        reservations: ['facility_title', [['unit_title','واحد'],['reservation_date_jalali','تاریخ'],['time_range','ساعت'],['final_amount_formatted','مبلغ']]],
        guests: ['guest_name', [['unit_title','واحد'],['expected_entry_jalali','زمان مراجعه'],['mobile','موبایل']]],
        services: ['title', [['request_number','شماره درخواست'],['provider_name','ارائه‌دهنده'],['created_at_jalali','تاریخ']]],
        support: ['subject', [['ticket_number','شماره تیکت'],['category_title','دسته‌بندی'],['created_at_jalali','تاریخ']]],
        wallet: ['entry_type_label', [['wallet_label','کیف پول'],['amount_formatted','مبلغ'],['created_at_jalali','تاریخ']]],
    };
    const renderCards = table => {
        if (!fields[resource] || !window.DataTable?.isDataTable(table)) return;
        const api = new window.DataTable.Api(table), rows = api.rows({ page: 'current' }).data().toArray();
        let list = root.querySelector('.resident-mobile-records');
        if (!list) { list = document.createElement('div'); list.className = 'resident-mobile-records'; table.closest('[data-dt-shell]').append(list); }
        list.replaceChildren();
        if (!rows.length) { const empty = document.createElement('p'); empty.className = 'resident-empty'; empty.textContent = 'موردی با این شرایط پیدا نشد.'; list.append(empty); }
        for (const row of rows) {
            const card = document.createElement('article'); card.className = 'resident-mobile-record';
            const header = document.createElement('header'), title = document.createElement('strong'); title.textContent = faText(row[fields[resource][0]] || '—'); header.append(title);
            if (row.status_label) { const state = document.createElement('span'); state.className = 'portal-status portal-status--' + (['success','warning','danger','muted','info'].includes(row.status_tone) ? row.status_tone : 'info'); state.textContent = row.status_label; header.append(state); }
            const data = document.createElement('dl');
            for (const [key, label] of fields[resource][1]) { const line = document.createElement('div'), term = document.createElement('dt'), value = document.createElement('dd'); term.textContent = label; value.textContent = faText(row[key] || '—'); line.append(term, value); data.append(line); }
            card.append(header, data);
            if (row.action_url) { const url = new URL(row.action_url, location.href); if (url.origin === location.origin) { const link = document.createElement('a'); link.className = 'portal-action-button buildino-dt-action'; link.href = url.href; link.textContent = 'مشاهده و پیگیری'; card.append(link); } }
            list.append(card);
        }
        const url = new URL(location.href), search = api.search(), info = api.page.info();
        search ? url.searchParams.set('search', search) : url.searchParams.delete('search');
        info.page ? url.searchParams.set('page', String(info.page + 1)) : url.searchParams.delete('page'); history.replaceState(history.state, '', url);
    };
    const connectTables = () => {
        if (!window.jQuery || !root || root.dataset.residentTables === '1') return;
        root.dataset.residentTables = '1';
        window.jQuery(root).on('draw.dt', 'table.js-server-datatable', event => renderCards(event.target));
        root.querySelectorAll('table.js-server-datatable').forEach(renderCards);
    };
    connectTables(); window.addEventListener('buildino:datatables-ready', connectTables);
})();
