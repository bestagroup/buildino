(() => {
    'use strict';

    const config = window.BuildinoFlowConfig || {};
    const structure = ['complexes', 'buildings', 'blocks', 'floors', 'units'];
    const labels = { complexes: 'مجتمع', buildings: 'ساختمان', blocks: 'بلوک', floors: 'طبقه', units: 'واحد', users: 'کاربر', 'role-assignments': 'نقش و محدوده', invitations: 'دعوت' };
    const parents = { buildings: 'complex_id', blocks: 'building_id', floors: 'block_id', units: 'floor_id' };
    const parentResources = { buildings: 'complexes', blocks: 'buildings', floors: 'blocks', units: 'floors' };
    const storageKey = `buildino.core-flows.${config.actorId || 'guest'}`;
    const safeId = (value) => /^[1-9]\d*$/.test(String(value ?? '')) ? Number(value) : null;
    const read = () => {
        try { const value = JSON.parse(sessionStorage.getItem(storageKey) || '{}'); return value && typeof value === 'object' && !Array.isArray(value) ? value : {}; } catch { return {}; }
    };
    let state = read();
    let resourceKey = '';
    let rows = [];
    let message = '';
    let problem = '';
    let loading = false;
    let generation = 0;
    const contextLabels = {};
    let onChoose = null;
    const persist = () => { try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* Navigation also carries selected IDs. */ } };
    const flow = () => new URL(window.location.href).searchParams.get('flow');
    const allowed = (key, action) => {
        if (!config.resources?.[key]) return false;
        const prefix = key === 'role-assignments' || key === 'roles' ? 'users' : key === 'invitations' ? 'unit-invitations' : key;
        const permissions = key === 'role-assignments' || key === 'roles' ? config.globalPermissions : config.permissions;
        return (permissions || []).includes(`${prefix}.${key === 'role-assignments' && action === 'create' ? 'update' : action}`);
    };
    const canP05 = () => (config.globalPermissions || []).includes('users.create')
        && (config.globalPermissions || []).includes('users.update') && Boolean(config.resources?.['role-assignments']);
    const context = () => state[flow()] || {};
    const request = async (url, options = {}) => {
        const endpoint = new URL(url, window.location.origin);
        if (endpoint.origin !== window.location.origin) throw new Error('آدرس درخواست معتبر نیست.');
        const response = await fetch(endpoint, {
            credentials: 'same-origin', ...options,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '', ...options.headers },
        });
        const payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        if (!response.ok) {
            const error = new Error(response.status === 401 || response.status === 419 ? 'نشست شما پایان یافته است؛ دوباره وارد شوید.'
                : response.status === 403 ? 'این عملیات در محدوده دسترسی شما مجاز نیست.'
                : response.status === 429 ? 'درخواست‌های زیادی ارسال شده است؛ کمی بعد دوباره تلاش کنید.'
                : Object.values(payload?.errors || {}).flat().join('؛ ') || payload?.message || 'درخواست انجام نشد؛ دوباره تلاش کنید.');
            error.status = response.status;
            error.errors = payload?.errors || {};
            throw error;
        }
        return payload;
    };
    const urlFor = (key, flowId = flow(), ids = context().ids || {}) => {
        const url = new URL(config.resources[key].url, window.location.origin);
        url.searchParams.set('flow', flowId);
        for (const [resource, value] of Object.entries(ids)) {
            if (safeId(value) && structure.includes(resource)) url.searchParams.set(`${resource}_id`, String(value));
        }
        if (flowId === 'P05' && safeId(state.P05?.userId)) url.searchParams.set('user_id', String(state.P05.userId));
        return url.pathname + url.search;
    };
    const selectStructure = (key, record) => {
        if (!structure.includes(key) || !safeId(record?.id)) return;
        const ids = { ...(state.P06?.ids || {}) };
        const index = structure.indexOf(key);
        for (const child of structure.slice(index + 1)) delete ids[child];
        ids[key] = Number(record.id);
        contextLabels[key] = record.title || record.unit_number || record.code || `#${record.id}`;
        const parent = parentResources[key];
        if (parent && safeId(record[parents[key]])) ids[parent] = Number(record[parents[key]]);
        state.P06 = { ids };
        persist();
    };
    const selected = () => safeId(context().ids?.[resourceKey]);
    const host = () => document.querySelector('[data-core-flow-host]');
    const render = () => {
        const root = host();
        if (!root) return;
        window.BuildinoSelect2?.destroy(root);
        const active = flow();
        const structurePage = structure.includes(resourceKey);
        if (!structurePage && !['users', 'role-assignments'].includes(resourceKey)) { root.hidden = true; return; }
        root.hidden = false;
        root.className = 'core-flow';
        if (active !== 'P06' && active !== 'P05') {
            root.innerHTML = `<h2>${structurePage ? 'ساختار ساختمان را مرحله‌به‌مرحله تکمیل کنید' : 'تعریف کاربر و دسترسی'}</h2><p>${structurePage ? 'مجتمع یا ساختمان موجود را انتخاب کنید و سپس بلوک، طبقه و واحدها را اضافه کنید.' : 'ابتدا مشخصات کاربر را ثبت کنید؛ تعیین نقش و محدوده توسط مدیر سامانه انجام می‌شود.'}</p><div class="core-flow__actions"></div>`;
            if (structurePage || canP05()) {
                const start = document.createElement('a');
                start.className = 'is-primary'; start.textContent = 'شروع راهنمای مراحل';
                start.href = urlFor(resourceKey, structurePage ? 'P06' : 'P05');
                root.querySelector('.core-flow__actions').append(start);
            }
            return;
        }
        if (active === 'P05' && !canP05()) { root.hidden = true; return; }
        if (active === 'P06' && !structurePage) { root.hidden = true; return; }
        const steps = active === 'P06' ? structure : ['users', 'role-assignments', 'activation'];
        const current = active === 'P05' && state.P05?.assignmentId ? 'activation' : resourceKey;
        root.innerHTML = `<h2>${active === 'P06' ? 'راه‌اندازی ساختار ساختمان' : 'تعریف کاربر و دسترسی'}</h2><ol class="core-flow__steps" aria-label="مراحل فرآیند">${steps.map((key, index) => `<li ${current === key ? 'aria-current="step"' : ''} class="${active === 'P06' ? (safeId(context().ids?.[key]) ? 'is-complete' : '') : (key === 'users' && state.P05?.userId || key === 'role-assignments' && state.P05?.assignmentId || key === 'activation' && state.P05?.active ? 'is-complete' : '')}">${index + 1}. ${labels[key] || 'فعال‌سازی'}</li>`).join('')}</ol><p class="core-flow__hint">${active === 'P06' ? 'هر مرحله جداگانه ذخیره می‌شود. می‌توانید رکورد موجود را انتخاب یا مورد جدیدی اضافه کنید.' : 'کاربر جدید تا پایان تخصیص نقش غیرفعال می‌ماند؛ مراحل ذخیره‌شده قابل ادامه هستند.'}</p><div class="core-flow__actions"></div><p class="core-flow__status" role="status"></p><p class="core-flow__error" role="alert"></p>`;
        root.querySelector('[role="status"]').textContent = loading ? 'در حال بررسی اطلاعات ذخیره‌شده…' : message;
        root.querySelector('[role="alert"]').textContent = problem;
        if (active === 'P06') {
            const summary = document.createElement('p'); summary.className = 'core-flow__hint';
            summary.textContent = structure.filter((key) => safeId(context().ids?.[key]) && contextLabels[key]).map((key) => `${labels[key]}: ${contextLabels[key]}`).join(' ← ');
            if (summary.textContent) root.querySelector('.core-flow__steps').after(summary);
        }
        const actions = root.querySelector('.core-flow__actions');
        const link = (title, href) => { const element = document.createElement('a'); element.textContent = title; element.href = href; actions.append(element); return element; };
        if (active === 'P06' || resourceKey === 'users') {
            if (rows.length) {
                const select = document.createElement('select'); select.className = 'core-flow__select'; select.setAttribute('aria-label', `انتخاب ${labels[resourceKey]} موجود`);
                select.add(new Option(`انتخاب ${labels[resourceKey]} موجود`, ''));
                for (const row of rows) if (safeId(row.id)) select.add(new Option(String(row.full_name || [row.first_name, row.last_name].filter(Boolean).join(' ') || row.title || row.unit_number || row.code || `شناسه ${row.id}`), String(row.id)));
                select.value = String(active === 'P06' ? selected() || '' : state.P05?.userId || '');
                select.disabled = loading;
                select.addEventListener('change', async () => {
                    const row = rows.find((item) => Number(item.id) === Number(select.value));
                    if (!row) return;
                    if (active === 'P06') selectStructure(resourceKey, row);
                    else { state.P05 = { userId: Number(row.id), active: Boolean(row.is_active) }; persist(); }
                    problem = ''; message = `${labels[resourceKey]} انتخاب شد.`; onChoose?.(row); render();
                });
                actions.append(select);
            }
            if (allowed(resourceKey, 'create')) {
                const create = document.createElement('button'); create.type = 'button'; create.textContent = `افزودن ${labels[resourceKey]}`; create.disabled = loading;
                create.addEventListener('click', () => window.dispatchEvent(new CustomEvent('buildino:flow-create', { detail: { resourceKey } })));
                actions.append(create);
            }
        }
        if (active === 'P06' && selected()) {
            const next = structure[structure.indexOf(resourceKey) + 1];
            if (next && config.resources[next]) link('ادامه به ' + labels[next], urlFor(next)).className = 'is-primary';
            if (!next) {
                root.querySelector('[role="status"]').textContent = 'ساختار واحدها ثبت شد. برای هر طبقه می‌توانید واحدهای بیشتری اضافه کنید.';
                if (config.resources.invitations && allowed('invitations', 'create')) {
                    const invite = new URL(config.resources.invitations.url, window.location.origin);
                    invite.searchParams.set('unit_id', String(selected()));
                    link('دعوت کاربران این واحد', invite.pathname + invite.search).className = 'is-primary';
                }
            }
        }
        if (active === 'P05' && resourceKey === 'users' && state.P05?.userId && !state.P05.assignmentId) link('ادامه به نقش و محدوده', urlFor('role-assignments')).className = 'is-primary';
        if (active === 'P05' && state.P05?.assignmentId && !state.P05.active) {
            const activate = document.createElement('button'); activate.type = 'button'; activate.className = 'is-primary'; activate.textContent = 'فعال‌سازی حساب'; activate.disabled = loading;
            activate.addEventListener('click', async () => {
                loading = true; problem = ''; render();
                try {
                    await request(`/management/data/users/${state.P05.userId}`, { method: 'PATCH', body: JSON.stringify({ is_active: true }) });
                    state.P05.active = true; persist(); message = 'حساب کاربر فعال شد.';
                } catch (error) { problem = error.message; } finally { loading = false; render(); }
            });
            actions.append(activate);
        }
        if (active === 'P05' && state.P05?.active && state.P05?.assignmentId) root.querySelector('[role="status"]').textContent = 'نقش و محدوده ثبت شد و حساب کاربر فعال است.';
        link('خروج از راهنما', config.resources[resourceKey].url);
    };
    const validateStructure = async () => {
        if (flow() !== 'P06') return;
        const ticket = ++generation;
        loading = true; render();
        try {
            const ids = { ...(state.P06?.ids || {}) };
            for (const key of structure) {
                if (!safeId(ids[key]) || !config.resources?.[key]) continue;
                const payload = await request(`/api/v1/${key}/${ids[key]}`);
                if (ticket !== generation) return;
                const record = payload?.data;
                if (!record || Number(record.id) !== Number(ids[key])) throw new Error('رکورد انتخاب‌شده دیگر در دسترس نیست.');
                contextLabels[key] = record.title || record.unit_number || record.code || `#${record.id}`;
                const parent = parentResources[key];
                if (parent && ids[parent] && Number(record[parents[key]]) !== Number(ids[parent])) {
                    for (const child of structure.slice(structure.indexOf(key))) delete ids[child];
                    break;
                }
            }
            state.P06 = { ids }; persist();
        } catch (error) {
            if (ticket === generation) { state.P06 = { ids: {} }; persist(); problem = error.message; }
        } finally { if (ticket === generation) { loading = false; render(); } }
    };
    const validateAccess = async () => {
        if (flow() !== 'P05' || !canP05() || !safeId(state.P05?.userId)) return;
        loading = true; render();
        try {
            const result = await request(`/management/data/role-assignments?user_id=${state.P05.userId}`);
            const assignment = (result?.data || []).find((item) => Number(item.id) === Number(state.P05.assignmentId) && item.is_active);
            if (!assignment) delete state.P05.assignmentId;
            persist();
        } catch (error) { problem = error.message; } finally { loading = false; render(); }
    };
    const mount = (key, choose = null) => {
        resourceKey = key; onChoose = choose; rows = []; message = ''; problem = '';
        const params = new URL(window.location.href).searchParams;
        if (flow() === 'P06') {
            const ids = { ...(state.P06?.ids || {}) };
            for (const entry of structure) if (safeId(params.get(`${entry}_id`))) ids[entry] = safeId(params.get(`${entry}_id`));
            state.P06 = { ids }; persist();
        }
        if (flow() === 'P05' && canP05() && safeId(params.get('user_id')) && Number(params.get('user_id')) !== Number(state.P05?.userId)) {
            state.P05 = { userId: safeId(params.get('user_id')) }; persist();
        }
        render();
        return flow() === 'P05' ? validateAccess() : validateStructure();
    };
    const saved = (key, record, mode) => {
        if (!safeId(record?.id)) return;
        if (flow() === 'P06' && structure.includes(key)) selectStructure(key, record);
        if (flow() === 'P05' && canP05()) {
            if (key === 'users') state.P05 = { userId: Number(record.id), active: Boolean(record.is_active) };
            if (key === 'role-assignments' && Number(record.user_id) === Number(state.P05?.userId)) {
                if (record.is_active === false) {
                    delete state.P05.assignmentId;
                    problem = 'تخصیص نقش غیرفعال است؛ پیش از پایان راهنما آن را فعال کنید.';
                } else state.P05.assignmentId = Number(record.id);
            }
            persist();
        }
        message = `${labels[key] || 'اطلاعات'} ${mode === 'edit' ? 'ویرایش' : 'ثبت'} شد؛ برای ادامه مرحله بعد را انتخاب کنید.`;
        render();
    };
    const defaults = (key, values, mode) => {
        if (mode !== 'create') return values;
        const result = { ...values };
        if (flow() === 'P06' && parents[key] && safeId(state.P06?.ids?.[parentResources[key]])) result[parents[key]] = state.P06.ids[parentResources[key]];
        if (flow() === 'P05' && canP05()) {
            if (key === 'users') result.is_active = false;
            if (key === 'role-assignments' && safeId(state.P05?.userId)) result.user_id = state.P05.userId;
        }
        return result;
    };
    const enhanceForm = async (container, mode) => {
        for (const error of container.querySelectorAll('.core-field-error')) error.remove();
        if (flow() === 'P05' && canP05() && resourceKey === 'users' && mode === 'create') {
            const input = container.querySelector('[name="is_active"]');
            if (input) { input.checked = false; input.closest('label').hidden = true; }
        }
        if (resourceKey === 'role-assignments') {
            const user = container.querySelector('[name="user_id"]');
            if (flow() === 'P05' && canP05() && safeId(state.P05?.userId) && mode === 'create' && user) {
                if (![...user.options].some((option) => Number(option.value) === Number(state.P05.userId))) user.add(new Option(`کاربر ثبت‌شده #${state.P05.userId}`, String(state.P05.userId)));
                user.value = String(state.P05.userId);
                user.disabled = true;
                window.jQuery?.(user).trigger('change.select2');
            }
            const scope = container.querySelector('[name="scope_type"]');
            const old = container.querySelector('[name="scope_id"]');
            if (scope && old) {
                const label = old.closest('label');
                if (label?.querySelector('span')) label.querySelector('span').textContent = 'محدوده دسترسی';
                if (label?.querySelector('small')) label.querySelector('small').textContent = 'محدوده را با نام آن انتخاب کنید؛ دسترسی سراسری به انتخاب محدوده نیاز ندارد.';
                const select = document.createElement('select');
                for (const attribute of old.attributes) if (!['type', 'inputmode', 'value'].includes(attribute.name)) select.setAttribute(attribute.name, attribute.value);
                select.dir = 'rtl';
                const previous = old.value; old.replaceWith(select);
                let count = 0;
                const load = async (initial = false) => {
                    const ticket = ++count;
                    const sources = { complex: 'complexes', building: 'buildings', block: 'blocks' };
                    select.replaceChildren(new Option(scope.value === 'global' ? 'دسترسی سراسری' : 'انتخاب محدوده', ''));
                    select.disabled = true; select.required = scope.value !== 'global'; select.setAttribute('aria-required', String(select.required));
                    if (!sources[scope.value]) return;
                    try {
                        const payload = await request(`/management/lookups/${sources[scope.value]}`);
                        if (ticket !== count || !select.isConnected) return;
                        for (const item of payload.data || []) select.add(new Option(item.label, String(item.id)));
                        if (initial) select.value = previous;
                        select.disabled = false;
                    } catch (error) { showErrors(container, { scope_id: [error.message] }); }
                };
                scope.addEventListener('change', () => { void load(); });
                await load(true);
            }
            const role = container.querySelector('[name="role_id"]');
            if (role) {
                const preview = document.createElement('div'); preview.className = 'core-flow'; preview.setAttribute('role', 'status'); container.append(preview);
                try {
                    const [roleResult, permissionResult] = await Promise.all([request('/management/data/roles'), request('/management/lookups/permissions')]);
                    const update = () => {
                        const selectedRole = (roleResult.data || []).find((item) => Number(item.id) === Number(role.value));
                        preview.replaceChildren();
                        const heading = document.createElement('strong'); heading.textContent = 'مجوزهای نقش انتخاب‌شده'; preview.append(heading);
                        const description = document.createElement('p'); description.textContent = selectedRole ? `${selectedRole.display_name}: ${selectedRole.description || ''}` : 'ابتدا نقش را انتخاب کنید.'; preview.append(description);
                        if (selectedRole) {
                            const details = document.createElement('details'); details.className = 'core-form-details';
                            const summary = document.createElement('summary'); summary.textContent = `مشاهده مجوزها (${(selectedRole.permission_ids || []).length.toLocaleString('fa-IR')})`;
                            const list = document.createElement('ul');
                            for (const item of permissionResult.data || []) if ((selectedRole.permission_ids || []).includes(item.id)) { const li = document.createElement('li'); li.textContent = item.label; list.append(li); }
                            details.append(summary, list); preview.append(details);
                        }
                    };
                    role.addEventListener('change', update); update();
                } catch { preview.textContent = 'نمایش مجوزها بارگذاری نشد؛ می‌توانید جزئیات نقش را در صفحه نقش‌ها بررسی کنید.'; }
            }
        }
        groupOptional(container);
    };
    const optional = new Set(['address', 'postal_code', 'latitude', 'longitude', 'sort_order', 'building_number', 'timezone', 'currency', 'floors_count', 'units_count', 'parking_count', 'storage_count', 'construction_year', 'bedrooms', 'national_code', 'starts_at', 'ends_at']);
    const groupOptional = (container) => {
        if (container.querySelector('.core-form-details')) return;
        const fields = [...container.querySelectorAll(':scope > label')].filter((label) => {
            const input = label.querySelector('[name]');
            return input && optional.has(input.name) && !input.required;
        });
        if (!fields.length) return;
        const details = document.createElement('details'); details.className = 'core-form-details';
        const summary = document.createElement('summary'); summary.textContent = 'اطلاعات تکمیلی (اختیاری)';
        const grid = document.createElement('div'); details.append(summary, grid); container.append(details);
        for (const field of fields) grid.append(field);
    };
    const showErrors = (container, errors = {}) => {
        container.querySelectorAll('.core-field-error').forEach((node) => node.remove());
        container.querySelectorAll('[aria-invalid="true"]').forEach((input) => input.setAttribute('aria-invalid', 'false'));
        let first = null;
        for (const [name, messages] of Object.entries(errors)) {
            const input = container.querySelector(`[name="${CSS.escape(name)}"]`);
            if (!input) continue;
            const details = input.closest('details'); if (details) details.open = true;
            input.setAttribute('aria-invalid', 'true');
            const feedback = document.createElement('small'); feedback.className = 'core-field-error'; feedback.setAttribute('role', 'alert'); feedback.id = `core-error-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
            feedback.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            input.setAttribute('aria-describedby', feedback.id); input.closest('label')?.append(feedback);
            first ||= input;
        }
        first?.focus();
    };
    const contextChanged = (key, parentId) => {
        if (flow() !== 'P06' || !parentResources[key]) return;
        const parent = parentResources[key];
        if (Number(state.P06?.ids?.[parent]) === Number(parentId)) return;
        const ids = { ...(state.P06?.ids || {}) };
        for (const child of structure.slice(structure.indexOf(key))) delete ids[child];
        for (const child of structure.slice(structure.indexOf(key))) delete contextLabels[child];
        if (safeId(parentId)) ids[parent] = Number(parentId); else delete ids[parent];
        state.P06 = { ids }; persist(); render();
    };
    window.BuildinoCoreFlows = { mount, saved, defaults, enhanceForm, showErrors, request, safeId, allowed, labels, contextChanged, parentValue: (key) => state.P06?.ids?.[parentResources[key]], setRows: (items) => { rows = items; render(); }, groupOptional };
    document.addEventListener('submit', (event) => {
        if (event.target.action?.endsWith('/logout')) {
            try {
                sessionStorage.removeItem(storageKey);
                sessionStorage.removeItem('buildino.pending-invitation');
            } catch { /* Session storage may be unavailable. */ }
        }
    });
})();
