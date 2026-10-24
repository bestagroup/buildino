(() => {
    'use strict';
    const root = document.querySelector('[data-guest-page]');
    if (!root) return;
    const $ = name => root.querySelector(`[data-guest-${name}]`);
    const fields = ['building', 'block', 'floor', 'unit'].map($);
    if (!fields[0]) return;
    const buildings = new Map(window.BuildinoGuestConfig.buildings.map(row => [String(row.id), row]));
    const request = (...args) => window.BuildinoCoreFlows.request(...args);
    const form = $('form'), accessForm = $('access-form');
    const statuses = { invited: 'در انتظار مراجعه', entered: 'داخل ساختمان', exited: 'مراجعه پایان‌یافته', expired: 'منقضی‌شده', cancelled: 'لغوشده' };
    const number = value => new Intl.NumberFormat('fa-IR').format(value);
    const date = value => value ? new Date(value).toLocaleString('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }) : 'تعیین نشده';
    const node = (tag, cls, text) => { const el = document.createElement(tag); el.className = cls || ''; if (text !== undefined) el.textContent = text; return el; };
    const button = (label, handler) => { const el = node('button', 'ownership-button', label); el.type = 'button'; el.addEventListener('click', handler); return el; };
    let generation = 0, listGeneration = 0, detailGeneration = 0, step = 0, page = 1, lastPage = 1, logsPage = 1, logsLast = 1;
    let selected = null, accessAction = null, busy = false, searchTimer;
    const building = () => buildings.get(fields[0].value);
    const unit = () => fields[3].value;
    const expired = row => Boolean(row.expected_exit_at && new Date(row.expected_exit_at).getTime() < Date.now());
    const value = key => form.elements.namedItem(key).value.trim();
    const message = (kind, text = '') => { for (const key of ['error', 'success']) { $(key).hidden = key !== kind || !text; $(key).textContent = key === kind ? text : ''; } };
    const report = error => {
        const translations = {
            'This guest visit has expired.': 'مجوز مراجعه منقضی شده است؛ ورود مهمان پذیرفته نشد.',
            'Guest entry is allowed only for an invited visit.': 'وضعیت این مراجعه برای ثبت ورود معتبر نیست.',
            'Guest exit is allowed only after an entry has been recorded.': 'ثبت خروج فقط پس از ورود امکان‌پذیر است.',
        };
        const details = Object.values(error.errors || {}).flat();
        message('error', details.length ? details.map(text => translations[text] || text).join('؛ ') : error.status ? error.message : 'ارتباط با سرور برقرار نشد. فهرست را به‌روزرسانی و دوباره تلاش کنید.');
    };
    const refresh = select => window.BuildinoSelect2?.refresh(select);
    const lock = state => {
        busy = state;
        root.setAttribute('aria-busy', String(state));
        fields.forEach(el => { el.disabled = state || el.options.length <= 1; refresh(el); });
        root.querySelectorAll('button').forEach(el => { if (state) el.dataset.wasDisabled = String(el.disabled); el.disabled = state || el.dataset.wasDisabled === 'true'; });
    };
    const closeDetail = () => { ++detailGeneration; selected = null; $('detail').hidden = true; accessForm.hidden = true; };
    const resetContext = () => {
        ++listGeneration; closeDetail(); $('records').hidden = true; $('editor').hidden = true;
        $('list').replaceChildren(); message('', '');
    };
    const loadChildren = async index => {
        const ticket = ++generation; resetContext();
        for (let i = index + 1; i < fields.length; i++) { fields[i].replaceChildren(new Option('ابتدا محدوده بالاتر را انتخاب کنید', '')); fields[i].disabled = true; refresh(fields[i]); }
        if (!fields[index].value) return;
        if (index === 3) {
            $('unit-label').textContent = fields.map(el => el.selectedOptions[0]?.textContent).join(' / ');
            $('add').hidden = !building()?.canCreate; $('records').hidden = false;
            $('search').value = ''; $('status').value = ''; refresh($('status'));
            $('location-status').textContent = 'واحد انتخاب شد.';
            await loadList(1); return;
        }
        const types = ['blocks', 'floors', 'units'], keys = ['building_id', 'block_id', 'floor_id'];
        $('location-status').textContent = 'در حال دریافت محدوده‌ها…';
        try {
            const payload = await request(`/management/lookups/${types[index]}?${keys[index]}=${fields[index].value}`);
            if (ticket !== generation) return;
            const target = fields[index + 1];
            target.replaceChildren(new Option('انتخاب کنید', ''));
            for (const row of payload.data || []) target.add(new Option(row.label, row.id));
            target.disabled = target.options.length <= 1;
            $('location-status').textContent = target.disabled ? 'در این محدوده موردی ثبت نشده است.' : 'محدوده بعدی را انتخاب کنید.';
            if (target.options.length === 2) target.selectedIndex = 1;
            refresh(target);
            if (target.value) await loadChildren(index + 1);
        } catch (error) { if (ticket === generation) { $('location-status').textContent = 'دریافت اطلاعات انجام نشد؛ محدوده را دوباره انتخاب کنید.'; report(error); } }
    };
    const card = (title, lines) => {
        const el = node('article', 'ownership-record'), copy = node('div', 'ownership-record__main');
        copy.append(node('strong', '', title));
        for (const line of lines) copy.append(node('small', '', line));
        el.append(copy); return el;
    };
    const guestName = row => `${row.guest?.first_name || ''} ${row.guest?.last_name || ''}`.trim() || 'مهمان';
    async function loadList(targetPage = page) {
        const ticket = ++listGeneration;
        $('list').replaceChildren(); $('list-status').textContent = 'در حال دریافت مراجعه‌ها…';
        $('prev').disabled = true; $('next').disabled = true;
        const query = new URLSearchParams({ page: String(targetPage), search: $('search').value.trim(), status: $('status').value });
        try {
            const payload = await request(`/api/v1/units/${unit()}/guest-visits?${query}`);
            if (ticket !== listGeneration) return;
            page = payload.meta?.current_page || 1; lastPage = payload.meta?.last_page || 1;
            $('list-status').textContent = payload.data.length ? `${number(payload.meta?.total ?? payload.data.length)} مراجعه` : 'مراجعه‌ای با این شرایط پیدا نشد.';
            for (const row of payload.data) {
                const el = card(guestName(row), [`ورود مورد انتظار: ${date(row.expected_entry_at)}`, `خروج مورد انتظار: ${date(row.expected_exit_at)}`]);
                const meta = node('div', 'ownership-record__meta'); meta.append(node('span', row.status === 'entered' ? 'is-primary' : '', statuses[row.status] || 'نامشخص'));
                el.append(meta, button('مجوز و جزئیات', () => { if (!busy) openDetail(row.id); })); $('list').append(el);
            }
            $('page-label').textContent = `صفحه ${number(page)} از ${number(lastPage)}`;
            $('prev').disabled = busy || page <= 1; $('next').disabled = busy || page >= lastPage;
        } catch (error) { if (ticket === listGeneration) { $('list-status').textContent = 'دریافت فهرست انجام نشد؛ به‌روزرسانی را بزنید.'; report(error); } }
    }
    const showStep = next => {
        step = next;
        root.querySelectorAll('[data-guest-step]').forEach(el => el.hidden = Number(el.dataset.guestStep) !== step);
        root.querySelectorAll('[data-guest-step-label]').forEach(el => {
            const index = Number(el.dataset.guestStepLabel); el.className = index === step ? 'is-current' : index < step ? 'is-complete' : '';
            if (index === step) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
        });
        $('back').hidden = step === 0; $('continue').textContent = step === 2 ? 'ثبت مراجعه و ایجاد مجوز' : 'ادامه';
        $('review').replaceChildren(card(`${value('first_name')} ${value('last_name')}`, [
            $('unit-label').textContent, `موبایل: ${value('mobile') || 'وارد نشده'}`, `کد ملی: ${value('national_code') || 'وارد نشده'}`, `پلاک: ${value('vehicle_plate') || 'وارد نشده'}`,
            `ورود: ${date(value('expected_entry_at'))}`, `خروج: ${date(value('expected_exit_at'))}`, value('description'),
        ]));
    };
    const valid = () => {
        const panel = root.querySelector(`[data-guest-step="${Math.min(step, 1)}"]`);
        for (const input of panel.querySelectorAll('input, textarea')) {
            if (!input.checkValidity()) { message('error', input.validationMessage || 'اطلاعات این مرحله را کامل کنید.'); input.focus(); return false; }
        }
        const names = step === 0 ? ['first_name', 'last_name'] : ['expected_entry_at'];
        for (const key of names) if (!value(key)) {
            message('error', step === 0 ? 'نام و نام خانوادگی مهمان را وارد کنید.' : 'زمان مراجعه را وارد کنید.');
            const el = form.elements.namedItem(key); (el.type === 'hidden' ? el.previousElementSibling : el)?.focus(); return false;
        }
        if (step > 0 && (!Number.isFinite(new Date(value('expected_entry_at')).getTime()) || value('expected_exit_at') && (!Number.isFinite(new Date(value('expected_exit_at')).getTime()) || new Date(value('expected_exit_at')) < new Date(value('expected_entry_at'))))) {
            message('error', 'زمان‌ها را بررسی کنید؛ خروج نباید قبل از ورود باشد.'); return false;
        }
        message('', ''); return true;
    };
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !building()?.canCreate || !valid()) return;
        if (step < 2) { showStep(step + 1); $('editor-title').focus(); return; }
        const hostUnit = unit();
        lock(true);
        try {
            const payload = await request(`/api/v1/units/${hostUnit}/guest-visits`, { method: 'POST', body: JSON.stringify({
                guest: Object.fromEntries(['first_name', 'last_name', 'mobile', 'national_code', 'vehicle_plate'].map(key => [key, value(key) || null])),
                expected_entry_at: new Date(value('expected_entry_at')).toISOString(),
                expected_exit_at: value('expected_exit_at') ? new Date(value('expected_exit_at')).toISOString() : null,
                description: value('description') || null,
            }) });
            $('editor').hidden = true; message('success', 'مراجعه ثبت شد و مجوز ایجاد شد.');
            await loadList(1); await openDetail(payload.data.id);
        } catch (error) { report(error); } finally { lock(false); syncPaging(); }
    });
    const syncPaging = () => {
        $('prev').disabled = busy || page <= 1; $('next').disabled = busy || page >= lastPage;
        $('logs-prev').disabled = busy || logsPage <= 1; $('logs-next').disabled = busy || logsPage >= logsLast;
    };
    async function loadLogs(targetPage = 1, ticket = detailGeneration) {
        $('logs').replaceChildren(node('p', 'ownership-form-hint', 'در حال دریافت سابقه…'));
        $('logs-prev').disabled = true; $('logs-next').disabled = true;
        const id = selected.id;
        try {
            const payload = await request(`/api/v1/guest-visits/${id}/access-logs?page=${targetPage}`);
            if (ticket !== detailGeneration) return;
            logsPage = payload.meta?.current_page || 1; logsLast = payload.meta?.last_page || 1;
            $('logs').replaceChildren();
            for (const log of payload.data) $('logs').append(card(log.action === 'entry' ? 'ورود ثبت‌شده' : 'خروج ثبت‌شده', [date(log.occurred_at), log.verifier ? `ثبت‌کننده: ${log.verifier.first_name || ''} ${log.verifier.last_name || ''}` : '', log.notes || '']));
            if (!payload.data.length) $('logs').append(node('p', 'ownership-empty', 'هنوز ورود یا خروجی ثبت نشده است.'));
            $('logs-page').textContent = `صفحه ${number(logsPage)} از ${number(logsLast)}`; syncPaging();
        } catch (error) { if (ticket === detailGeneration) { $('logs').replaceChildren(node('p', 'ownership-form-hint', 'سابقه دریافت نشد.')); $('logs').append(button('تلاش مجدد', () => loadLogs(targetPage))); report(error); } }
    }
    async function openDetail(id) {
        const ticket = ++detailGeneration;
        selected = null; accessForm.hidden = true; $('detail').hidden = false;
        $('detail-body').replaceChildren(); $('detail-actions').replaceChildren(); $('logs').replaceChildren();
        $('detail-status').textContent = 'در حال دریافت مجوز…';
        try {
            const payload = await request(`/api/v1/guest-visits/${id}`);
            if (ticket !== detailGeneration) return;
            selected = payload.data;
            $('detail-status').textContent = `مراجعه شماره ${number(id)} · ${statuses[selected.status] || 'نامشخص'}`;
            $('detail-body').append(card(guestName(selected), [`موبایل: ${selected.guest?.mobile || 'وارد نشده'}`, `پلاک: ${selected.guest?.vehicle_plate || 'وارد نشده'}`, `ورود مورد انتظار: ${date(selected.expected_entry_at)}`, `خروج مورد انتظار: ${date(selected.expected_exit_at)}`, selected.description || '']));
            if (selected.status === 'invited' && expired(selected)) $('detail-body').append(node('p', 'ownership-alert ownership-alert--error', 'زمان پایان مجوز گذشته است؛ ورود مهمان پذیرفته نمی‌شود.'));
            if (building()?.canUpdate && (selected.status === 'entered' || selected.status === 'invited' && !expired(selected))) {
                const action = selected.status === 'entered' ? 'exit' : 'entry';
                $('detail-actions').append(button(action === 'entry' ? 'بررسی مجوز و ثبت ورود' : 'ثبت خروج و اتمام مراجعه', () => {
                    if (busy) return;
                    accessAction = action; accessForm.reset(); accessForm.hidden = false;
                    $('access-title').textContent = action === 'entry' ? 'ثبت ورود مهمان' : 'ثبت خروج مهمان';
                    $('access-hint').textContent = action === 'entry' ? 'اعتبار مجوز در سرور بررسی می‌شود. در صورت نامعتبر بودن، ورود ثبت نمی‌شود.' : 'خروج با زمان فعلی ثبت و مراجعه به سابقه تبدیل می‌شود.';
                    accessForm.elements.notes.focus();
                }));
            }
            $('detail-title').focus(); await loadLogs(1, ticket);
        } catch (error) { if (ticket === detailGeneration) { $('detail-status').textContent = 'مجوز دریافت نشد.'; $('detail-body').append(button('تلاش مجدد', () => openDetail(id))); report(error); } }
    }
    accessForm.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !selected || !accessAction || !building()?.canUpdate) return;
        const id = selected.id, action = accessAction; lock(true);
        try {
            await request(`/api/v1/guest-visits/${id}/${action}`, { method: 'POST', body: JSON.stringify({ notes: accessForm.elements.notes.value.trim() || null }) });
            message('success', action === 'entry' ? 'ورود مهمان ثبت شد.' : 'خروج مهمان ثبت شد و مراجعه پایان یافت.');
            await loadList(); await openDetail(id);
        } catch (error) { report(error); await loadList(); await openDetail(id); }
        finally { lock(false); syncPaging(); }
    });
    fields.forEach((el, index) => el.addEventListener('change', () => { if (!busy) loadChildren(index); }));
    $('add').addEventListener('click', () => { if (busy || !building()?.canCreate) return; closeDetail(); form.reset(); $('editor').hidden = false; showStep(0); form.elements.first_name.focus(); });
    $('cancel').addEventListener('click', () => { if (!busy) { $('editor').hidden = true; $('add').focus(); } });
    $('back').addEventListener('click', () => { if (!busy) { showStep(Math.max(0, step - 1)); $('editor-title').focus(); } });
    $('close-detail').addEventListener('click', () => { if (!busy) { closeDetail(); $('reload').focus(); } });
    $('access-cancel').addEventListener('click', () => { if (!busy) { accessForm.hidden = true; $('detail-title').focus(); } });
    $('reload').addEventListener('click', () => { if (!busy) loadList(); });
    $('prev').addEventListener('click', () => { if (!busy && page > 1) loadList(page - 1); });
    $('next').addEventListener('click', () => { if (!busy && page < lastPage) loadList(page + 1); });
    $('logs-prev').addEventListener('click', () => { if (!busy && selected && logsPage > 1) loadLogs(logsPage - 1); });
    $('logs-next').addEventListener('click', () => { if (!busy && selected && logsPage < logsLast) loadLogs(logsPage + 1); });
    $('search').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { if (!busy) loadList(1); }, 350); });
    $('status').addEventListener('change', () => { if (!busy) loadList(1); });
    if (fields[0].options.length === 2) { fields[0].selectedIndex = 1; refresh(fields[0]); loadChildren(0); }
})();
