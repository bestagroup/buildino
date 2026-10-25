(() => {
    'use strict';
    const make = (tag, cls, text) => { const el = document.createElement(tag); el.className = cls || ''; if (text !== undefined) el.textContent = text; return el; };
    const number = value => new Intl.NumberFormat('fa-IR').format(value);
    let sequence = 0;
    window.BuildinoChargeFormulaUi = {
        mount(component, { collect, form, saveButton, row, reloadCategories }) {
            form.chargeWizardDestroy?.();
            const abort = new AbortController(), originalValidation = form.noValidate;
            form.noValidate = true;
            component.classList.add('charge-wizard');
            const $ = selector => component.querySelector(selector);
            const category = $('[data-formula-category]'), fixed = $('[data-formula-fixed-wrap]');
            category.dataset.select2 = 'off';
            const originalFloor = $('[data-formula-floor-wrap]'), floor = make('div', originalFloor.className);
            floor.dataset.formulaFloorWrap = '';
            floor.replaceChildren(...originalFloor.childNodes); originalFloor.replaceWith(floor);
            const coefficients = $('[data-formula-floor-coefficients]'); coefficients.hidden = true;
            const headings = ['برای کدام هزینه؟', 'هزینه چگونه تقسیم شود؟', 'تنظیمات روش محاسبه', 'بررسی نهایی فرمول'];
            const labels = ['نوع هزینه', 'روش محاسبه', 'پارامترها', 'بررسی نهایی'];
            let step = 0, busy = false, disposed = false, dirty = false, allowDiscard = false, loadingCategories = false;
            let titleInput = null, activeInput = null, suggestedTitle = row ? null : '';
            component.firstElementChild.remove();
            const context = make('p', 'charge-wizard__context', document.querySelector('[data-context-name="building_id"]')?.selectedOptions[0]?.textContent || 'تعریف فرمول شارژ');
            const nav = make('nav', 'charge-wizard__steps'); nav.setAttribute('aria-label', 'مراحل تعریف فرمول شارژ');
            const progress = make('div', 'charge-wizard__progress'); progress.setAttribute('aria-hidden', 'true');
            const panelHeading = make('h4', 'charge-wizard__title'); panelHeading.tabIndex = -1;
            const error = make('div', 'charge-wizard__error'); error.id = 'charge-error-' + ++sequence; error.setAttribute('role', 'alert'); error.hidden = true;
            panelHeading.id = 'charge-stage-title-' + sequence;
            const panels = labels.map((label, index) => { const el = make('section', 'charge-wizard__panel'); el.dataset.chargePanel = index; return el; });
            panels.forEach(panel => panel.setAttribute('aria-labelledby', panelHeading.id));
            const announcement = make('p', 'charge-sr-only'); announcement.setAttribute('role', 'status'); announcement.setAttribute('aria-live', 'polite');
            const categoryField = category.closest('label'), categoryState = make('div', 'charge-wizard__empty');
            const retry = make('button', 'crud-button crud-button--soft', 'دریافت دوباره دسته‌ها'); retry.type = 'button';
            const changeBuilding = make('button', 'crud-button crud-button--soft', 'تغییر ساختمان'); changeBuilding.type = 'button';
            const categoryActions = make('div', 'charge-wizard__empty-actions'); categoryActions.append(retry, changeBuilding);
            panels[0].append(categoryField, categoryState, categoryActions);
            const methods = $('.crud-formula-methods');
            methods.setAttribute('role', 'radiogroup'); methods.setAttribute('aria-label', 'روش تقسیم هزینه بین واحدها');
            if (!row) methods.querySelectorAll('input').forEach(input => input.checked = false);
            panels[1].append(methods);
            const parameterHint = make('div', 'charge-wizard__note'); panels[2].append(parameterHint, fixed, floor);
            const summary = make('dl', 'charge-wizard__review'), activation = make('div', 'charge-wizard__activation');
            panels[3].append(summary, activation, make('p', 'charge-wizard__hint', 'سامانه پیش از ذخیره، اطلاعات را اعتبارسنجی می‌کند. فعال‌بودن این فرمول به معنی صدور شارژ یا صورتحساب نیست.'));
            const discard = make('div', 'charge-wizard__discard'); discard.hidden = true; discard.setAttribute('role', 'alert');
            const keep = make('button', 'crud-button crud-button--primary', 'ادامه ویرایش'), leave = make('button', 'crud-button crud-button--soft', 'خروج بدون ذخیره'); keep.type = leave.type = 'button';
            discard.append(make('strong', '', 'تغییرات شما هنوز ذخیره نشده است.'), keep, leave);
            component.append(context, nav, progress, announcement, error, panelHeading, ...panels, discard);
            const back = make('button', 'crud-button crud-button--soft charge-wizard__back', 'مرحله قبل'); back.type = 'button';
            saveButton.parentElement.insertBefore(back, saveButton);
            const method = () => $('[data-formula-method]:checked');
            const isActive = () => activeInput?.checked ?? row?.is_active ?? true;
            const clearError = () => { error.hidden = true; error.textContent = ''; component.querySelectorAll('[aria-invalid="true"]').forEach(el => { el.removeAttribute('aria-invalid'); const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== error.id); if (ids.length) el.setAttribute('aria-describedby', ids.join(' ')); else el.removeAttribute('aria-describedby'); }); };
            const categoryAvailable = () => [...category.options].some(option => option.value);
            const categoryStatus = () => {
                const available = categoryAvailable(), failed = [...category.options].some(option => option.textContent.includes('خطا در دریافت'));
                categoryField.hidden = !available;
                if (titleInput) titleInput.closest('label').hidden = !available;
                categoryState.hidden = available; categoryActions.hidden = available;
                categoryState.replaceChildren(make('strong', '', failed ? 'دسته‌های هزینه دریافت نشدند' : 'این ساختمان دسته هزینهٔ فعالی ندارد'), make('p', '', failed ? 'اتصال را بررسی کنید و دوباره تلاش کنید؛ اطلاعات فرم حفظ می‌شود.' : 'برای تعریف فرمول، ابتدا باید دسته هزینهٔ فعال برای همین ساختمان موجود باشد. فعلاً امکان ایجاد دسته هزینه در داشبورد فراهم نیست.'));
                sync();
            };
            const rows = make('div', 'charge-floor-rows'); floor.append(rows);
            const add = make('button', 'crud-button crud-button--soft', '+ افزودن ضریب طبقه'); add.type = 'button'; floor.append(add);
            const addFloor = (floorNumber = '', coefficient = 1) => {
                const el = make('div', 'charge-floor-row');
                for (const [key, label, value] of [['floor', 'شماره طبقه', floorNumber], ['coefficient', 'ضریب سهم', coefficient]]) {
                    const wrapper = make('label', 'crud-field'), input = make('input'); input.type = 'number'; input.value = value; input.dir = 'ltr';
                    input.dataset.chargeFloorField = key; input.step = key === 'floor' ? '1' : 'any';
                    if (key === 'coefficient') input.min = '0';
                    wrapper.append(make('span', '', label), input); el.append(wrapper);
                }
                const remove = make('button', 'crud-button crud-button--soft', 'حذف'); remove.type = 'button'; remove.setAttribute('aria-label', 'حذف ضریب این طبقه');
                remove.addEventListener('click', () => { if (!busy) { dirty = true; el.remove(); add.focus(); } });
                el.append(remove); rows.append(el); return el;
            };
            const existing = row?.configuration?.floor_coefficients || {};
            if (Object.keys(existing).length) Object.entries(existing).forEach(([key, value]) => addFloor(key, value)); else addFloor(0, 1);
            const fail = (text, targetStep, input) => { const error = new Error(text); error.step = targetStep; error.field = input; throw error; };
            const synchronize = () => {
                if (method()?.value !== 'floor_coefficient') return;
                const values = [], used = new Set();
                if (!rows.children.length) fail('حداقل یک طبقه و ضریب آن را اضافه کنید.', 2, add);
                for (const el of rows.children) {
                    const floorInput = el.querySelector('[data-charge-floor-field="floor"]'), factorInput = el.querySelector('[data-charge-floor-field="coefficient"]');
                    const key = Number(floorInput.value), factor = Number(factorInput.value);
                    if (!floorInput.value.trim() || !Number.isSafeInteger(key)) fail('شماره طبقه را به‌صورت عدد صحیح وارد کنید؛ برای همکف صفر و برای زیرزمین عدد منفی.', 2, floorInput);
                    if (!factorInput.value.trim() || !Number.isFinite(factor) || factor <= 0) fail('ضریب سهم باید عددی بزرگ‌تر از صفر باشد.', 2, factorInput);
                    if (used.has(key)) fail('طبقه ' + number(key) + ' تکراری است؛ برای هر طبقه فقط یک ضریب وارد کنید.', 2, floorInput);
                    used.add(key); values.push([String(key), factor]);
                }
                coefficients.value = JSON.stringify(Object.fromEntries(values));
            };
            const validate = until => {
                if (!categoryAvailable() || !category.value) fail('برای ادامه، یک دسته هزینه انتخاب کنید.', 0, categoryAvailable() ? category : retry);
                if (!titleInput?.value.trim()) fail('عنوان فرمول را وارد کنید.', 0, titleInput);
                if (titleInput && !titleInput.checkValidity()) fail(titleInput.validationMessage, 0, titleInput);
                if (until >= 1 && !method()) fail('یک روش تقسیم هزینه انتخاب کنید.', 1, methods.querySelector('input'));
                if (until >= 2) {
                    const amount = $('[data-formula-fixed-amount]');
                    if (method()?.value === 'fixed' && (amount.value === '' || !Number.isSafeInteger(Number(amount.value)) || Number(amount.value) < 0)) fail('مبلغ ثابت هر واحد را با عدد صحیح صفر یا بیشتر وارد کنید.', 2, amount);
                    synchronize(); collect();
                }
            };
            const renderSummary = () => {
                const data = collect().builder; summary.replaceChildren();
                const entries = [
                    ['نوع هزینه', category.selectedOptions[0]?.textContent, 0], ['عنوان فرمول', titleInput?.value.trim(), 0],
                    ['روش تقسیم', method()?.closest('label').querySelector('b').textContent, 1],
                ];
                if (data.calculation_type === 'fixed') entries.push(['مبلغ ثابت هر واحد', number(data.fixed_amount) + ' ریال', 2]);
                else if (data.floor_coefficients) entries.push(['ضرایب طبقات', Object.entries(data.floor_coefficients).map(([key, value]) => 'طبقه ' + number(key) + ': ' + number(value)).join('؛ '), 2]);
                else entries.push(['پارامترها', 'این روش تنظیمات دستی اضافه ندارد.', 2]);
                for (const [label, text, target] of entries) {
                    const value = make('dd', '', text), edit = make('button', 'charge-wizard__edit', 'ویرایش'); edit.type = 'button'; edit.setAttribute('aria-label', 'ویرایش ' + label);
                    edit.addEventListener('click', () => navigate(target)); value.append(edit); summary.append(make('dt', '', label), value);
                }
            };
            function sync() {
                if (disposed) return;
                component.formulaBusy = busy;
                panels.forEach((panel, index) => panel.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = busy || index !== step));
                const type = method()?.value;
                fixed.hidden = type !== 'fixed'; floor.hidden = type !== 'floor_coefficient';
                fixed.querySelectorAll('input').forEach(el => el.disabled = busy || step !== 2 || type !== 'fixed');
                floor.querySelectorAll('input, textarea, button').forEach(el => el.disabled = busy || step !== 2 || type !== 'floor_coefficient');
                nav.querySelectorAll('button').forEach(el => el.disabled = busy);
                back.hidden = step === 0; back.disabled = busy;
                saveButton.disabled = busy || loadingCategories || step === 0 && !categoryAvailable();
                for (const id of ['crudDrawerClose', 'crudCancelButton']) { const button = document.getElementById(id); if (button) button.disabled = busy; }
                saveButton.textContent = busy ? 'در حال اعتبارسنجی و ذخیره…' : step < 3 ? ['ادامه: انتخاب روش', 'ادامه: تنظیم پارامترها', 'مرور تنظیمات'][step] : isActive() ? 'ذخیره و فعال‌سازی فرمول' : 'ذخیره فرمول غیرفعال';
                component.setAttribute('aria-busy', String(busy || loadingCategories));
                window.BuildinoSelect2?.refresh(category);
            }
            function show(next, focus = true) {
                step = next; component.dataset.formulaStep = String(step); discard.hidden = true;
                panels.forEach((panel, index) => panel.hidden = index !== step);
                [...nav.children].forEach((button, index) => { button.classList.toggle('is-complete', index < step); if (index === step) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current'); });
                panelHeading.textContent = headings[step]; progress.style.setProperty('--charge-progress', ((step + 1) * 25) + '%');
                announcement.textContent = `مرحله ${number(step + 1)} از ${number(labels.length)}: ${labels[step]}`;
                parameterHint.textContent = method()?.value === 'fixed' ? 'مبلغی که هر واحد در هر دوره باید بپردازد را وارد کنید. این عدد مبلغ کل ساختمان نیست.' : method()?.value === 'floor_coefficient' ? 'ضریب ۱ سهم مبناست؛ عدد بزرگ‌تر سهم بیشتری می‌دهد. همکف را صفر و زیرزمین را با شماره منفی وارد کنید.' : 'این روش پارامتر دستی ندارد. سامانه هزینه ثبت‌شدهٔ دوره را بر اساس روش انتخابی بین واحدها تقسیم می‌کند.';
                if (step === 3) renderSummary(); sync(); if (focus) panelHeading.focus();
            }
            const showFailure = failure => {
                if (Number.isInteger(failure.step)) show(failure.step, false);
                error.textContent = failure.message; error.hidden = false;
                const input = failure.field;
                if (input) { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', [...new Set([...(input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean), error.id])].join(' ')); input.focus(); }
                else { error.tabIndex = -1; error.focus(); }
            };
            function navigate(next) {
                if (busy || loadingCategories) return;
                clearError(); try { if (next > step) validate(next - 1); show(next); } catch (failure) { showFailure(failure); }
            }
            labels.forEach((label, index) => { const button = make('button'); button.type = 'button'; button.append(make('b', '', number(index + 1)), make('span', '', label)); button.addEventListener('click', () => navigate(index)); nav.append(button); });
            back.addEventListener('click', () => navigate(step - 1));
            add.addEventListener('click', () => { if (!busy) { dirty = true; addFloor().querySelector('input').focus(); } });
            retry.addEventListener('click', async () => {
                if (busy || loadingCategories) return; loadingCategories = true; sync(); retry.textContent = 'در حال دریافت…';
                try { await reloadCategories(); } finally { loadingCategories = false; if (!disposed) { retry.textContent = 'دریافت دوباره دسته‌ها'; categoryStatus(); } }
            });
            changeBuilding.addEventListener('click', () => {
                if (busy) return;
                document.getElementById('crudCancelButton')?.click();
                if (!document.getElementById('crudDrawer')?.classList.contains('is-open')) { document.querySelector('[data-charge-change-building]')?.click(); document.querySelector('[data-context-name="building_id"]')?.focus(); }
            });
            keep.addEventListener('click', () => { discard.hidden = true; panelHeading.focus(); });
            leave.addEventListener('click', () => { allowDiscard = true; document.getElementById('crudCancelButton')?.click(); });
            component.requestFormulaClose = () => {
                if (busy) return false;
                if (!dirty || allowDiscard) return true;
                discard.hidden = false; keep.focus(); return false;
            };
            component.markFormulaSaved = () => { dirty = false; };
            component.prepareFormulaSubmit = () => {
                if (busy || loadingCategories) return false;
                clearError(); try { validate(step); if (step < 3) { show(step + 1); return false; } return true; } catch (failure) { showFailure(failure); return false; }
            };
            component.syncFormulaFooter = sync;
            component.setFormulaBusy = value => { busy = value; sync(); };
            component.showFormulaErrors = (errors, fallback) => {
                busy = false;
                const key = Object.keys(errors || {})[0], text = Object.values(errors || {}).flat().join('؛ ') || fallback || 'اطلاعات ذخیره نشد؛ تنظیمات را بررسی و دوباره تلاش کنید.';
                const target = key?.includes('financial_category') ? 0 : key === 'title' ? 0 : key?.includes('calculation_type') ? 1 : key?.includes('fixed_amount') || key?.includes('floor_coefficients') || key === 'items' ? 2 : step;
                const field = key?.includes('financial_category') ? category : key === 'title' ? titleInput : key?.includes('fixed_amount') ? $('[data-formula-fixed-amount]') : null;
                showFailure({ message: text, step: target, field });
            };
            const changed = event => {
                if (busy || disposed) return; dirty = true; clearError();
                if (event.target === category && titleInput) {
                    if (!titleInput.value.trim() || titleInput.value === suggestedTitle) { suggestedTitle = category.value ? 'شارژ ' + category.selectedOptions[0].textContent : ''; titleInput.value = suggestedTitle; }
                }
                if (step === 3) renderSummary(); sync();
            };
            form.addEventListener('input', changed, { signal: abort.signal }); form.addEventListener('change', changed, { signal: abort.signal });
            form.addEventListener('buildino:form-mounted', () => {
                titleInput = form.elements.namedItem('title'); activeInput = form.elements.namedItem('is_active');
                if (titleInput) { titleInput.placeholder = 'مثلاً شارژ نظافت ساختمان'; panels[0].append(titleInput.closest('label')); }
                if (activeInput) activation.append(activeInput.closest('label'));
                activation.append(make('p', 'charge-wizard__hint', 'در حالت فعال، این قاعده در محاسبه دوره‌های ساختمان استفاده می‌شود.'));
                categoryStatus(); show(0, false);
                const drawerTitle = document.getElementById('crudDrawerTitle'); if (drawerTitle) drawerTitle.textContent = row ? 'ویرایش فرمول شارژ' : 'تعریف فرمول شارژ';
            }, { signal: abort.signal, once: true });
            window.addEventListener('beforeunload', event => { if (dirty && component.isConnected && !disposed) { event.preventDefault(); event.returnValue = ''; } }, { signal: abort.signal });
            form.chargeWizardDestroy = () => { disposed = true; abort.abort(); back.remove(); form.noValidate = originalValidation; };
            show(0, false); categoryStatus();
        },
    };
    const page = document.querySelector('.charge-formula-page');
    if (page) {
        const building = page.querySelector('[data-context-name="building_id"]');
        const locationPanel = page.querySelector('[data-charge-location]'), directory = page.querySelector('[data-charge-directory]');
        const drawer = document.getElementById('crudDrawer'), create = document.getElementById('crudCreateButton');
        const resume = page.querySelector('[data-charge-resume]');
        const show = (name, focus = false) => {
            locationPanel.hidden = name !== 'location'; directory.hidden = name !== 'directory';
            resume.hidden = !building.value;
            if (focus) page.querySelector(`#charge-${name}-title`)?.focus();
        };
        const update = (focus = false) => {
            const capability = window.BuildinoCrud?.formulaBuildings?.find(row => String(row.id) === building.value);
            if (create) { create.hidden = !capability?.canCreate; create.disabled = !capability?.canCreate; }
            page.querySelector('[data-charge-building-label]').textContent = (building.selectedOptions[0]?.textContent || '') + (capability && !capability.canCreate && !capability.canUpdate ? ' · دسترسی فقط مشاهده' : '');
            const periodLink = page.querySelector('[data-charge-period-link]');
            if (periodLink) { periodLink.hidden = !capability?.canViewPeriods; const url = new URL(periodLink.href, location.origin); if (building.value) url.searchParams.set('building_id', building.value); periodLink.href = url.href; }
            if (!drawer.classList.contains('is-open')) show(building.value ? 'directory' : 'location', focus);
        };
        building.addEventListener('change', () => { document.getElementById('crudSearch').value = ''; update(true); });
        page.querySelector('[data-charge-change-building]').addEventListener('click', () => show('location', true));
        resume.addEventListener('click', () => { show('directory', true); document.getElementById('crudRefreshButton').click(); });
        page.addEventListener('buildino:inline-editor', event => { if (event.detail.open) show('editor'); else update(); });
        update();
    }
    const create = document.getElementById('crudCreateButton'); if (create) create.textContent = '+ تعریف فرمول شارژ';
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-context-name="building_id"]')) return;
        const link = document.querySelector('[data-charge-period-link]'); if (!link) return;
        const url = new URL(link.href, location.origin);
        if (event.target.value) url.searchParams.set('building_id', event.target.value); else url.searchParams.delete('building_id'); link.href = url.href;
    });
})();
