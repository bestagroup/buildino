(() => {
    'use strict';
    const root = document.querySelector('[data-unit-occupancy-page]');
    if (!root) return;
    const config = window.BuildinoOccupancyConfig || {};
    const buildings = new Map((config.buildings || []).map(item => [String(item.id), item]));
    const $ = name => root.querySelector(`[data-occupancy-${name}]`);
    const fields = { building: $('building'), block: $('block'), floor: $('floor'), unit: $('unit') };
    if (!fields.building) return;
    const form = $('form'), endForm = $('end-form'), userSelect = $('user'), userSearch = $('user-search');
    let records = [], invitations = [], candidates = [], editing = null, ending = null;
    let generation = 0, userGeneration = 0, searchTimer, busy = false, endTrigger = null;
    let currentStep = 'unit', recordsLoading = false;
    const safeId = value => /^[1-9]\d*$/.test(String(value || '')) ? Number(value) : null;
    const unitId = () => safeId(fields.unit.value);
    const building = () => buildings.get(fields.building.value);
    const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
    const displayDate = value => value ? (window.BuildinoJalaliDatepicker?.serverToJalali(value, 'date') || value) : 'نامشخص';
    const number = value => new Intl.NumberFormat('fa-IR').format(value);
    const make = (tag, className, content) => { const node = document.createElement(tag); if (className) node.className = className; if (content !== undefined) node.textContent = content; return node; };
    const readDate = (parent, name) => parent.elements.namedItem(name)?.value || '';
    const setDate = (parent, name, value) => {
        const input = parent.elements.namedItem(name);
        if (!input) return;
        if (input.dataset.buildinoGregorianValue === 'date') {
            const visible = input.previousElementSibling;
            visible.value = window.BuildinoJalaliDatepicker.serverToJalali(value, 'date');
            visible.dispatchEvent(new Event('input', { bubbles: true }));
        } else input.value = value;
    };
    const message = (type, value) => {
        for (const kind of ['error', 'success']) { $(kind).hidden = kind !== type || !value; $(kind).textContent = kind === type ? value || '' : ''; }
        if (type === 'error' && value) $('error').scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    const report = error => {
        message('error', Object.values(error.errors || {}).flat().join('؛ ') || error.message || 'درخواست انجام نشد. دوباره تلاش کنید.');
        const name = Object.keys(error.errors || {})[0];
        if (name === 'notes' || name === 'ends_at') $('more').open = true;
        if (!$('editor').hidden) form.elements.namedItem(name)?.focus();
    };
    const request = async (url, options = {}) => {
        const endpoint = new URL(url, window.location.origin);
        if (endpoint.origin !== window.location.origin) throw new Error('آدرس درخواست معتبر نیست.');
        let response;
        try {
            response = await fetch(endpoint, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken || '', ...options.headers } });
        } catch { throw new Error('ارتباط با سرور برقرار نشد. پیش از تکرار عملیات، فهرست را به‌روزرسانی کنید.'); }
        const payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        if (!response.ok) {
            const error = new Error(response.status === 401 || response.status === 419 ? 'نشست شما پایان یافته است؛ دوباره وارد شوید.' : response.status === 403 ? 'این عملیات برای ساختمان انتخاب‌شده مجاز نیست.' : response.status === 429 ? 'درخواست‌های زیادی ارسال شده است؛ کمی بعد دوباره تلاش کنید.' : payload?.message || 'درخواست انجام نشد.');
            error.errors = payload?.errors || {}; throw error;
        }
        return payload;
    };
    const lookup = async (type, key, value) => { const url = new URL(`${config.lookupBase}/${type}`, location.origin); if (key && value) url.searchParams.set(key, value); return (await request(url)).data || []; };
    const resetSelect = (select, label) => { select.replaceChildren(new Option(label, '')); select.disabled = true; window.BuildinoSelect2?.refresh(select); };
    const setOptions = (select, rows, label, preferred) => {
        select.replaceChildren(new Option(label, ''));
        for (const row of rows) select.add(new Option(row.label, String(row.id)));
        select.disabled = !rows.length;
        if (safeId(preferred) && rows.some(row => Number(row.id) === Number(preferred))) select.value = String(preferred);
        else if (rows.length === 1) select.value = String(rows[0].id);
        window.BuildinoSelect2?.refresh(select);
        return select.value;
    };
    const flow = () => {
        const steps = ['unit', 'records', 'change'];
        for (const [index, step] of steps.entries()) {
            const marker = root.querySelector(`[data-occupancy-flow="${step}"]`);
            marker.className = step === currentStep ? 'is-current' : index < steps.indexOf(currentStep) ? 'is-complete' : '';
            if (step === currentStep) marker.setAttribute('aria-current', 'step'); else marker.removeAttribute('aria-current');
        }
    };
    const showStep = (step, focus = false) => {
        currentStep = step;
        $('location').hidden = step !== 'unit';
        $('records').hidden = step !== 'records';
        $('editor').hidden = step !== 'change';
        $('continue').hidden = !unitId(); flow();
        if (focus) document.getElementById(step === 'unit' ? 'occupancy-unit-heading' : step === 'records' ? 'occupancy-records-heading' : 'occupancy-editor-heading').focus();
    };
    const hideRecords = () => {
        records = []; invitations = []; editing = null;
        ++userGeneration; clearTimeout(searchTimer); showStep('unit');
        const url = new URL(location.href); url.searchParams.delete('unit_id'); history.replaceState(null, '', url);
    };
    const hint = value => { $('location-hint').textContent = value; };
    const loadBlocks = async (preferred = null) => {
        const ticket = ++generation; hideRecords(); resetSelect(fields.block, 'در حال دریافت بلوک‌ها…'); resetSelect(fields.floor, 'ابتدا بلوک را انتخاب کنید'); resetSelect(fields.unit, 'ابتدا طبقه را انتخاب کنید');
        if (!fields.building.value) { resetSelect(fields.block, 'ابتدا ساختمان را انتخاب کنید'); hint('برای شروع، ساختمان را انتخاب کنید.'); return; }
        hint('در حال دریافت بلوک‌های ساختمان…');
        try { const rows = await lookup('blocks', 'building_id', fields.building.value); if (ticket !== generation) return; const value = setOptions(fields.block, rows, 'بلوک را انتخاب کنید', preferred?.blockId); hint(rows.length ? 'بلوک و طبقه را انتخاب کنید.' : 'برای این ساختمان بلوکی ثبت نشده است.'); if (value) await loadFloors(preferred); }
        catch (error) { if (ticket === generation) report(error); }
    };
    const loadFloors = async (preferred = null) => {
        const ticket = ++generation; hideRecords(); resetSelect(fields.floor, 'در حال دریافت طبقات…'); resetSelect(fields.unit, 'ابتدا طبقه را انتخاب کنید');
        if (!fields.block.value) { resetSelect(fields.floor, 'ابتدا بلوک را انتخاب کنید'); return; }
        try { const rows = await lookup('floors', 'block_id', fields.block.value); if (ticket !== generation) return; const value = setOptions(fields.floor, rows, 'طبقه را انتخاب کنید', preferred?.floorId); hint(rows.length ? 'طبقه و واحد را انتخاب کنید.' : 'برای این بلوک طبقه‌ای ثبت نشده است.'); if (value) await loadUnits(preferred); }
        catch (error) { if (ticket === generation) report(error); }
    };
    const loadUnits = async (preferred = null) => {
        const ticket = ++generation; hideRecords(); resetSelect(fields.unit, 'در حال دریافت واحدها…');
        if (!fields.floor.value) { resetSelect(fields.unit, 'ابتدا طبقه را انتخاب کنید'); return; }
        try { const rows = await lookup('units', 'floor_id', fields.floor.value); if (ticket !== generation) return; const value = setOptions(fields.unit, rows, 'واحد را انتخاب کنید', preferred?.unitId); hint(rows.length ? 'واحد موردنظر را انتخاب کنید.' : 'برای این طبقه واحدی ثبت نشده است.'); if (value) await loadRecords(); }
        catch (error) { if (ticket === generation) report(error); }
    };
    const paged = async url => {
        const rows = []; let page = 1, lastPage = 1;
        do { const endpoint = new URL(url, location.origin); endpoint.searchParams.set('per_page', '100'); endpoint.searchParams.set('page', String(page)); const payload = await request(endpoint); rows.push(...(payload.data || [])); lastPage = Number(payload.meta?.last_page || 1); page++; } while (page <= lastPage);
        return rows;
    };
    const nameOf = record => [record.user?.first_name, record.user?.last_name].filter(Boolean).join(' ') || `کاربر #${record.user_id}`;
    const typeLabels = { tenant: 'مستأجر', resident: 'ساکن', family_member: 'عضو خانواده', owner: 'مالک ساکن' };
    const invitationFor = record => invitations.find(invitation => Number(invitation.invited_user_id) === Number(record.user_id));
    const invitationExpired = invitation => invitation && ['pending', 'sent'].includes(invitation.status)
        && invitation.expires_at && new Date(invitation.expires_at).getTime() <= Date.now();
    const invitationLabel = invitation => ({ pending: 'دعوت در انتظار پذیرش', sent: 'دعوت ارسال‌شده', accepted: 'دعوت پذیرفته‌شده', expired: 'دعوت منقضی‌شده', cancelled: 'دعوت لغوشده' })[invitation.status] || `وضعیت دعوت: ${invitation.status}`;
    const accountLabel = user => !user.account_active ? 'حساب غیرفعال یا مسدود' : user.mobile_verified || user.email_verified ? 'هویت تأییدشده' : 'در انتظار تأیید هویت';
    const card = (record, active) => {
        const node = make('article', 'ownership-record');
        const main = make('div', 'ownership-record__main');
        main.append(make('strong', '', nameOf(record)), make('small', '', record.user?.mobile || record.user?.email || 'اطلاعات تماس ثبت نشده'));
        const meta = make('div', 'ownership-record__meta');
        meta.append(make('span', '', typeLabels[record.occupancy_type] || record.occupancy_type));
        if (record.is_primary && active) meta.append(make('span', 'is-primary', 'ساکن اصلی'));
        meta.append(make('span', '', accountLabel(record.user || {})));
        meta.append(make('span', '', `از ${displayDate(record.starts_at)}${record.ends_at ? ` تا ${displayDate(record.ends_at)}` : ''}`));
        if (!active) meta.append(make('span', '', 'پایان‌یافته'));
        const invitation = invitationFor(record);
        if (invitation && active) meta.append(make('span', '', invitationExpired(invitation) ? 'دعوت منقضی‌شده' : invitationLabel(invitation)));
        node.append(main, meta);
        if (active) {
            const actions = make('div', 'ownership-record__actions');
            if (building()?.canUpdate) {
                const edit = make('button', 'ownership-button ownership-button--soft', 'ویرایش'); edit.type = 'button'; edit.addEventListener('click', () => openEditor(record)); actions.append(edit);
                const end = make('button', 'ownership-button ownership-button--soft', 'پایان سکونت'); end.type = 'button'; end.addEventListener('click', () => openEnd(record)); actions.append(end);
            }
            if (building()?.canInvite && record.user?.mobile && (invitationExpired(invitation) || !['pending', 'sent', 'accepted'].includes(invitation?.status))) {
                const invite = make('button', 'ownership-button ownership-button--soft', 'ارسال دعوت ورود'); invite.type = 'button'; invite.addEventListener('click', () => sendInvite(record, invite)); actions.append(invite);
            }
            if (actions.childNodes.length) node.append(actions);
        }
        return node;
    };
    const render = () => {
        const active = records.filter(record => record.is_active && (!record.ends_at || record.ends_at >= today()));
        const old = records.filter(record => !active.includes(record));
        const pending = invitations.filter(invitation => invitation.planned_occupancy && ['pending', 'sent'].includes(invitation.status) && !invitationExpired(invitation));
        $('overview').replaceChildren(make('strong', '', `${number(active.length)} ساکن فعال`));
        $('active').replaceChildren(...(active.length ? active.map(record => card(record, true)) : [make('p', 'ownership-empty', 'هنوز سکونت فعالی برای این واحد ثبت نشده است.')]));
        $('pending').replaceChildren(...pending.map(invitation => {
            const item = make('article', 'ownership-record');
            const main = make('div', 'ownership-record__main');
            main.append(make('strong', '', invitation.invited_user?.display_name || `کاربر #${invitation.invited_user_id}`), make('small', '', `${invitation.mobile || ''} · ${invitation.invited_user?.identity_verified ? 'هویت تأییدشده' : 'نیازمند تأیید هویت'}`));
            const meta = make('div', 'ownership-record__meta');
            meta.append(make('span', '', typeLabels[invitation.relation_type] || invitation.relation_type), make('span', '', invitationLabel(invitation)), make('span', '', `شروع ${displayDate(invitation.planned_occupancy.starts_at)}`));
            item.append(main, meta);
            if (building()?.canUpdateInvite) {
                const cancel = make('button', 'ownership-button ownership-button--soft', 'لغو دعوت');
                cancel.type = 'button'; cancel.addEventListener('click', async () => {
                    if (busy || !window.confirm('این دعوت لغو شود؟')) return;
                    busy = true; cancel.disabled = true;
                    try { await request(`${config.apiBase}/unit-invitations/${invitation.id}/cancel`, { method: 'POST' }); await loadRecords(); message('success', 'دعوت لغو شد.'); }
                    catch (error) { report(error); }
                    finally { busy = false; cancel.disabled = false; }
                });
                item.append(cancel);
            }
            return item;
        }));
        $('pending-wrap').hidden = !pending.length;
        $('history').replaceChildren(...old.map(record => card(record, false)));
        $('history-wrap').hidden = !old.length; $('history-count').textContent = `(${number(old.length)})`;
        $('actions').hidden = !building()?.canCreate; $('create-user').hidden = !building()?.canCreateUser;
        showStep('records', true);
    };
    const loadRecords = async () => {
        const id = unitId(), ticket = ++generation; hideRecords(); if (!id) return;
        recordsLoading = true; $('records').setAttribute('aria-busy', 'true'); $('reload').disabled = true;
        $('overview').replaceChildren();
        $('active').replaceChildren(make('p', 'ownership-empty', 'در حال دریافت ساکنان واحد…'));
        $('pending-wrap').hidden = true; $('history-wrap').hidden = true; $('actions').hidden = true;
        $('unit-label').textContent = `${fields.unit.selectedOptions[0]?.textContent || 'واحد'} · ${fields.building.selectedOptions[0]?.textContent || ''}`;
        showStep('records', true);
        hint('در حال دریافت ساکنان واحد…');
        try {
            const people = await paged(`${config.apiBase}/units/${id}/occupancies`);
            if (ticket !== generation) return;
            let invites = [];
            if (building()?.canViewInvites) invites = await paged(`${config.apiBase}/units/${id}/invitations`);
            if (ticket !== generation) return;
            records = people; invitations = invites;
            $('unit-label').textContent = `${fields.unit.selectedOptions[0]?.textContent || 'واحد'} · ${fields.building.selectedOptions[0]?.textContent || ''}`;
            const url = new URL(location.href); url.searchParams.set('unit_id', String(id)); history.replaceState(null, '', url);
            hint('واحد انتخاب شد. برای ادامه، نمایش ساکنان را بزنید.'); render();
        } catch (error) {
            if (ticket === generation) { $('active').replaceChildren(make('p', 'ownership-empty', 'دریافت ساکنان انجام نشد؛ به‌روزرسانی را بزنید یا واحد را تغییر دهید.')); report(error); }
        } finally {
            if (ticket === generation) { recordsLoading = false; $('records').setAttribute('aria-busy', 'false'); $('reload').disabled = false; }
        }
    };
    const openEditor = (record = null) => {
        if (busy || recordsLoading || !unitId() || !(record ? building()?.canUpdate : building()?.canCreate)) return;
        message(null, null); editing = record; form.reset(); $('more').open = false;
        $('editor-title').textContent = record ? 'ویرایش سکونت' : 'ثبت ساکن جدید'; $('save').textContent = record ? 'ذخیره تغییرات' : 'ثبت سکونت';
        $('user-picker').hidden = Boolean(record); $('edit-person').hidden = !record; $('create-end').hidden = Boolean(record);
        $('invite-wrap').hidden = Boolean(record) || !building()?.canInvite;
        $('owner-option').hidden = !record || record.occupancy_type !== 'owner';
        userSelect.required = !record; resetSelect(userSelect, 'ابتدا شخص را جست‌وجو کنید'); userSearch.value = ''; candidates = [];
        $('user-hint').textContent = 'برای شخصی خارج از ساختمان، شماره موبایل ۱۱ رقمی یا ایمیل کامل را جست‌وجو کنید.';
        $('account-status').textContent = ''; $('edit-name').textContent = record ? nameOf(record) : '';
        form.elements.namedItem('occupancy_type').value = record?.occupancy_type || '';
        setDate(form, 'starts_at', record?.starts_at || today()); setDate(form, 'ends_at', '');
        form.elements.namedItem('is_primary').checked = Boolean(record?.is_primary);
        form.elements.namedItem('notes').value = record?.notes || '';
        $('editor-unit-label').textContent = $('unit-label').textContent;
        showStep('change', true);
    };
    const closeEditor = () => { editing = null; ++userGeneration; clearTimeout(searchTimer); showStep('records', true); };
    const searchUsers = async () => {
        const query = userSearch.value.trim(), ticket = ++userGeneration;
        resetSelect(userSelect, query.length < 2 ? 'حداقل دو حرف یا رقم وارد کنید' : 'در حال جست‌وجو…');
        $('account-status').textContent = '';
        if (query.length < 2 || !unitId()) return;
        try {
            const url = new URL(`${config.lookupBase}/occupancy_candidates`, location.origin); url.searchParams.set('unit_id', String(unitId())); url.searchParams.set('search', query);
            const rows = (await request(url)).data || []; if (ticket !== userGeneration) return;
            candidates = rows; setOptions(userSelect, rows, rows.length ? 'شخص را انتخاب کنید' : 'حسابی پیدا نشد'); userSelect.value = ''; window.BuildinoSelect2?.refresh(userSelect);
            $('user-hint').textContent = rows.length ? `${number(rows.length)} حساب پیدا شد.` : 'حسابی پیدا نشد؛ اطلاعات دیگری جست‌وجو کنید یا حساب جدید بسازید.';
        } catch (error) { if (ticket === userGeneration) report(error); }
    };
    const save = async event => {
        event.preventDefault(); if (busy || !unitId()) return; message(null, null);
        if (!form.reportValidity()) return;
        const selectedId = safeId(userSelect.value);
        if (!editing && !candidates.some(candidate => Number(candidate.id) === selectedId)) { message('error', 'یک حساب کاربری معتبر را انتخاب کنید.'); userSelect.focus(); return; }
        const start = readDate(form, 'starts_at'), end = readDate(form, 'ends_at');
        if (!editing && end && end < start) { $('more').open = true; message('error', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.'); return; }
        if (!editing && records.some(record => Number(record.user_id) === selectedId && record.is_active && (!record.ends_at || record.ends_at >= today()))) { message('error', 'این شخص هم‌اکنون ساکن همین واحد است. برای تغییر رابطه، رکورد فعلی را ویرایش کنید.'); return; }
        if (!editing && invitations.some(invitation => Number(invitation.invited_user_id) === selectedId && invitation.planned_occupancy && ['pending', 'sent'].includes(invitation.status) && !invitationExpired(invitation))) { message('error', 'برای این شخص دعوت در انتظار پذیرش وجود دارد. ابتدا آن را لغو کنید.'); return; }
        const payload = { occupancy_type: form.elements.namedItem('occupancy_type').value, starts_at: start, is_primary: form.elements.namedItem('is_primary').checked, notes: form.elements.namedItem('notes').value.trim() || null };
        if (!editing) { payload.user_id = selectedId; payload.is_active = true; if (end) payload.ends_at = end; }
        const inviteFirst = !editing && form.elements.namedItem('send_invitation').checked;
        if (inviteFirst && (!building()?.canInvite || !candidates.find(candidate => Number(candidate.id) === selectedId)?.hasMobile)) {
            message('error', 'برای دعوت، حساب فعال با شماره موبایل لازم است.'); return;
        }
        busy = true; $('save').disabled = true;
        try {
            const endpoint = inviteFirst ? `${config.apiBase}/units/${unitId()}/invitations` : editing ? `${config.apiBase}/unit-occupancies/${editing.id}` : `${config.apiBase}/units/${unitId()}/occupancies`;
            const body = inviteFirst ? { user_ids: [selectedId], occupancy: { occupancy_type: payload.occupancy_type, starts_at: payload.starts_at, ends_at: payload.ends_at || null, is_primary: payload.is_primary, notes: payload.notes } } : payload;
            await request(endpoint, { method: editing ? 'PATCH' : 'POST', body: JSON.stringify(body) });
            const success = inviteFirst ? 'دعوت ثبت شد. سکونت پس از پذیرش دعوت فعال می‌شود.' : editing ? 'تغییرات سکونت ذخیره شد.' : 'سکونت واحد ثبت شد.';
            closeEditor(); await loadRecords(); message('success', success);
        } catch (error) { report(error); }
        finally { busy = false; $('save').disabled = false; }
    };
    const sendInvite = async (record, button) => {
        if (busy || !unitId() || !building()?.canInvite) return;
        busy = true; button.disabled = true; message(null, null);
        try {
            await request(`${config.apiBase}/units/${unitId()}/invitations`, { method: 'POST', body: JSON.stringify({ user_ids: [record.user_id] }) });
            await loadRecords(); message('success', 'درخواست دعوت ثبت شد؛ وضعیت واقعی ارسال و پذیرش در کارت ساکن نمایش داده می‌شود.');
        } catch (error) { report(error); }
        finally { busy = false; button.disabled = false; }
    };
    const closeEnd = () => { window.jalaliDatepicker?.hide(); $('end-dialog').close(); };
    const openEnd = record => {
        ending = record; endTrigger = document.activeElement; $('end-name').textContent = nameOf(record); setDate(endForm, 'ends_at', today());
        $('end-backdrop').hidden = false; document.body.classList.add('ownership-modal-open'); $('end-dialog').show(); document.getElementById('occupancy-end-title').focus({ preventScroll: true });
    };
    const end = async event => {
        event.preventDefault(); if (busy || !ending || !unitId() || !endForm.reportValidity()) return;
        const endDate = readDate(endForm, 'ends_at');
        if (ending.starts_at && endDate < ending.starts_at) { closeEnd(); message('error', 'تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.'); return; }
        busy = true; $('end-confirm').disabled = true;
        try { await request(`${config.apiBase}/unit-occupancies/${ending.id}/end`, { method: 'POST', body: JSON.stringify({ ends_at: endDate }) }); closeEnd(); await loadRecords(); message('success', 'سکونت پایان یافت و در سوابق واحد باقی ماند.'); }
        catch (error) { closeEnd(); report(error); }
        finally { busy = false; $('end-confirm').disabled = false; }
    };
    fields.building.addEventListener('change', () => { message(null, null); loadBlocks(); });
    fields.block.addEventListener('change', () => { message(null, null); loadFloors(); });
    fields.floor.addEventListener('change', () => { message(null, null); loadUnits(); });
    fields.unit.addEventListener('change', () => { message(null, null); loadRecords(); });
    $('reload').addEventListener('click', () => { message(null, null); loadRecords(); });
    $('add').addEventListener('click', () => openEditor()); $('cancel').addEventListener('click', () => { if (!busy) closeEditor(); });
    $('back-unit').addEventListener('click', () => {
        if (busy) return;
        ++generation; recordsLoading = false; $('records').setAttribute('aria-busy', 'false'); $('reload').disabled = false;
        message(null, null); showStep('unit', true);
    });
    $('continue').addEventListener('click', () => { if (!busy && unitId()) loadRecords(); });
    $('end-cancel').addEventListener('click', closeEnd); $('end-backdrop').addEventListener('click', closeEnd);
    $('end-dialog').addEventListener('close', () => { $('end-backdrop').hidden = true; document.body.classList.remove('ownership-modal-open'); ending = null; if (endTrigger?.isConnected) endTrigger.focus({ preventScroll: true }); endTrigger = null; });
    document.addEventListener('keydown', event => {
        const dialog = $('end-dialog'); if (!dialog.open) return;
        if (event.key === 'Escape') { event.preventDefault(); closeEnd(); }
        if (event.key === 'Tab') {
            const focusable = [...dialog.querySelectorAll('input:not([type="hidden"]), button')].filter(node => !node.disabled);
            if (event.shiftKey && (document.activeElement === focusable[0] || !dialog.contains(document.activeElement))) { event.preventDefault(); focusable.at(-1)?.focus(); }
            else if (!event.shiftKey && (document.activeElement === focusable.at(-1) || !dialog.contains(document.activeElement))) { event.preventDefault(); focusable[0]?.focus(); }
        }
    });
    form.addEventListener('submit', save); endForm.addEventListener('submit', end);
    userSearch.addEventListener('input', () => { userSelect.value = ''; candidates = []; $('account-status').textContent = ''; clearTimeout(searchTimer); searchTimer = setTimeout(searchUsers, 280); });
    userSelect.addEventListener('change', () => {
        const candidate = candidates.find(item => Number(item.id) === safeId(userSelect.value));
        $('invite-wrap').hidden = !building()?.canInvite || !candidate?.hasMobile;
        if ($('invite-wrap').hidden) form.elements.namedItem('send_invitation').checked = false;
        $('account-status').textContent = candidate ? candidate.mobileVerified || candidate.emailVerified ? 'هویت این حساب تأیید شده است.' : candidate.hasMobile ? 'هویت این حساب هنوز تأیید نشده است؛ برای پذیرش دعوت باید آن را تأیید کند.' : 'برای این حساب شماره موبایل ثبت نشده و امکان دعوت پیامکی وجود ندارد.' : '';
    });
    const initial = config.initialUnit;
    fields.building.value = String(initial?.buildingId || (buildings.size === 1 ? [...buildings.keys()][0] : ''));
    window.BuildinoSelect2?.refresh(fields.building);
    if (fields.building.value) loadBlocks(initial); else showStep('unit');
})();
