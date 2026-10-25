(() => {
    'use strict';

    const root = document.querySelector('[data-unit-ownership-page]');
    if (!root) return;

    const config = window.BuildinoOwnershipConfig || {};
    const buildings = new Map((config.buildings || []).map((item) => [String(item.id), item]));
    const $ = (name) => root.querySelector(`[data-ownership-${name}]`);
    const fields = {
        building: $('building'), block: $('block'), floor: $('floor'), unit: $('unit'),
    };
    if (!fields.building) return;

    const form = $('form');
    const endForm = $('end-form');
    const userSelect = $('user');
    const userSearch = $('user-search');
    const percentField = form.elements.namedItem('ownership_percentage');
    const primaryField = form.elements.namedItem('is_primary');
    const notesField = form.elements.namedItem('notes');
    let records = [];
    let editing = null;
    let ending = null;
    let generation = 0;
    let userGeneration = 0;
    let searchTimer;
    let busy = false;
    let endTrigger = null;
    let currentStep = 'unit';
    let recordsLoading = false;

    const today = () => {
        const date = new Date();
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    };
    const number = (value) => new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(value);
    const readDate = (parent, name) => parent.elements.namedItem(name)?.value || '';
    const setDate = (parent, name, value) => {
        const input = parent.elements.namedItem(name);
        if (!input) return;
        if (input.dataset.buildinoGregorianValue === 'date') {
            const visible = input.previousElementSibling;
            visible.value = window.BuildinoJalaliDatepicker.serverToJalali(value, 'date');
            visible.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            input.value = value;
        }
    };
    const safeId = (value) => /^[1-9]\d*$/.test(String(value || '')) ? Number(value) : null;
    const make = (tag, className, value) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (value !== undefined) node.textContent = value;
        return node;
    };
    const message = (type, value) => {
        for (const key of ['error', 'success']) {
            const node = $(key);
            node.hidden = key !== type || !value;
            node.textContent = key === type ? value || '' : '';
        }
        if (type === 'error' && value) $('error').scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    const report = (error) => {
        const details = Object.values(error.errors || {}).flat().join('؛ ');
        message('error', details || error.message || 'درخواست انجام نشد. دوباره تلاش کنید.');
        const name = Object.keys(error.errors || {})[0];
        if (name === 'notes' || name === 'ends_at') $('more').open = true;
        const input = form.elements.namedItem(name);
        if (input && !$('editor').hidden) input.focus();
    };
    const request = async (url, options = {}) => {
        const endpoint = new URL(url, window.location.origin);
        if (endpoint.origin !== window.location.origin) throw new Error('آدرس درخواست معتبر نیست.');
        let response;
        try {
            response = await fetch(endpoint, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken || '',
                    ...options.headers,
                },
            });
        } catch {
            throw new Error('ارتباط با سرور برقرار نشد. پیش از تکرار ثبت، وضعیت واحد را به‌روزرسانی کنید.');
        }
        const payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        if (!response.ok) {
            const error = new Error(response.status === 401 || response.status === 419
                ? 'نشست شما پایان یافته است؛ دوباره وارد شوید.'
                : response.status === 403 ? 'این عملیات برای ساختمان انتخاب‌شده مجاز نیست.'
                    : response.status === 429 ? 'درخواست‌های زیادی ارسال شده است؛ کمی بعد دوباره تلاش کنید.'
                        : payload?.message || 'درخواست انجام نشد.');
            error.errors = payload?.errors || {};
            throw error;
        }
        return payload;
    };
    const lookup = async (type, key, value) => {
        const url = new URL(`${config.lookupBase}/${type}`, window.location.origin);
        if (key && value) url.searchParams.set(key, value);
        return (await request(url)).data || [];
    };
    const resetSelect = (select, text) => {
        select.replaceChildren(new Option(text, ''));
        select.disabled = true;
    };
    const setOptions = (select, items, text, preferred) => {
        select.replaceChildren(new Option(text, ''));
        for (const item of items) select.add(new Option(item.label, String(item.id)));
        select.disabled = items.length === 0;
        const preferredId = safeId(preferred);
        if (preferredId && items.some((item) => Number(item.id) === preferredId)) select.value = String(preferredId);
        else if (items.length === 1) select.value = String(items[0].id);
        return select.value;
    };
    const building = () => buildings.get(fields.building.value);
    const unitId = () => safeId(fields.unit.value);
    const updateFlow = () => {
        const steps = ['unit', 'records', 'change'];
        for (const [index, step] of steps.entries()) {
            const marker = root.querySelector(`[data-ownership-flow="${step}"]`);
            marker.className = step === currentStep ? 'is-current' : index < steps.indexOf(currentStep) ? 'is-complete' : '';
            if (step === currentStep) marker.setAttribute('aria-current', 'step'); else marker.removeAttribute('aria-current');
        }
    };
    const showStep = (step, focus = false) => {
        currentStep = step;
        $('location').hidden = step !== 'unit';
        $('records').hidden = step !== 'records';
        $('editor').hidden = step !== 'change';
        $('continue').hidden = !unitId();
        updateFlow();
        if (focus) document.getElementById(step === 'unit' ? 'ownership-unit-heading' : step === 'records' ? 'ownership-records-heading' : 'ownership-editor-heading').focus();
    };
    const hideRecords = () => {
        records = [];
        editing = null;
        ++userGeneration;
        clearTimeout(searchTimer);
        showStep('unit');
        const url = new URL(window.location.href);
        url.searchParams.delete('unit_id');
        window.history.replaceState(null, '', url);
    };
    const setHint = (value) => { $('location-hint').textContent = value; };
    const loadBlocks = async (preferred = null) => {
        const ticket = ++generation;
        hideRecords();
        resetSelect(fields.block, 'در حال دریافت بلوک‌ها…');
        resetSelect(fields.floor, 'ابتدا بلوک را انتخاب کنید');
        resetSelect(fields.unit, 'ابتدا طبقه را انتخاب کنید');
        if (!fields.building.value) { resetSelect(fields.block, 'ابتدا ساختمان را انتخاب کنید'); setHint('برای شروع، ساختمان را انتخاب کنید.'); return; }
        setHint('در حال دریافت بلوک‌های ساختمان…');
        try {
            const items = await lookup('blocks', 'building_id', fields.building.value);
            if (ticket !== generation) return;
            const value = setOptions(fields.block, items, 'بلوک را انتخاب کنید', preferred?.blockId);
            setHint(items.length ? 'بلوک و طبقه را انتخاب کنید.' : 'برای این ساختمان هنوز بلوکی ثبت نشده است.');
            if (value) await loadFloors(preferred);
        } catch (error) { if (ticket === generation) report(error); }
    };
    const loadFloors = async (preferred = null) => {
        const ticket = ++generation;
        hideRecords();
        resetSelect(fields.floor, 'در حال دریافت طبقات…');
        resetSelect(fields.unit, 'ابتدا طبقه را انتخاب کنید');
        if (!fields.block.value) { resetSelect(fields.floor, 'ابتدا بلوک را انتخاب کنید'); return; }
        try {
            const items = await lookup('floors', 'block_id', fields.block.value);
            if (ticket !== generation) return;
            const value = setOptions(fields.floor, items, 'طبقه را انتخاب کنید', preferred?.floorId);
            setHint(items.length ? 'طبقه و واحد را انتخاب کنید.' : 'برای این بلوک هنوز طبقه‌ای ثبت نشده است.');
            if (value) await loadUnits(preferred);
        } catch (error) { if (ticket === generation) report(error); }
    };
    const loadUnits = async (preferred = null) => {
        const ticket = ++generation;
        hideRecords();
        resetSelect(fields.unit, 'در حال دریافت واحدها…');
        if (!fields.floor.value) { resetSelect(fields.unit, 'ابتدا طبقه را انتخاب کنید'); return; }
        try {
            const items = await lookup('units', 'floor_id', fields.floor.value);
            if (ticket !== generation) return;
            const value = setOptions(fields.unit, items, 'واحد را انتخاب کنید', preferred?.unitId);
            setHint(items.length ? 'واحد مورد نظر را انتخاب کنید.' : 'برای این طبقه هنوز واحدی ثبت نشده است.');
            if (value) await loadRecords();
        } catch (error) { if (ticket === generation) report(error); }
    };
    const ownerName = (record) => [record.user?.first_name, record.user?.last_name].filter(Boolean).join(' ') || `کاربر #${record.user_id}`;
    const recordCard = (record, active) => {
        const card = make('article', 'ownership-record');
        const main = make('div', 'ownership-record__main');
        main.append(make('strong', '', ownerName(record)), make('small', '', record.user?.mobile || record.user?.email || 'اطلاعات تماس ثبت نشده'));
        const meta = make('div', 'ownership-record__meta');
        meta.append(make('span', '', record.ownership_percentage === null ? 'سهم نامشخص' : `${number(record.ownership_percentage)}٪ سهم`));
        if (record.is_primary && active) meta.append(make('span', 'is-primary', 'مالک اصلی'));
        meta.append(make('span', '', `از ${record.starts_at || 'نامشخص'}${record.ends_at ? ` تا ${record.ends_at}` : ''}`));
        if (!active) meta.append(make('span', '', 'پایان‌یافته'));
        card.append(main, meta);
        if (active && building()?.canUpdate) {
            const actions = make('div', 'ownership-record__actions');
            const edit = make('button', 'ownership-button ownership-button--soft', 'ویرایش');
            edit.type = 'button'; edit.addEventListener('click', () => openEditor(record));
            const end = make('button', 'ownership-button ownership-button--soft', 'پایان مالکیت');
            end.type = 'button'; end.addEventListener('click', () => openEnd(record));
            actions.append(edit, end); card.append(actions);
        }
        return card;
    };
    const renderRecords = () => {
        const currentDate = today();
        const active = records.filter((item) => item.is_active && (!item.ends_at || item.ends_at >= currentDate));
        const history = records.filter((item) => !active.includes(item));
        const total = active.reduce((sum, item) => sum + (Number(item.ownership_percentage) || 0), 0);
        const unspecified = active.some((item) => item.ownership_percentage === null);
        const overview = $('overview');
        overview.replaceChildren(
            make('strong', '', `${number(active.length)} مالکیت در جریان`),
            make('span', total > 100 ? 'ownership-overview__warning' : '', `${number(total)}٪ سهم مشخص`),
        );
        if (unspecified) overview.append(make('span', 'ownership-overview__warning', 'درصد بعضی مالکیت‌ها مشخص نیست'));
        const activeHost = $('active'); activeHost.replaceChildren();
        if (active.length) for (const item of active) activeHost.append(recordCard(item, true));
        else activeHost.append(make('p', 'ownership-empty', 'هنوز مالکیت فعالی برای این واحد ثبت نشده است.'));
        const historyHost = $('history'); historyHost.replaceChildren();
        for (const item of history) historyHost.append(recordCard(item, false));
        $('history-wrap').hidden = history.length === 0;
        $('history-count').textContent = `(${number(history.length)})`;
        $('actions').hidden = !building()?.canCreate;
        $('create-user').hidden = !building()?.canCreateUser;
        showStep('records', true);
    };
    const loadRecords = async () => {
        const selectedId = unitId();
        const ticket = ++generation;
        hideRecords();
        if (!selectedId) return;
        recordsLoading = true;
        $('records').setAttribute('aria-busy', 'true');
        $('reload').disabled = true;
        $('overview').replaceChildren();
        $('active').replaceChildren(make('p', 'ownership-empty', 'در حال دریافت مالکیت‌های واحد…'));
        $('history-wrap').hidden = true;
        $('actions').hidden = true;
        $('unit-label').textContent = `${fields.unit.selectedOptions[0]?.textContent || 'واحد'} · ${fields.building.selectedOptions[0]?.textContent || ''}`;
        showStep('records', true);
        setHint('در حال دریافت مالکیت‌های واحد…');
        try {
            const rows = [];
            let page = 1;
            let lastPage = 1;
            do {
                const url = new URL(`${config.apiBase}/units/${selectedId}/ownerships`, window.location.origin);
                url.searchParams.set('per_page', '100');
                url.searchParams.set('page', String(page));
                const payload = await request(url);
                if (ticket !== generation) return;
                rows.push(...(payload.data || []));
                lastPage = Number(payload.meta?.last_page || 1);
                page += 1;
            } while (page <= lastPage);
            if (ticket !== generation) return;
            records = rows;
            $('unit-label').textContent = `${fields.unit.selectedOptions[0]?.textContent || 'واحد'} · ${fields.building.selectedOptions[0]?.textContent || ''}`;
            const url = new URL(window.location.href); url.searchParams.set('unit_id', String(selectedId));
            window.history.replaceState(null, '', url);
            setHint('واحد انتخاب شد. برای ادامه، نمایش مالکیت‌ها را بزنید.');
            renderRecords();
        } catch (error) {
            if (ticket === generation) { $('active').replaceChildren(make('p', 'ownership-empty', 'دریافت مالکیت‌ها انجام نشد؛ به‌روزرسانی را بزنید یا واحد را تغییر دهید.')); report(error); }
        } finally {
            if (ticket === generation) { recordsLoading = false; $('records').setAttribute('aria-busy', 'false'); $('reload').disabled = false; }
        }
    };
    const shareHint = () => {
        const other = records.filter((item) => item.id !== editing?.id && item.is_active && (!item.ends_at || item.ends_at >= today()));
        const known = other.reduce((sum, item) => sum + (Number(item.ownership_percentage) || 0), 0);
        $('share-hint').textContent = other.some((item) => item.ownership_percentage === null)
            ? 'درصد مالکیت برخی مالکان مشخص نشده است؛ مقدار جدید پس از ثبت توسط سامانه بررسی می‌شود.'
            : `از سهم مشخص‌شدهٔ دیگر مالکان، ${number(Math.max(0, 100 - known))}٪ باقی مانده است.`;
    };
    const openEditor = (record = null) => {
        if (busy || recordsLoading || !unitId() || !(record ? building()?.canUpdate : building()?.canCreate)) return;
        message(null, null);
        editing = record;
        form.reset();
        $('more').open = false;
        $('editor-title').textContent = record ? 'ویرایش مالکیت' : 'ثبت مالک جدید';
        $('save').textContent = record ? 'ذخیره تغییرات' : 'ثبت مالکیت';
        $('user-picker').hidden = Boolean(record);
        $('edit-owner').hidden = !record;
        $('create-end').hidden = Boolean(record);
        userSelect.required = !record;
        userSelect.replaceChildren(new Option('ابتدا مالک را جست‌وجو کنید', ''));
        userSearch.value = '';
        $('user-hint').textContent = 'برای حسابی که هنوز به ساختمان وصل نیست، شماره موبایل ۱۱ رقمی یا ایمیل کامل او را وارد کنید.';
        $('edit-name').textContent = record ? ownerName(record) : '';
        setDate(form, 'starts_at', record?.starts_at || today());
        percentField.value = record?.ownership_percentage ?? '';
        primaryField.checked = record?.is_primary || false;
        notesField.value = record?.notes || '';
        setDate(form, 'ends_at', '');
        $('editor-unit-label').textContent = $('unit-label').textContent;
        shareHint(); showStep('change', true);
    };
    const closeEditor = () => { editing = null; ++userGeneration; clearTimeout(searchTimer); showStep('records', true); };
    const closeEndDialog = () => {
        window.jalaliDatepicker?.hide();
        $('end-dialog').close();
    };
    const openEnd = (record) => {
        ending = record;
        endTrigger = document.activeElement;
        $('end-name').textContent = ownerName(record);
        setDate(endForm, 'ends_at', today());
        $('end-backdrop').hidden = false;
        document.body.classList.add('ownership-modal-open');
        $('end-dialog').show();
        document.getElementById('ownership-end-title').focus({ preventScroll: true });
    };
    const save = async (event) => {
        event.preventDefault();
        if (busy || !unitId()) return;
        message(null, null);
        if (!form.reportValidity()) return;
        if (!editing && !safeId(userSelect.value)) { userSelect.focus(); message('error', 'یک حساب کاربری را برای مالک انتخاب کنید.'); return; }
        const percentage = percentField.value.trim();
        const payload = {
            ownership_percentage: percentage === '' ? null : Number(percentage),
            starts_at: readDate(form, 'starts_at'),
            is_primary: primaryField.checked,
            notes: notesField.value.trim() || null,
        };
        if (!editing) {
            payload.user_id = safeId(userSelect.value);
            payload.is_active = true;
            if (readDate(form, 'ends_at')) payload.ends_at = readDate(form, 'ends_at');
            if (records.some((item) => item.user_id === payload.user_id && item.is_active && (!item.ends_at || item.ends_at >= today()))) {
                message('error', 'این کاربر هم‌اکنون مالک این واحد است. برای تغییر سهم یا مشخصات، مالکیت فعلی او را ویرایش کنید.');
                return;
            }
        }
        busy = true; $('save').disabled = true;
        try {
            await request(editing ? `${config.apiBase}/unit-ownerships/${editing.id}` : `${config.apiBase}/units/${unitId()}/ownerships`, {
                method: editing ? 'PATCH' : 'POST', body: JSON.stringify(payload),
            });
            const success = editing ? 'تغییرات مالکیت ذخیره شد.' : 'مالک جدید برای این واحد ثبت شد.';
            closeEditor(); await loadRecords(); message('success', success);
        } catch (error) { report(error); }
        finally { busy = false; $('save').disabled = false; }
    };
    const end = async (event) => {
        event.preventDefault();
        if (busy || !ending || !unitId()) return;
        if (!endForm.reportValidity()) return;
        const endDate = readDate(endForm, 'ends_at');
        if (ending.starts_at && endDate < ending.starts_at) { message('error', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع مالکیت باشد.'); closeEndDialog(); return; }
        busy = true; $('end-confirm').disabled = true;
        try {
            await request(`${config.apiBase}/unit-ownerships/${ending.id}/end`, {
                method: 'POST', body: JSON.stringify({ ends_at: endDate }),
            });
            closeEndDialog();
            await loadRecords(); message('success', 'مالکیت پایان یافت و در سابقه واحد باقی ماند.');
        } catch (error) { closeEndDialog(); report(error); }
        finally { busy = false; $('end-confirm').disabled = false; }
    };
    const searchUsers = async () => {
        const query = userSearch.value.trim();
        const ticket = ++userGeneration;
        resetSelect(userSelect, query.length < 2 ? 'حداقل دو حرف یا رقم وارد کنید' : 'در حال جست‌وجو…');
        if (query.length < 2) return;
        try {
            const url = new URL(`${config.lookupBase}/ownership_candidates`, window.location.origin);
            url.searchParams.set('unit_id', String(unitId()));
            url.searchParams.set('search', query);
            const rows = (await request(url)).data || [];
            if (ticket !== userGeneration) return;
            setOptions(userSelect, rows, rows.length ? 'مالک را انتخاب کنید' : 'حسابی پیدا نشد');
            userSelect.value = '';
            $('user-hint').textContent = rows.length ? `${number(rows.length)} حساب پیدا شد.` : 'حسابی پیدا نشد؛ نام یا موبایل دیگری را جست‌وجو کنید.';
        } catch (error) { if (ticket === userGeneration) report(error); }
    };

    fields.building.addEventListener('change', () => { message(null, null); loadBlocks(); });
    fields.block.addEventListener('change', () => { message(null, null); loadFloors(); });
    fields.floor.addEventListener('change', () => { message(null, null); loadUnits(); });
    fields.unit.addEventListener('change', () => { message(null, null); loadRecords(); });
    $('reload').addEventListener('click', () => { message(null, null); loadRecords(); });
    $('add').addEventListener('click', () => openEditor());
    $('cancel').addEventListener('click', () => { if (!busy) closeEditor(); });
    $('back-unit').addEventListener('click', () => {
        if (busy) return;
        ++generation; recordsLoading = false;
        $('records').setAttribute('aria-busy', 'false'); $('reload').disabled = false;
        message(null, null); showStep('unit', true);
    });
    $('continue').addEventListener('click', () => { if (!busy && unitId()) loadRecords(); });
    $('end-cancel').addEventListener('click', closeEndDialog);
    $('end-backdrop').addEventListener('click', closeEndDialog);
    $('end-dialog').addEventListener('close', () => {
        $('end-backdrop').hidden = true;
        document.body.classList.remove('ownership-modal-open');
        ending = null;
        if (endTrigger?.isConnected) endTrigger.focus({ preventScroll: true });
        endTrigger = null;
    });
    document.addEventListener('keydown', (event) => {
        const dialog = $('end-dialog');
        if (!dialog.open) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeEndDialog();
        } else if (event.key === 'Tab') {
            const focusable = [...dialog.querySelectorAll('input:not([type="hidden"]), button')].filter((element) => !element.disabled);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                event.preventDefault(); last?.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                event.preventDefault(); first?.focus();
            }
        }
    });
    form.addEventListener('submit', save);
    endForm.addEventListener('submit', end);
    userSearch.addEventListener('input', () => {
        userSelect.value = '';
        clearTimeout(searchTimer);
        searchTimer = setTimeout(searchUsers, 280);
    });

    const initial = config.initialUnit;
    fields.building.value = String(initial?.buildingId || (buildings.size === 1 ? [...buildings.keys()][0] : ''));
    window.BuildinoSelect2?.refresh(fields.building);
    if (fields.building.value) loadBlocks(initial);
    else showStep('unit');
})();
