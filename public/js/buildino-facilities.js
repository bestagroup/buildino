(() => {
    'use strict';
    const root = document.querySelector('[data-facility-page]');
    if (!root) return;
    const $ = key => root.querySelector(`[data-facility-${key}]`);
    const buildingSelect = $('building');
    const buildings = new Map(window.BuildinoFacilityConfig.buildings.map(row => [String(row.id), row]));
    const request = (...args) => window.BuildinoCoreFlows.request(...args);
    const forms = { base: $('base-form'), schedule: $('schedule-form'), slot: $('slot-form'), rule: $('rule-form') };
    const types = { gym: 'باشگاه ورزشی', pool: 'استخر', roof_garden: 'روف‌گاردن', meeting_hall: 'سالن جلسات / اجتماعات', other: 'سایر' };
    const days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    const number = value => new Intl.NumberFormat('fa-IR').format(value);
    const building = () => buildings.get(buildingSelect.value);
    const currency = () => ({ IRR: 'ریال', IRT: 'تومان' }[building()?.currency] || building()?.currency || 'واحد پول ساختمان');
    const money = value => `${number(value)} ${currency()}`;
    const input = (form, key) => form.elements.namedItem(key);
    const get = (form, key) => input(form, key).value.trim();
    const checked = (form, key) => input(form, key).checked;
    const numeric = (form, key) => get(form, key) === '' ? null : Number(get(form, key));
    const time = value => String(value || '').slice(0, 5);
    const minutes = value => { const [h, m] = time(value).split(':').map(Number); return h * 60 + m; };
    const make = (tag, cls, text) => { const el = document.createElement(tag); el.className = cls || ''; if (text !== undefined) el.textContent = text; return el; };
    const button = (label, action) => { const el = make('button', 'ownership-button', label); el.type = 'button'; el.addEventListener('click', () => { if (!busy) action(); }); return el; };
    const card = (title, text) => { const el = make('article', 'ownership-record'), copy = make('div', 'ownership-record__main'); copy.append(make('strong', '', title), make('small', '', text)); el.append(copy); return el; };
    let facility = null, scheduleId = null, slotId = null, page = 1, lastPage = 1, busy = false, loadingDetail = false;
    let listTicket = 0, detailTicket = 0, step = 0, searchTimer, previousBuilding = buildingSelect.value;
    const dirty = new Set();
    const refresh = select => window.BuildinoSelect2?.refresh(select);
    const message = (kind, text = '') => { for (const key of ['error', 'success']) { $(key).hidden = key !== kind || !text; $(key).textContent = key === kind ? text : ''; } };
    const report = error => {
        const translations = {
            'The facility schedule overlaps an existing schedule for this day.': 'این بازه با برنامه دیگری در همان روز هم‌پوشانی دارد.',
            'The time slot must be fully inside its facility schedule.': 'سانس باید کاملاً داخل بازه برنامه هفتگی باشد.',
            'The facility time slot overlaps an existing time slot.': 'این سانس با سانس دیگری هم‌پوشانی دارد.',
            'End time must be after start time.': 'زمان پایان باید بعد از زمان شروع باشد.',
            'Maximum duration must be greater than or equal to minimum duration.': 'حداکثر مدت رزرو نباید کمتر از حداقل آن باشد.',
        };
        const errors = Object.values(error.errors || {}).flat();
        message('error', error.partial ? 'قوانین رزرو ذخیره شد، اما تنظیم تأیید مدیر ذخیره نشد. دوباره این بخش را ذخیره کنید.' : errors.length ? errors.map(text => translations[text] || text).join('؛ ') : error.status ? error.message : 'ارتباط برقرار نشد؛ پیش از تکرار ثبت، اطلاعات را به‌روزرسانی کنید.');
    };
    const canWrite = () => Boolean(facility?.id ? building()?.canUpdate : building()?.canCreate);
    const sync = () => {
        root.setAttribute('aria-busy', String(busy || loadingDetail));
        buildingSelect.disabled = busy; refresh(buildingSelect);
        root.querySelectorAll('button').forEach(el => el.disabled = busy);
        root.querySelectorAll('[data-facility-step-button]').forEach(el => el.disabled = busy || loadingDetail || !facility?.id && Number(el.dataset.facilityStepButton) > 0);
        for (const key of Object.keys(forms)) {
            const allowed = key === 'base' ? canWrite() : Boolean(facility?.id && building()?.canUpdate);
            $(`${key}-fields`).disabled = busy || loadingDetail || !allowed;
            $(`${key}-save`).hidden = !allowed;
            forms[key].querySelectorAll('select').forEach(refresh);
        }
        input(forms.slot, 'schedule_id').disabled = busy || loadingDetail || !building()?.canUpdate || slotId !== null;
        refresh(input(forms.slot, 'schedule_id'));
        $('schedule-reset').hidden = !building()?.canUpdate;
        $('slot-reset').hidden = !building()?.canUpdate;
        $('prev').disabled = busy || page <= 1; $('next').disabled = busy || page >= lastPage;
        $('activate').disabled = busy || loadingDetail || !building()?.canUpdate || !facility?.id || facility.is_active || dirty.size > 0 || deficiencies().length > 0;
    };
    const discard = () => !dirty.size || window.confirm('تغییرات ذخیره‌نشده کنار گذاشته شوند؟');
    const fill = (form, values) => {
        for (const el of form.elements) {
            if (!el.name || !(el.name in values)) continue;
            if (el.type === 'checkbox') el.checked = Boolean(values[el.name]); else el.value = values[el.name] ?? '';
            if (el.tagName === 'SELECT') refresh(el);
        }
    };
    const schedules = () => facility?.schedules || [];
    const scheduleLabel = row => `${days[row.day_of_week]} · ${time(row.start_time)} تا ${time(row.end_time)}${row.is_active ? '' : ' · غیرفعال'}`;
    function resetSchedule() { forms.schedule.reset(); scheduleId = null; dirty.delete('schedule'); $('schedule-title').textContent = 'افزودن برنامه هفتگی'; }
    function resetSlot() {
        forms.slot.reset(); slotId = null; dirty.delete('slot');
        fill(forms.slot, { capacity: facility?.capacity ?? 1, price: facility?.default_price ?? 0 });
        $('slot-title').textContent = 'افزودن سانس قابل رزرو'; sync();
    }
    function renderSchedules() {
        $('schedules').replaceChildren();
        const select = input(forms.slot, 'schedule_id'), previous = select.value;
        select.replaceChildren(new Option('برنامه را انتخاب کنید', ''));
        for (const row of schedules()) {
            select.add(new Option(scheduleLabel(row), row.id));
            const el = card(scheduleLabel(row), `${number(row.time_slots?.length || 0)} سانس تعریف شده`);
            if (building()?.canUpdate) el.append(button('ویرایش برنامه', () => {
                if (dirty.has('schedule') && !window.confirm('تغییرات برنامه ذخیره نشده است؛ کنار گذاشته شود؟')) return;
                resetSchedule(); scheduleId = row.id;
                forms.schedule.querySelectorAll('[name="days"]').forEach(el => el.checked = Number(el.value) === row.day_of_week);
                fill(forms.schedule, { ...row, start_time: time(row.start_time), end_time: time(row.end_time) });
                $('schedule-title').textContent = 'ویرایش برنامه · فقط یک روز را انتخاب کنید'; input(forms.schedule, 'start_time').focus();
            }));
            $('schedules').append(el);
            for (const slot of row.time_slots || []) {
                const item = card(`سانس ${time(slot.start_time)} تا ${time(slot.end_time)}${slot.is_active ? '' : ' · غیرفعال'}`, `ظرفیت: ${number(slot.capacity ?? 1)} نفر · هزینه: ${money(slot.price)}`);
                if (building()?.canUpdate) item.append(button('ویرایش سانس', () => {
                    if (dirty.has('slot') && !window.confirm('تغییرات سانس ذخیره نشده است؛ کنار گذاشته شود؟')) return;
                    resetSlot(); slotId = slot.id;
                    fill(forms.slot, { ...slot, schedule_id: row.id, capacity: slot.capacity ?? 1, start_time: time(slot.start_time), end_time: time(slot.end_time) });
                    $('slot-title').textContent = 'ویرایش سانس'; sync(); input(forms.slot, 'start_time').focus();
                }));
                $('schedules').append(item);
            }
        }
        if (!schedules().length) $('schedules').append(make('p', 'ownership-empty', 'هنوز برنامه و سانسی تعریف نشده است. از فرم زیر شروع کنید.'));
        if ([...select.options].some(option => option.value === previous)) select.value = previous;
        else if (schedules().length === 1) select.value = String(schedules()[0].id);
        refresh(select);
    }
    function deficiencies() {
        if (!facility?.id) return [{ text: 'ابتدا اطلاعات پایه را ذخیره کنید.', step: 0 }];
        const issues = [];
        if (!facility.title?.trim() || !facility.code?.trim() || !types[facility.type] || facility.default_price < 0) issues.push({ text: 'عنوان، کد، نوع و هزینه پایه را کامل کنید.', step: 0 });
        const active = schedules().filter(row => row.is_active);
        if (!active.length) issues.push({ text: 'حداقل یک برنامه هفتگی فعال تعریف کنید.', step: 1 });
        if (!active.some(row => row.time_slots?.some(slot => slot.is_active))) issues.push({ text: 'حداقل یک سانس فعال در برنامه فعال تعریف کنید.', step: 1 });
        const rule = facility.reservation_rule;
        if (!rule) issues.push({ text: 'قوانین رزرو را بررسی و ذخیره کنید.', step: 2 });
        for (const schedule of active) for (const slot of schedule.time_slots || []) {
            if (!slot.is_active) continue;
            const duration = minutes(slot.end_time) - minutes(slot.start_time);
            if (duration <= 0 || minutes(slot.start_time) < minutes(schedule.start_time) || minutes(slot.end_time) > minutes(schedule.end_time)) issues.push({ text: `بازه سانس ${scheduleLabel(schedule)} را اصلاح کنید.`, step: 1 });
            if (rule && (rule.min_duration_minutes && duration < rule.min_duration_minutes || rule.max_duration_minutes && duration > rule.max_duration_minutes)) issues.push({ text: `مدت سانس ${scheduleLabel(schedule)} با حداقل یا حداکثر مدت رزرو سازگار نیست.`, step: 2 });
        }
        for (const key of dirty) issues.push({ text: `تغییرات ${ { base: 'اطلاعات پایه', schedule: 'برنامه', slot: 'سانس', rule: 'قوانین' }[key]} هنوز ذخیره نشده است.`, step: { base: 0, schedule: 1, slot: 1, rule: 2 }[key] });
        return issues;
    }
    function renderReview() {
        const host = $('checklist'); host.replaceChildren();
        if (!facility) return;
        host.append(card(facility.title, `${types[facility.type]} · کد ${facility.code} · ظرفیت پایه ${facility.capacity ? number(facility.capacity) : 'تعیین نشده'} · ${money(facility.default_price)}`));
        const issues = deficiencies();
        if (issues.length) for (const issue of issues) { const el = card('نیاز به تکمیل', issue.text); el.append(button('اصلاح این بخش', () => go(issue.step))); host.append(el); }
        else host.append(card('اطلاعات پیکربندی کامل است', 'اطلاعات پایه، برنامه و سانس فعال و قوانین رزرو ذخیره شده‌اند.'));
        $('activation-state').textContent = facility.is_active ? 'این امکان در سامانه فعال است.' : issues.length ? 'امکان غیرفعال می‌ماند تا موارد بالا تکمیل شوند.' : 'امکان آماده فعال‌سازی است.';
        $('activate').textContent = facility.is_active ? 'امکان فعال است' : 'فعال‌سازی امکان'; sync();
    }
    function go(next) {
        if (busy || loadingDetail || !facility?.id && next > 0) return;
        step = next;
        root.querySelectorAll('[data-facility-panel]').forEach(el => el.hidden = Number(el.dataset.facilityPanel) !== step);
        root.querySelectorAll('[data-facility-step-button]').forEach(el => {
            if (Number(el.dataset.facilityStepButton) === step) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
        });
        if (step === 3) renderReview(); sync(); $('editor-title').focus();
    }
    async function loadList(target = page) {
        const ticket = ++listTicket;
        $('list').replaceChildren(); $('list-status').textContent = 'در حال دریافت امکانات…'; $('prev').disabled = true; $('next').disabled = true;
        const query = new URLSearchParams({ page: String(target), search: $('search').value.trim() });
        try {
            const payload = await request(`/api/v1/buildings/${buildingSelect.value}/facilities?${query}`);
            if (ticket !== listTicket) return;
            page = payload.meta?.current_page || 1; lastPage = payload.meta?.last_page || 1;
            $('list-status').textContent = payload.data.length ? `${number(payload.meta?.total ?? payload.data.length)} امکان` : 'امکانی پیدا نشد. برای شروع «امکان جدید» را بزنید.';
            for (const row of payload.data) {
                const el = card(row.title, `${types[row.type] || row.type} · ${row.is_active ? 'فعال' : 'غیرفعال / در حال پیکربندی'} · ${money(row.default_price)}`);
                el.append(button(building()?.canUpdate ? 'ادامه پیکربندی' : 'مشاهده پیکربندی', () => open(row.id))); $('list').append(el);
            }
            $('pagination').textContent = `صفحه ${number(page)} از ${number(lastPage)}`; sync();
        } catch (error) { if (ticket === listTicket) { $('list-status').textContent = 'اطلاعات دریافت نشد؛ به‌روزرسانی را بزنید.'; report(error); } }
    }
    async function readFacility() {
        const payload = await request(`/api/v1/facilities/${facility.id}`);
        facility = payload.data; return facility;
    }
    async function open(id = null) {
        if (!discard()) return;
        const ticket = ++detailTicket; dirty.clear(); facility = null; loadingDetail = false;
        Object.values(forms).forEach(form => form.reset()); scheduleId = null; slotId = null;
        $('editor').hidden = false; $('save-state').textContent = id ? 'در حال دریافت اطلاعات…' : 'امکان جدید تا پایان پیکربندی غیرفعال ذخیره می‌شود.';
        go(0); loadingDetail = Boolean(id); sync();
        if (id) try {
            const payload = await request(`/api/v1/facilities/${id}`); if (ticket !== detailTicket) return;
            facility = payload.data;
            fill(forms.base, facility); fill(forms.rule, { ...facility.reservation_rule, requires_approval: facility.requires_approval });
            renderSchedules(); resetSlot();
            $('save-state').textContent = facility.is_active ? 'امکان فعال است؛ تغییرات هر بخش پس از ذخیره اعمال می‌شوند.' : 'پیکربندی ذخیره‌شده؛ برای آماده‌شدن، مراحل را تکمیل کنید.';
        } catch (error) { if (ticket === detailTicket) { $('editor').hidden = true; report(error); } }
        finally { if (ticket === detailTicket) { loadingDetail = false; sync(); } }
        else renderSchedules();
    }
    const validForm = form => {
        for (const el of form.elements) if (el.willValidate && !el.checkValidity()) { el.closest('details')?.setAttribute('open', ''); el.reportValidity(); return false; }
        return true;
    };
    async function save(key, operation, next = step) {
        if (busy || loadingDetail || !validForm(forms[key]) || !(key === 'base' ? canWrite() : building()?.canUpdate && facility?.id)) return;
        busy = true; message('', ''); sync();
        let stored = false;
        try {
            await operation(); stored = true; dirty.delete(key);
            if (key !== 'base') await readFacility();
            renderSchedules(); message('success', 'اطلاعات این بخش ذخیره شد.'); $('save-state').textContent = 'آخرین تغییرات این بخش در سامانه ذخیره شد.';
        } catch (error) {
            report(error);
            if (stored) message('error', 'اطلاعات ذخیره شد، اما دریافت وضعیت جدید انجام نشد. پیش از تکرار ثبت، امکان را دوباره باز کنید.');
        } finally { busy = false; sync(); if (stored) go(next); }
    }
    forms.base.addEventListener('submit', event => {
        event.preventDefault();
        if (!get(forms.base, 'title') || !get(forms.base, 'code')) { message('error', 'عنوان و کد امکان را وارد کنید.'); return; }
        save('base', async () => {
            const creating = !facility?.id;
            const form = forms.base, data = { title: get(form, 'title'), code: get(form, 'code'), type: get(form, 'type'), description: get(form, 'description') || null, capacity: numeric(form, 'capacity'), default_price: numeric(form, 'default_price'), requires_payment: checked(form, 'requires_payment') };
            if (creating) data.is_active = false;
            const payload = await request(facility?.id ? `/api/v1/facilities/${facility.id}` : `/api/v1/buildings/${buildingSelect.value}/facilities`, { method: facility?.id ? 'PATCH' : 'POST', body: JSON.stringify(data) });
            facility = payload.data; if (creating) resetSlot();
        }, 1);
    });
    forms.schedule.addEventListener('submit', event => {
        event.preventDefault();
        const selectedDays = [...forms.schedule.querySelectorAll('[name="days"]:checked')].map(el => Number(el.value));
        if (!selectedDays.length || scheduleId && selectedDays.length !== 1) { message('error', scheduleId ? 'برای ویرایش فقط یک روز انتخاب کنید.' : 'حداقل یک روز فعالیت انتخاب کنید.'); return; }
        save('schedule', async () => {
            const form = forms.schedule, data = { start_time: get(form, 'start_time'), end_time: get(form, 'end_time'), is_active: checked(form, 'is_active'), ...(scheduleId ? { day_of_week: selectedDays[0] } : { days_of_week: selectedDays }) };
            await request(`/api/v1/facilities/${facility.id}/schedules${scheduleId ? `/${scheduleId}` : ''}`, { method: scheduleId ? 'PATCH' : 'POST', body: JSON.stringify(data) }); resetSchedule();
        });
    });
    forms.slot.addEventListener('submit', event => {
        event.preventDefault();
        save('slot', async () => {
            const form = forms.slot, data = { start_time: get(form, 'start_time'), end_time: get(form, 'end_time'), capacity: numeric(form, 'capacity'), price: numeric(form, 'price'), is_active: checked(form, 'is_active') };
            await request(`/api/v1/facilities/${facility.id}/schedules/${get(form, 'schedule_id')}/time-slots${slotId ? `/${slotId}` : ''}`, { method: slotId ? 'PATCH' : 'POST', body: JSON.stringify(data) }); resetSlot();
        });
    });
    forms.rule.addEventListener('submit', event => {
        event.preventDefault();
        save('rule', async () => {
            const form = forms.rule, data = {};
            for (const el of form.elements) if (el.name && el.name !== 'requires_approval') data[el.name] = el.type === 'checkbox' ? el.checked : numeric(form, el.name);
            await request(`/api/v1/facilities/${facility.id}/reservation-rule`, { method: 'PUT', body: JSON.stringify(data) });
            // This flag belongs to the facility, not the reservation-rule resource.
            try {
                await request(`/api/v1/facilities/${facility.id}`, { method: 'PATCH', body: JSON.stringify({ requires_approval: checked(form, 'requires_approval') }) });
            } catch (error) { error.partial = true; throw error; }
        }, 3);
    });
    $('activate').addEventListener('click', async () => {
        if (busy || loadingDetail || !facility?.id || !building()?.canUpdate || dirty.size) return;
        busy = true; sync(); message('', '');
        try {
            await readFacility();
            if (deficiencies().length) { renderReview(); return; }
            const payload = await request(`/api/v1/facilities/${facility.id}`, { method: 'PATCH', body: JSON.stringify({ is_active: true }) });
            facility = payload.data; message('success', 'امکان فعال شد و برای رزرو آماده است.'); await loadList();
        } catch (error) { report(error); }
        finally { busy = false; renderReview(); }
    });
    $('check').addEventListener('click', async () => {
        if (busy || !facility?.id) return; busy = true; sync();
        try { await readFacility(); renderReview(); } catch (error) { report(error); } finally { busy = false; sync(); }
    });
    for (const [key, form] of Object.entries(forms)) {
        form.addEventListener('input', () => { dirty.add(key); $('activate').disabled = true; $('save-state').textContent = 'تغییرات ذخیره‌نشده دارید.'; });
        form.addEventListener('change', () => { dirty.add(key); $('activate').disabled = true; });
    }
    root.querySelectorAll('[data-facility-step-button]').forEach(el => el.addEventListener('click', () => go(Number(el.dataset.facilityStepButton))));
    root.querySelectorAll('[data-facility-go]').forEach(el => el.addEventListener('click', () => go(Number(el.dataset.facilityGo))));
    $('schedule-reset').addEventListener('click', () => { if (!busy && (!dirty.has('schedule') || window.confirm('تغییرات برنامه کنار گذاشته شود؟'))) resetSchedule(); });
    $('slot-reset').addEventListener('click', () => { if (!busy && (!dirty.has('slot') || window.confirm('تغییرات سانس کنار گذاشته شود؟'))) resetSlot(); });
    $('new').addEventListener('click', () => { if (!busy && building()?.canCreate) open(); });
    $('close').addEventListener('click', () => { if (!busy && discard()) { ++detailTicket; loadingDetail = false; dirty.clear(); $('editor').hidden = true; $('reload').focus(); } });
    $('reload').addEventListener('click', () => { if (!busy) loadList(); });
    $('prev').addEventListener('click', () => { if (!busy && page > 1) loadList(page - 1); });
    $('next').addEventListener('click', () => { if (!busy && page < lastPage) loadList(page + 1); });
    $('search').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { if (!busy) loadList(1); }, 350); });
    buildingSelect.addEventListener('change', () => {
        if (busy || !discard()) { buildingSelect.value = previousBuilding; refresh(buildingSelect); return; }
        previousBuilding = buildingSelect.value; ++detailTicket; ++listTicket; loadingDetail = false; dirty.clear(); facility = null;
        $('editor').hidden = true; $('directory').hidden = !building(); $('search').value = ''; message('', '');
        root.querySelectorAll('[data-facility-currency]').forEach(el => el.textContent = currency());
        $('new').hidden = !building()?.canCreate;
        if (building()) loadList(1);
    });
    window.addEventListener('beforeunload', event => { if (dirty.size) { event.preventDefault(); event.returnValue = ''; } });
    if (buildingSelect.options.length === 2) { buildingSelect.selectedIndex = 1; refresh(buildingSelect); buildingSelect.dispatchEvent(new Event('change')); }
})();
