(() => {
    'use strict';
    const root = document.querySelector('[data-period-page]');
    if (!root) return;
    const $ = key => root.querySelector(`[data-period-${key}]`);
    const config = window.BuildinoPeriodConfig;
    const buildings = new Map(config.buildings.map(row => [String(row.id), row]));
    const form = $('form'), buildingSelect = $('building');
    const request = (...args) => window.BuildinoCoreFlows.request(...args);
    const labels = { draft: 'پیش‌نویس', calculating: 'در حال محاسبه', calculated: 'محاسبه‌شده', issued: 'صادرشده', closed: 'بسته‌شده', cancelled: 'لغوشده' };
    const number = value => value === undefined || value === null ? '—' : Number(value).toLocaleString('fa-IR');
    const date = value => /^\d{4}-\d{2}-\d{2}$/.test(value || '') ? new Date(`${value}T12:00:00`).toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }) : 'تعیین نشده';
    const node = (tag, cls, text) => { const el = document.createElement(tag); el.className = cls || ''; if (text !== undefined) el.textContent = text; return el; };
    const building = () => buildings.get(buildingSelect.value);
    const field = key => form.elements.namedItem(key);
    const visibleField = key => { const el = field(key); return el.type === 'hidden' ? el.previousElementSibling : el; };
    const value = key => field(key).value.trim();
    const refresh = select => window.BuildinoSelect2?.refresh(select);
    let records = [], selected = null, detailId = null, editingId = null, returnView = 'directory', action = null;
    let page = 1, lastPage = 1, total = 0, listTicket = 0, detailTicket = 0, busy = false, loadingList = false, loadingDetail = false, dirty = false;
    const message = (kind, text = '') => {
        for (const key of ['error', 'success']) { $(key).hidden = kind !== key || !text; $(key).textContent = kind === key ? text : ''; }
    };
    const translations = {
        'This charge period overlaps an existing period.': 'بازه این دوره با دوره دیگری در همین ساختمان هم‌پوشانی دارد. تاریخ‌ها را اصلاح کنید.',
        'Only a draft charge period can be edited.': 'فقط دوره پیش‌نویس قابل ویرایش است. وضعیت دوره را به‌روزرسانی کنید.',
        'Only draft or calculated charge periods can be recalculated.': 'محاسبه فقط برای دوره پیش‌نویس یا محاسبه‌شده امکان‌پذیر است.',
        'Charge period must be calculated before issuance.': 'قبل از صدور صورتحساب، محاسبه شارژ دوره را انجام دهید.',
        'No active unit exists for this building.': 'در این ساختمان واحد فعالی برای محاسبه شارژ وجود ندارد.',
    };
    const translate = text => translations[text] || text;
    const clearFieldErrors = () => {
        root.querySelectorAll('[data-period-field-error]').forEach(el => { el.hidden = true; el.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
    };
    const showFieldError = (key, text) => {
        const el = visibleField(key), feedback = root.querySelector(`[data-period-field-error="${key}"]`);
        if (feedback) { feedback.textContent = text; feedback.hidden = false; }
        el.setAttribute('aria-invalid', 'true');
    };
    const report = (error, inForm = false) => {
        const errors = Object.entries(error.errors || {}), lines = errors.flatMap(([, texts]) => Array.isArray(texts) ? texts : [texts]).map(translate);
        message('error', lines.join('؛ ') || (error.status ? translate(error.message) : 'ارتباط با سرور برقرار نشد. اگر عملیات ثبت یا اجرا کرده‌اید، پیش از تکرار، وضعیت دوره را به‌روزرسانی کنید.'));
        let first;
        if (inForm) for (const [key, texts] of errors) if (field(key)) { showFieldError(key, translate(Array.isArray(texts) ? texts[0] : texts)); first ||= visibleField(key); }
        (first || $('error')).focus();
    };
    const canCalculate = () => Boolean(selected && building()?.canCalculate && ['draft', 'calculated'].includes(selected.status));
    const canIssue = () => Boolean(selected?.status === 'calculated' && building()?.canIssue);
    const sync = () => {
        root.setAttribute('aria-busy', String(busy));
        root.querySelectorAll('button').forEach(el => el.disabled = busy);
        buildingSelect.disabled = busy; refresh(buildingSelect);
        $('fields').disabled = busy;
        $('new').hidden = !building()?.canCreate;
        $('formulas').hidden = !building()?.canViewFormulas;
        $('directory').setAttribute('aria-busy', String(loadingList));
        $('prev').disabled = busy || loadingList || page <= 1;
        $('next').disabled = busy || loadingList || page >= lastPage;
        $('edit').hidden = !(selected?.status === 'draft' && building()?.canUpdate);
        $('calculate').hidden = !canCalculate();
        $('calculate').textContent = selected?.status === 'calculated' ? 'محاسبه مجدد شارژ' : 'محاسبه شارژ';
        $('calculate').classList.toggle('ownership-button--primary', selected?.status !== 'calculated');
        $('issue').hidden = !canIssue();
        $('invoice-link').hidden = !building()?.canViewInvoices || !['issued', 'closed'].includes(selected?.status);
        $('detail').setAttribute('aria-busy', String(loadingDetail || busy));
        $('save').textContent = busy ? 'در حال ذخیره…' : editingId ? 'ذخیره تغییرات دوره' : 'ذخیره دوره و ادامه';
        $('action-submit').textContent = busy ? 'در حال اجرا…' : action === 'issue' ? 'تأیید و صدور صورتحساب‌ها' : 'تأیید و محاسبه';
    };
    const showView = (name, focus = true) => {
        window.jalaliDatepicker?.hide();
        for (const key of ['location', 'directory', 'editor', 'detail']) $(key).hidden = key !== name;
        $('resume').hidden = !building();
        if (focus) root.querySelector(`#period-${name}-title`)?.focus();
    };
    const badge = (el, status) => { el.dataset.state = status; el.textContent = labels[status] || 'وضعیت نامشخص'; };
    const renderList = () => {
        $('list').replaceChildren();
        const search = $('search').value.trim(), status = $('filter').value;
        const filtered = records.filter(row => (!search || row.title.includes(search)) && (!status || row.status === status));
        $('list-status').textContent = records.length ? `${number(filtered.length)} دوره در این صفحه · ${number(total)} دوره در ساختمان` : 'هنوز دوره‌ای در این صفحه وجود ندارد.';
        if (!filtered.length) $('list').append(node('p', 'ownership-empty', records.length ? 'دوره‌ای مطابق این جست‌وجو در صفحه فعلی پیدا نشد. فیلتر را تغییر دهید یا صفحات دیگر را ببینید.' : building()?.canCreate ? 'برای شروع، یک دوره شارژ جدید تعریف کنید.' : 'دوره‌ای برای نمایش ثبت نشده است.'));
        for (const row of filtered) {
            const item = node('article', 'period-record'), copy = node('div'), actions = node('div', 'period-record__actions');
            copy.append(node('h4', '', row.title), node('p', '', `${date(row.period_start)} تا ${date(row.period_end)}`), node('p', '', `سررسید پرداخت: ${date(row.due_date)}`));
            const state = node('span', 'period-badge'); badge(state, row.status);
            const open = node('button', 'ownership-button', 'مشاهده و ادامه'); open.type = 'button';
            open.addEventListener('click', () => { if (!busy) openDetail(row.id); }); open.disabled = busy;
            actions.append(state, open); item.append(copy, actions); $('list').append(item);
        }
        $('pagination').textContent = `صفحه ${number(page)} از ${number(lastPage)}`;
    };
    async function loadList(target = page) {
        if (!building()) return;
        const ticket = ++listTicket; loadingList = true; records = []; $('list').replaceChildren();
        $('pagination').textContent = ''; $('list-status').textContent = 'در حال دریافت دوره‌های شارژ…'; sync();
        try {
            const payload = await request(`/api/v1/buildings/${buildingSelect.value}/charge-periods?page=${target}`);
            if (ticket !== listTicket) return;
            records = payload.data; page = payload.meta?.current_page || 1; lastPage = payload.meta?.last_page || 1; total = payload.meta?.total ?? records.length;
            renderList();
        } catch (error) { if (ticket === listTicket) { $('list-status').textContent = 'دریافت دوره‌ها انجام نشد؛ به‌روزرسانی را بزنید.'; report(error); } }
        finally { if (ticket === listTicket) { loadingList = false; sync(); } }
    }
    const renderDetail = () => {
        if (!selected) return;
        $('detail-title').textContent = selected.title;
        $('detail-context').textContent = building()?.title || '';
        badge($('status'), selected.status);
        $('start').textContent = date(selected.period_start); $('end').textContent = date(selected.period_end); $('due').textContent = date(selected.due_date);
        $('calculations').textContent = number(selected.calculations_count);
        $('allocations').textContent = number(selected.expense_allocations_count);
        $('invoices').textContent = number(selected.invoices_count);
        const finished = ['issued', 'closed'].includes(selected.status), current = selected.status === 'calculated' || finished ? 2 : 1;
        root.querySelectorAll('[data-period-stage]').forEach(el => {
            const index = Number(el.dataset.periodStage), cancelled = selected.status === 'cancelled';
            el.classList.toggle('is-current', !finished && !cancelled && index === current);
            el.classList.toggle('is-complete', !cancelled && (finished || index < current));
            if (!finished && !cancelled && index === current) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
        });
        const guidance = {
            draft: ['آماده محاسبه شارژ', 'محاسبه بر اساس فرمول‌های فعال ساختمان و هزینه‌های ثبت‌شده در بازه دوره انجام می‌شود. در این مرحله هنوز صورتحساب صادر نمی‌شود.'],
            calculating: ['محاسبه در حال انجام است', 'برای مشاهده نتیجه، وضعیت دوره را دوباره دریافت کنید. تا مشخص‌شدن نتیجه، عملیات را تکرار نکنید.'],
            calculated: ['محاسبه انجام شده؛ نوبت صدور است', 'تعداد محاسبات و سهم‌های هزینه در بالا نمایش داده شده است. با صدور، صورتحساب واحدها ایجاد می‌شود. محاسبه مجدد، نتیجه محاسبه قبلی را جایگزین می‌کند.'],
            issued: ['صورتحساب‌های دوره صادر شده‌اند', 'این دوره دیگر قابل ویرایش یا محاسبه مجدد نیست. مبالغ و جزئیات را در بخش صورتحساب‌ها بررسی کنید.'],
            closed: ['دوره بسته شده است', 'اطلاعات این دوره برای مشاهده سوابق در دسترس است.'],
            cancelled: ['دوره لغو شده است', 'برای این وضعیت، عملیات محاسبه یا صدور در دسترس نیست.'],
        }[selected.status] || ['وضعیت دوره نیاز به بررسی دارد', 'برای دریافت آخرین وضعیت، اطلاعات دوره را به‌روزرسانی کنید.'];
        $('next-title').textContent = guidance[0];
        $('next-hint').textContent = guidance[1] + (selected.status === 'draft' && !building()?.canCalculate ? ' محاسبه باید توسط کاربری با مجوز محاسبه انجام شود.' : selected.status === 'calculated' && !building()?.canIssue ? ' صدور باید توسط کاربری با مجوز صدور انجام شود.' : '');
        $('detail-status').textContent = ''; $('detail-body').hidden = false; $('detail-retry').hidden = true;
        action = null; $('confirm').hidden = true; $('detail-actions').hidden = false; sync();
    };
    async function openDetail(id, keepMessage = false) {
        const ticket = ++detailTicket; detailId = id; selected = null; loadingDetail = true; action = null;
        if (!keepMessage) message('', '');
        $('detail-title').textContent = 'جزئیات دوره'; $('detail-context').textContent = building()?.title || '';
        $('detail-body').hidden = true; $('detail-retry').hidden = true; $('detail-status').textContent = 'در حال دریافت آخرین وضعیت دوره…';
        showView('detail'); sync();
        try {
            const payload = await request(`/api/v1/charge-periods/${id}`);
            if (ticket !== detailTicket) return;
            selected = payload.data; renderDetail();
        } catch (error) { if (ticket === detailTicket) { $('detail-status').textContent = 'اطلاعات دوره دریافت نشد.'; $('detail-retry').hidden = false; report(error); } }
        finally { if (ticket === detailTicket) { loadingDetail = false; sync(); } }
    }
    const setDate = (key, dateValue) => {
        const el = visibleField(key);
        el.value = field(key).type === 'hidden' ? window.BuildinoJalaliDatepicker.serverToJalali(dateValue, 'date') : dateValue || '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const openEditor = record => {
        if (busy || (record ? !building()?.canUpdate || record.status !== 'draft' : !building()?.canCreate)) return;
        ++detailTicket; loadingDetail = false; editingId = record?.id || null; returnView = record ? 'detail' : 'directory';
        form.reset(); window.BuildinoJalaliDatepicker?.enhance(form); clearFieldErrors(); message('', '');
        field('title').value = record?.title || '';
        for (const key of ['period_start', 'period_end', 'due_date']) setDate(key, record?.[key] || '');
        dirty = false; $('editor-title').textContent = record ? 'ویرایش دوره پیش‌نویس' : 'تعریف دوره جدید';
        $('editor-context').textContent = building()?.title || ''; showView('editor'); sync(); field('title').focus();
    };
    const validForm = () => {
        clearFieldErrors();
        let first;
        for (const key of ['title', 'period_start', 'period_end', 'due_date']) {
            const el = visibleField(key);
            if (!value(key) || !el.checkValidity()) { showFieldError(key, key === 'title' ? 'عنوان دوره را وارد کنید (حداکثر ۲۵۵ حرف).' : 'تاریخ معتبر را از تقویم انتخاب کنید.'); first ||= el; }
        }
        if (!first && value('period_end') < value('period_start')) { showFieldError('period_end', 'پایان دوره نباید قبل از شروع آن باشد.'); first = visibleField('period_end'); }
        if (!first && value('due_date') < value('period_end')) { showFieldError('due_date', 'سررسید پرداخت باید هم‌زمان با پایان دوره یا پس از آن باشد.'); first = visibleField('due_date'); }
        first?.focus(); return !first;
    };
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !(editingId ? building()?.canUpdate && selected?.status === 'draft' : building()?.canCreate) || !validForm()) return;
        window.jalaliDatepicker?.hide();
        busy = true; sync(); message('', '');
        try {
            const payload = await request(editingId ? `/api/v1/charge-periods/${editingId}` : `/api/v1/buildings/${buildingSelect.value}/charge-periods`, {
                method: editingId ? 'PATCH' : 'POST', body: JSON.stringify(Object.fromEntries(['title', 'period_start', 'period_end', 'due_date'].map(key => [key, value(key)]))),
            });
            dirty = false; message('success', editingId ? 'تغییرات دوره ذخیره شد.' : 'دوره به‌صورت پیش‌نویس ثبت شد؛ اکنون می‌توانید محاسبه شارژ را انجام دهید.');
            await openDetail(payload.data.id, true);
        } catch (error) { report(error, true); }
        finally { busy = false; sync(); }
    });
    const confirmAction = kind => {
        if (busy || loadingDetail || (kind === 'calculate' ? !canCalculate() : !canIssue())) return;
        action = kind; message('', ''); $('confirm').hidden = false; $('detail-actions').hidden = true;
        $('confirm-title').textContent = kind === 'issue' ? 'صورتحساب‌های این دوره صادر شوند؟' : selected.status === 'calculated' ? 'محاسبه قبلی جایگزین شود؟' : 'شارژ این دوره محاسبه شود؟';
        $('confirm-copy').textContent = `دوره «${selected.title}» · ${date(selected.period_start)} تا ${date(selected.period_end)}. ` + (kind === 'issue' ? 'این اقدام صورتحساب واحدها را ایجاد می‌کند و دوره دیگر قابل ویرایش نخواهد بود.' : 'فرمول‌های فعال و هزینه‌های ثبت‌شده ساختمان مبنای محاسبه‌اند. این اقدام به‌تنهایی صورتحساب صادر نمی‌کند.');
        sync(); $('confirm-title').focus();
    };
    $('action-submit').addEventListener('click', async () => {
        if (busy || !action || (action === 'calculate' ? !canCalculate() : !canIssue())) return;
        const kind = action, id = selected.id; busy = true; sync();
        try {
            const payload = await request(`/api/v1/charge-periods/${id}/${kind}`, { method: 'POST' });
            selected = payload.data; renderDetail(); message('success', kind === 'issue' ? 'عملیات صدور دوره انجام شد؛ تعداد صورتحساب‌های ثبت‌شده در بالا نمایش داده می‌شود.' : 'محاسبه شارژ دوره انجام شد. نتیجه را بررسی و در صورت آماده‌بودن، صورتحساب‌ها را صادر کنید.');
        } catch (error) { report(error); await openDetail(id, true); }
        finally { busy = false; sync(); }
    });
    $('action-cancel').addEventListener('click', () => { if (!busy) { const previous = action; action = null; $('confirm').hidden = true; $('detail-actions').hidden = false; $(previous === 'issue' ? 'issue' : 'calculate').focus(); } });
    $('calculate').addEventListener('click', () => confirmAction('calculate'));
    $('issue').addEventListener('click', () => confirmAction('issue'));
    $('edit').addEventListener('click', () => { if (selected) openEditor(selected); });
    $('new').addEventListener('click', () => openEditor(null));
    $('cancel').addEventListener('click', () => {
        if (busy || dirty && !window.confirm('تغییرات ذخیره‌نشده کنار گذاشته شوند؟')) return;
        dirty = false; message('', ''); showView(returnView); if (returnView === 'directory') loadList();
    });
    $('close').addEventListener('click', () => { if (!busy) { ++detailTicket; loadingDetail = false; message('', ''); showView('directory'); loadList(); } });
    $('detail-retry').addEventListener('click', () => { if (!busy && detailId) openDetail(detailId); });
    $('detail-reload').addEventListener('click', () => { if (!busy && detailId) openDetail(detailId); });
    $('reload').addEventListener('click', () => { if (!busy) { message('', ''); loadList(); } });
    $('prev').addEventListener('click', () => { if (!busy && !loadingList && page > 1) loadList(page - 1); });
    $('next').addEventListener('click', () => { if (!busy && !loadingList && page < lastPage) loadList(page + 1); });
    $('search').addEventListener('input', () => { if (!loadingList) renderList(); });
    $('filter').addEventListener('change', () => { if (!loadingList) renderList(); });
    $('change-building').addEventListener('click', () => { if (!busy) { ++listTicket; loadingList = false; message('', ''); showView('location'); sync(); } });
    $('resume').addEventListener('click', () => { if (!busy && building()) { showView('directory'); loadList(); } });
    buildingSelect.addEventListener('change', () => {
        if (busy) return;
        ++listTicket; ++detailTicket; selected = null; records = []; loadingDetail = false; loadingList = false; page = 1; dirty = false;
        $('search').value = ''; $('filter').value = ''; refresh($('filter')); message('', '');
        $('building-label').textContent = building()?.title || ''; showView(building() ? 'directory' : 'location'); sync();
        if (building()) loadList(1);
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    const initial = String(config.initialBuildingId || '');
    if (buildings.has(initial)) buildingSelect.value = initial;
    else if (buildingSelect.options.length === 2) buildingSelect.selectedIndex = 1;
    if (buildingSelect.value) { refresh(buildingSelect); buildingSelect.dispatchEvent(new Event('change')); }
    else sync();
})();
