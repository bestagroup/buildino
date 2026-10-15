(() => {
    'use strict';
    const root = document.querySelector('[data-building-setup]');
    if (!root) return;
    const config = window.BuildinoBuildingSetupConfig;
    const engine = window.BuildinoBuildingSetupEngine;
    const form = root.querySelector('[data-setup-form]');
    const query = (selector) => root.querySelector(selector);
    const key = `buildino.building-setup.${config.actorId}`;
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const fa = (number) => Number(number).toLocaleString('fa-IR');
    const digits = (value) => String(value).replace(/[۰-۹٠-٩]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
    const newBlock = (title = 'بلوک اصلی') => ({ title, startFloor: 0, unitsPerFloor: 2, defaultArea: '', defaultBedrooms: '', usage: 'residential', floors: [] });
    const freshDraft = () => {
        const chosen = config.buildings.find((building) => Number(building.id) === Number(config.initialBuildingId)) || (config.buildings.length === 1 ? config.buildings[0] : null);
        const block = newBlock(); engine.resizeFloors(block, 3);
        return { token: crypto.randomUUID(), mode: config.buildings.length ? 'existing' : 'new', buildingId: chosen?.id || '', title: '', complexMode: config.canCreateComplex ? 'standalone' : 'existing', complexId: config.complexes.length === 1 ? config.complexes[0].id : '', complexTitle: '', provinceId: '', cityId: '', province: '', city: '', address: '', postalCode: '', parkingCount: 0, storageCount: 0, blocks: [block], started: false, loadedBuildingId: null };
    };
    let draft = freshDraft();
    let step = 0;
    let furthest = 0;
    let busy = false;
    let ratePauseUntil = 0;
    let restored = false;
    try {
        const saved = JSON.parse(sessionStorage.getItem(key) || 'null');
        if (saved?.version === 1 && Array.isArray(saved.draft?.blocks) && saved.draft.blocks.length <= 20 && saved.draft.blocks.every((block) => Array.isArray(block.floors) && block.floors.length <= 100 && block.floors.every((floor) => Array.isArray(floor.units) && floor.units.length <= 2000))) {
            draft = saved.draft; step = draft.started ? 3 : Math.max(0, Math.min(3, Number(saved.step) || 0)); furthest = step; restored = true;
        }
    } catch { /* A broken or unavailable browser draft does not block setup. */ }
    const persist = () => { try { sessionStorage.setItem(key, JSON.stringify({ version: 1, draft, step })); } catch {} };
    const clearError = () => { query('[data-setup-error]').hidden = true; };
    const errorMessage = (message, field) => {
        const error = query('[data-setup-error]'); error.textContent = message; error.hidden = false;
        const input = field && form.elements.namedItem(field);
        if (input instanceof HTMLElement) { input.setAttribute('aria-invalid', 'true'); (input.parentElement.querySelector('.select2-selection') || input).focus(); }
        else error.scrollIntoView({ block: 'center', behavior: 'smooth' });
    };
    const pauseIfNeeded = async () => {
        while (Date.now() < ratePauseUntil) {
            const message = `ادامه کار تا ${fa(Math.ceil((ratePauseUntil - Date.now()) / 1000))} ثانیه دیگر…`;
            query('[data-setup-saving-label]').textContent = message;
            query('[data-setup-step-status]').textContent = message;
            await new Promise((resolve) => window.setTimeout(resolve, 1000));
        }
    };
    const api = async (path, options = {}) => {
        const url = new URL(path, `${config.apiBase}/`);
        const base = new URL(`${config.apiBase}/`);
        if (url.origin !== window.location.origin || !url.pathname.startsWith(base.pathname)) throw new Error('مسیر دریافت اطلاعات معتبر نیست.');
        await pauseIfNeeded();
        let response;
        try { response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken } }); }
        catch { throw new Error('ارتباط برقرار نشد. اطلاعات ثبت‌شده حفظ شده‌اند؛ دوباره ادامه دهید.'); }
        const remaining = response.headers.get('x-ratelimit-remaining');
        if (remaining !== null && Number(remaining) <= 2) ratePauseUntil = Date.now() + 62000;
        let payload;
        try { payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null; }
        catch { throw new Error('پاسخ ثبت کامل دریافت نشد. با «ادامه ثبت»، وضعیت ذخیره‌شده بررسی می‌شود.'); }
        if (!response.ok) {
            const error = new Error(response.status === 401 || response.status === 419 ? 'نشست شما پایان یافته است. دوباره وارد شوید و این راه‌اندازی را ادامه دهید.'
                : response.status === 403 ? 'مجوز انجام این کار را برای ساختمان انتخاب‌شده ندارید.'
                : response.status === 429 ? 'کمی صبر کنید و سپس «ادامه ثبت» را بزنید.'
                : Object.values(payload?.errors || {}).flat().join('؛ ') || payload?.message || 'ثبت کامل نشد؛ دوباره ادامه دهید.');
            error.status = response.status;
            if (response.status === 429) ratePauseUntil = Date.now() + (Number(response.headers.get('retry-after')) || 60) * 1000;
            throw error;
        }
        return payload;
    };
    const syncLocations = (reset = false) => {
        const province = config.locations.find((item) => item.id === String(draft.provinceId));
        if (reset) draft.cityId = '';
        const cityInput = form.elements.namedItem('cityId');
        cityInput.replaceChildren(new Option(province ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید', ''));
        for (const city of province?.cities || []) cityInput.add(new Option(city.label, city.id));
        cityInput.value = String(draft.cityId || '');
        const city = province?.cities.find((item) => item.id === String(draft.cityId));
        draft.province = province?.name || ''; draft.city = city?.name || '';
        cityInput.disabled = !province || draft.started;
        window.BuildinoSelect2?.refresh(cityInput);
    };
    const syncBasics = () => {
        const existing = draft.mode === 'existing';
        query('[data-setup-existing]').hidden = !existing;
        query('[data-setup-new]').hidden = existing;
        query('[data-setup-complex-name]').hidden = draft.complexMode !== 'new';
        query('[data-setup-complex-existing]').hidden = draft.complexMode !== 'existing';
        query('[data-setup-location]').hidden = draft.complexMode === 'existing';
    };
    const counter = (attribute, value, minimum, label) => `<div class="setup-counter"><button type="button" data-count-step="-1" aria-label="کاهش ${esc(label)}">−</button><input type="number" ${attribute} value="${value}" min="${minimum}" max="100" step="1" aria-label="${esc(label)}"><button type="button" data-count-step="1" aria-label="افزایش ${esc(label)}">+</button></div>`;
    const renderBlocks = () => {
        const host = query('[data-setup-blocks]'); window.BuildinoSelect2?.destroy(host);
        host.innerHTML = draft.blocks.map((block, index) => `<article class="setup-block" data-block="${index}">
            <div class="setup-block__header"><h4>${esc(block.title)}${block.existing ? '<span class="setup-badge">ثبت‌شده</span>' : ''}</h4>${!block.existing && draft.blocks.length > 1 ? '<button type="button" class="setup-button setup-button--danger" data-block-remove>حذف از پیش‌نویس</button>' : ''}</div>
            <div class="setup-fields">
                <label class="setup-field setup-wide"><span>نام بلوک</span><input type="text" data-block-field="title" value="${esc(block.title)}" maxlength="255" ${block.existing ? 'readonly' : ''}></label>
                <div class="setup-field"><span>تعداد طبقات این بلوک</span>${counter('data-block-field="floorCount"', block.floors.length || 1, Math.max(1, block.floors.filter((floor) => floor.existing).length), 'تعداد طبقات')}</div>
                <div class="setup-field"><span>واحدهای معمول در هر طبقه</span>${counter('data-block-field="unitsPerFloor"', block.unitsPerFloor, 0, 'واحدهای هر طبقه')}</div>
                <label class="setup-field"><span>شروع طبقات از</span><select data-block-field="startFloor" data-required="1" ${block.floors.some((floor) => floor.existing) ? 'disabled' : ''}>${[...new Set([-3,-2,-1,0,1,Number(block.startFloor)])].sort((a,b) => a-b).map((number) => `<option value="${number}" ${Number(block.startFloor) === number ? 'selected' : ''}>${esc(engine.labelForFloor(number))}</option>`).join('')}</select></label>
                <label class="setup-field"><span>کاربری واحدهای جدید</span><select data-block-field="usage" data-required="1">${Object.entries({ residential: 'مسکونی', commercial: 'تجاری', office: 'اداری', other: 'سایر' }).map(([value,label]) => `<option value="${value}" ${block.usage === value ? 'selected' : ''}>${label}</option>`).join('')}</select></label>
                <label class="setup-field"><span>متراژ معمول واحدها <small>اختیاری</small></span><input type="number" data-block-field="defaultArea" value="${esc(block.defaultArea)}" min="0" step="0.01" placeholder="مثلاً ۱۰۰ مترمربع"></label>
                <label class="setup-field"><span>تعداد اتاق معمول <small>اختیاری</small></span><input type="number" data-block-field="defaultBedrooms" value="${esc(block.defaultBedrooms)}" min="0" max="50" step="1"></label>
            </div><p class="setup-note">${block.existing ? 'طبقات و واحدهای ثبت‌شده حفظ می‌شوند. تعداد واحدهای هر طبقه را در گام بعد می‌توانید جداگانه تنظیم کنید.' : 'طبقات و واحدها با همین الگو آماده می‌شوند؛ در گام بعد جزئیات هر طبقه را اصلاح کنید.'}</p></article>`).join('');
        window.BuildinoSelect2?.enhance(host);
    };
    const renderTotals = () => {
        const count = engine.totals(draft.blocks);
        query('[data-setup-totals]').innerHTML = `<span>${fa(count.blocks)} بلوک</span><span>${fa(count.floors)} طبقه</span><span>${fa(count.units)} واحد</span>`;
    };
    const renderUnits = () => {
        renderTotals();
        query('[data-setup-units]').innerHTML = draft.blocks.map((block, blockIndex) => `<div data-block="${blockIndex}"><h4>${esc(block.title)}</h4>${block.floors.map((floor, floorIndex) => `<details class="setup-floor" data-floor="${floorIndex}" ${floorIndex === 0 ? 'open' : ''}><summary>${esc(floor.title)} <span class="setup-badge">${fa(floor.units.length)} واحد</span>${floor.existing ? '<span class="setup-badge">ثبت‌شده</span>' : ''}</summary><div class="setup-floor__content"><div class="setup-floor__header"><div class="setup-field"><span>تعداد واحدهای این طبقه</span>${counter('data-floor-count', floor.units.length, floor.units.filter((unit) => unit.existing).length, 'واحدهای این طبقه')}</div></div><table class="setup-unit-table"><caption class="visually-hidden">واحدهای ${esc(block.title)}، ${esc(floor.title)}</caption><thead><tr><th scope="col">شماره واحد</th><th scope="col">متراژ (مترمربع)</th><th scope="col">اتاق</th><th scope="col">وضعیت</th></tr></thead><tbody>${floor.units.map((unit, unitIndex) => `<tr data-unit="${unitIndex}"><td><input type="text" data-unit-field="unit_number" value="${esc(unit.unit_number)}" maxlength="100" aria-label="شماره واحد ${unitIndex + 1} در ${esc(floor.title)}" ${unit.existing ? 'readonly' : ''}></td><td><input type="number" data-unit-field="area" value="${esc(unit.area)}" min="0" step="0.01" aria-label="متراژ واحد ${esc(unit.unit_number)}" ${unit.existing ? 'readonly' : ''}></td><td><input type="number" data-unit-field="bedrooms" value="${esc(unit.bedrooms)}" min="0" max="50" step="1" aria-label="اتاق‌های واحد ${esc(unit.unit_number)}" ${unit.existing ? 'readonly' : ''}></td><td><span class="setup-badge">${unit.existing ? 'ثبت‌شده' : 'جدید'}</span></td></tr>`).join('')}</tbody></table>${!floor.units.length ? '<p class="setup-note">این طبقه واحدی ندارد. با دکمه + واحد اضافه کنید.</p>' : ''}</div></details>`).join('')}</div>`).join('');
    };
    const renderReview = () => {
        const count = engine.totals(draft.blocks);
        const building = config.buildings.find((item) => Number(item.id) === Number(draft.buildingId));
        const title = draft.mode === 'existing' ? building?.title || 'ساختمان انتخاب‌شده' : draft.title;
        const existing = draft.blocks.reduce((sum, block) => sum + block.floors.reduce((total, floor) => total + floor.units.filter((unit) => unit.existing).length, 0), 0);
        query('[data-setup-review]').innerHTML = `<div class="setup-review"><div class="setup-review__building"><h4>${esc(title)}</h4><p>${esc(draft.mode === 'existing' ? building?.complexTitle || '' : draft.complexMode === 'existing' ? config.complexes.find((item) => Number(item.id) === Number(draft.complexId))?.title || '' : draft.complexMode === 'standalone' ? 'ساختمان مستقل' : draft.complexTitle)}</p><p>${esc(draft.mode === 'existing' ? building?.address || '' : [draft.province, draft.city, draft.address].filter(Boolean).join('، '))}</p></div>${draft.blocks.map((block) => `<div class="setup-review__row"><strong>${esc(block.title)}</strong><span>${fa(block.floors.length)} طبقه · ${fa(block.floors.reduce((sum, floor) => sum + floor.units.length, 0))} واحد</span></div>`).join('')}<div class="setup-review__row"><strong>جمع کل</strong><span>${fa(count.units)} واحد، شامل ${fa(existing)} واحد ثبت‌شده و ${fa(count.units - existing)} واحد جدید</span></div><p class="setup-note">ساختار هر بخش پس از ثبت حفظ می‌شود. اگر ارتباط قطع شد، از همین صفحه ادامه دهید.</p></div>`;
    };
    const updateNavigation = () => {
        const links = [...root.querySelectorAll('[data-setup-step-link]')];
        links.forEach((button, index) => { button.disabled = busy || draft.started || index > furthest; button.classList.toggle('is-complete', index < step); if (index === step) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current'); });
        query('[data-setup-previous]').hidden = step === 0 || draft.started;
        query('[data-setup-previous]').disabled = busy;
        query('[data-setup-next]').disabled = busy;
        query('[data-setup-next]').textContent = step === 3 ? draft.started ? 'ادامه ثبت' : 'ثبت و راه‌اندازی' : 'ادامه';
        query('[data-setup-step-status]').textContent = `گام ${fa(step + 1)} از ۴`;
        query('[data-setup-reset]').disabled = busy;
    };
    const showStep = (index, focus = true) => {
        step = index; furthest = Math.max(furthest, index);
        root.querySelectorAll('[data-setup-step]').forEach((panel) => { panel.hidden = Number(panel.dataset.setupStep) !== step; });
        if (step === 1) renderBlocks();
        if (step === 2) renderUnits();
        if (step === 3) renderReview();
        updateNavigation(); persist();
        if (focus) { query(`[data-setup-step="${step}"] h3`).focus({ preventScroll: true }); query('.setup-steps').scrollIntoView({ block: 'start', behavior: 'smooth' }); }
    };
    const checkThrough = (last) => {
        const error = engine.validate(draft).find((item) => item.step <= last);
        if (error) { showStep(error.step); errorMessage(error.message, error.field); return false; }
        return true;
    };
    const loadExisting = async () => {
        if (draft.mode !== 'existing' || String(draft.loadedBuildingId) === String(draft.buildingId)) return;
        if (!config.buildings.some((building) => String(building.id) === String(draft.buildingId))) throw new Error('ساختمان را از فهرست ساختمان‌های خودتان انتخاب کنید.');
        const blocks = await engine.readStructure(api, draft.buildingId);
        for (const block of blocks) if (!block.floors.length) engine.resizeFloors(block, 3);
        draft.blocks = blocks.length ? blocks : [newBlock()];
        if (!blocks.length) engine.resizeFloors(draft.blocks[0], 3);
        draft.loadedBuildingId = draft.buildingId;
    };
    const submitSetup = async () => {
        if (!checkThrough(2)) return;
        draft.started = true; persist(); busy = true; updateNavigation();
        query('[data-setup-saving]').hidden = false;
        form.setAttribute('aria-busy', 'true');
        query('[data-setup-saving-label]').textContent = 'در حال بررسی و ثبت ساختار…';
        try {
            const result = await engine.execute(draft, api, ({ done, total, label }) => {
                query('[data-setup-progress]').value = Math.round(done / Math.max(total, 1) * 100);
                query('[data-setup-saving-label]').textContent = `در حال ثبت: ${label}`;
                query('[data-setup-saving-count]').textContent = `${fa(done)} از ${fa(total)} بخش بررسی و ثبت شد`;
            });
            form.hidden = true; query('.setup-steps').hidden = true; query('[data-setup-resume]').hidden = true;
            const success = query('[data-setup-success]'); success.hidden = false;
            query('[data-setup-success-summary]').textContent = `${fa(result.blocks)} بلوک، ${fa(result.floors)} طبقه و ${fa(result.units)} واحد در ساختار انتخاب‌شده آماده است.`;
            if (config.canInvite && result.firstUnitId) { const url = new URL(config.invitationsUrl, location.origin); url.searchParams.set('unit_id', result.firstUnitId); query('[data-setup-invite]').href = url.pathname + url.search; query('[data-setup-invite]').hidden = false; }
            try { sessionStorage.removeItem(key); } catch {}
            success.querySelector('h3').focus();
        } catch (error) {
            query('[data-setup-saving-label]').textContent = 'ثبت متوقف شد؛ بخش‌های ثبت‌شده محفوظ‌اند.';
            errorMessage(error.message);
        } finally { busy = false; form.removeAttribute('aria-busy'); updateNavigation(); }
    };
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); if (busy) return; clearError();
        if (step === 3) { await submitSetup(); return; }
        if (!checkThrough(step)) return;
        if (step === 0) {
            busy = true; updateNavigation(); query('[data-setup-next]').textContent = 'در حال آماده‌سازی…'; form.setAttribute('aria-busy', 'true');
            try { await loadExisting(); } catch (error) { errorMessage(error.message); return; } finally { busy = false; form.removeAttribute('aria-busy'); updateNavigation(); }
        }
        showStep(step + 1);
    });
    const fieldChanged = (event) => {
        const input = event.target;
        if (draft.started || !input.matches('[data-setup-field]')) return;
        const field = input.dataset.setupField;
        if (input.type === 'radio' && !input.checked) return;
        draft[field] = input.value;
        input.removeAttribute('aria-invalid'); clearError();
        if (field === 'mode' || field === 'buildingId') { draft.loadedBuildingId = null; furthest = 0; if (field === 'mode' && draft.mode === 'new') { draft.blocks = [newBlock()]; engine.resizeFloors(draft.blocks[0], 3); } }
        if (field === 'mode' || field === 'complexMode') syncBasics();
        if (field === 'provinceId') syncLocations(true);
        if (field === 'cityId') syncLocations();
        persist(); updateNavigation();
    };
    form.addEventListener('input', fieldChanged);
    form.addEventListener('change', fieldChanged);
    form.addEventListener('input', (event) => {
        if (draft.started || !event.target.matches('[data-unit-field]')) return;
        const floor = draft.blocks[Number(event.target.closest('[data-block]').dataset.block)].floors[Number(event.target.closest('[data-floor]').dataset.floor)];
        const unit = floor.units[Number(event.target.closest('[data-unit]').dataset.unit)];
        if (!unit.existing) unit[event.target.dataset.unitField] = event.target.dataset.unitField === 'unit_number' ? digits(event.target.value) : event.target.value;
        clearError(); persist();
    });
    form.addEventListener('change', (event) => {
        const input = event.target;
        if (draft.started || !input.matches('[data-block-field], [data-floor-count]')) return;
        const blockIndex = Number(input.closest('[data-block]').dataset.block);
        const block = draft.blocks[blockIndex];
        const previousBlock = structuredClone(block);
        if (input.hasAttribute('data-floor-count')) {
            engine.resizeUnits(block, block.floors[Number(input.closest('[data-floor]').dataset.floor)], input.value);
        } else {
            const field = input.dataset.blockField;
            if (field === 'floorCount') engine.resizeFloors(block, input.value);
            else if (field === 'unitsPerFloor') { block.unitsPerFloor = Math.max(0, Math.min(100, Math.trunc(Number(input.value) || 0))); block.floors.forEach((floor) => engine.resizeUnits(block, floor, block.unitsPerFloor)); }
            else if (field === 'startFloor') { block.startFloor = Number(input.value); block.floors.forEach((floor, index) => { if (!floor.existing) { floor.number = block.startFloor + index; floor.title = engine.labelForFloor(floor.number); } }); }
            else if (field === 'title') { if (!block.existing) block.title = input.value.trim(); }
            else {
                const previous = block[field]; block[field] = input.value;
                const unitField = { defaultArea: 'area', defaultBedrooms: 'bedrooms', usage: 'usage_type' }[field];
                for (const floor of block.floors) for (const unit of floor.units) if (!unit.existing && (unit[unitField] === '' || String(unit[unitField]) === String(previous))) unit[unitField] = input.value;
            }
        }
        if (engine.totals(draft.blocks).units > 2000) {
            draft.blocks[blockIndex] = previousBlock;
            if (step === 1) renderBlocks(); else renderUnits();
            errorMessage('در هر بار راه‌اندازی حداکثر ۲۰۰۰ واحد اضافه کنید.');
            return;
        }
        if (step === 1) renderBlocks(); else renderUnits();
        clearError(); persist();
    });
    form.addEventListener('click', (event) => {
        const counterButton = event.target.closest('[data-count-step]');
        if (counterButton && !draft.started) { const input = counterButton.parentElement.querySelector('input'); input.value = Math.max(Number(input.min || 0), Math.min(Number(input.max || 100), Number(input.value) + Number(counterButton.dataset.countStep))); input.dispatchEvent(new Event('change', { bubbles: true })); }
        const remove = event.target.closest('[data-block-remove]');
        if (remove && !draft.started) { const index = Number(remove.closest('[data-block]').dataset.block); if (!draft.blocks[index].existing && draft.blocks.length > 1) draft.blocks.splice(index, 1); renderBlocks(); persist(); }
    });
    query('[data-setup-add-block]').addEventListener('click', () => {
        if (draft.started || draft.blocks.length >= 20) return;
        let number = draft.blocks.length + 1;
        while (draft.blocks.some((block) => block.title === `بلوک ${fa(number)}`)) number++;
        const block = newBlock(`بلوک ${fa(number)}`); engine.resizeFloors(block, 3); draft.blocks.push(block); renderBlocks(); persist();
    });
    query('[data-setup-previous]').addEventListener('click', () => { if (!busy && !draft.started && step > 0) { clearError(); showStep(step - 1); } });
    root.querySelectorAll('[data-setup-step-link]').forEach((button) => button.addEventListener('click', () => { const index = Number(button.dataset.setupStepLink); if (!busy && !draft.started && index <= furthest && (index <= step || checkThrough(index - 1))) { clearError(); showStep(index); } }));
    const reset = () => { if (busy) return; try { sessionStorage.removeItem(key); } catch {} window.location.assign(window.location.pathname); };
    query('[data-setup-reset]').addEventListener('click', reset); query('[data-setup-another]').addEventListener('click', reset);
    for (const input of form.querySelectorAll('[data-setup-field]')) { if (input.type === 'radio') input.checked = input.value === draft.mode; else if (input.name !== 'cityId') input.value = draft[input.dataset.setupField] ?? ''; }
    syncBasics(); syncLocations(); showStep(step, false);
    query('[data-setup-resume]').hidden = !restored;
    if (draft.started) query('[data-setup-resume]').hidden = false;
    if (!config.canCreateBuilding && draft.mode === 'new') errorMessage('برای این حساب ساختمانی در دسترس نیست. از مدیر مجتمع بخواهید دسترسی ساختمان را بررسی کند.');
    window.addEventListener('beforeunload', (event) => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
    root.dataset.setupReady = 'true';
})();
