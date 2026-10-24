import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('P12 browser: empty context, staged validation, correction, payload and busy state', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-p12-'));
    const source = readFileSync(join(project, 'public/js/buildino-crud.js'), 'utf8');
    const builder = source.slice(source.indexOf('    let formulaBuilderSequence'), source.indexOf('    const createFieldElement'));
    const asset = name => pathToFileURL(join(project, 'public', name)).href;
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="${asset('css/buildino-management.css')}">
    <link rel="stylesheet" href="${asset('css/buildino-crud.css')}">
    <link rel="stylesheet" href="${asset('css/buildino-charge-formulas.css')}">
    <select data-context-name="building_id"><option value="4">ساختمان دمو ۰۱</option></select>
    <aside class="crud-drawer charge-formula-dialog is-open" id="crudDrawer">
    <div class="crud-drawer__header"><h3 id="crudDrawerTitle">تعریف فرمول شارژ</h3></div>
    <form id="crudForm" class="crud-form"><div id="fields" class="crud-form-grid"></div>
    <div class="crud-form-actions"><button type="button" id="crudCancelButton" class="crud-button">انصراف</button><button type="submit" id="save" class="crud-button crud-button--primary">ادامه</button></div></form></aside>
    <pre id="result" hidden></pre>
    <script src="${asset('js/buildino-charge-formula-ui.js')}"></script>
    <script>
    const form = document.getElementById('crudForm'), fields = document.getElementById('fields'), saveButton = document.getElementById('save');
    const elements = { form, saveButton };
    const lookupCache = new Map();
    const $ = (selector, root = document) => root.querySelector(selector), $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const escapeHtml = value => { const el = document.createElement('span'); el.textContent = String(value); return el.innerHTML; };
    let categories = true, component, saved = null, checks = 0;
    async function loadLookupOptions(select, field, selected) { select.replaceChildren(new Option('انتخاب کنید', '')); if (categories) select.add(new Option('نظافت ساختمان', '7')); select.value = String(selected || ''); }
    ${builder}
    const expect = (condition, message) => { if (!condition) throw new Error(message); checks++; };
    form.addEventListener('submit', event => { event.preventDefault(); if (component.prepareFormulaSubmit()) saved = collectChargeFormulaBuilder(component); });
    const build = async (row = null) => {
        form.chargeWizardDestroy?.(); fields.replaceChildren();
        const title = document.createElement('label'); title.className = 'crud-field'; title.innerHTML = '<span>عنوان فرمول</span><input name="title" required maxlength="255">'; fields.append(title);
        title.querySelector('input').value = row?.title || '';
        component = await createChargeFormulaBuilder({ name: 'builder', label: 'قاعده محاسبه' }, row); fields.append(component);
        const active = document.createElement('label'); active.className = 'crud-field'; active.innerHTML = '<span><input name="is_active" type="checkbox" checked> فعال در محاسبات</span>'; fields.append(active);
        active.querySelector('input').checked = row?.is_active ?? true;
        form.dispatchEvent(new CustomEvent('buildino:form-mounted', { bubbles: true }));
    };
    const choose = value => { const el = component.querySelector('[data-formula-method][value="' + value + '"]'); el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); };
    const step = () => component.dataset.formulaStep;
    (async () => {
        categories = false; await build();
        expect(saveButton.disabled, 'Empty category list must block advance');
        expect(!component.querySelector('.charge-wizard__empty').hidden, 'Empty state must explain missing data');
        categories = true; component.querySelector('.charge-wizard__empty-actions button').click();
        await new Promise(resolve => setTimeout(resolve, 0));
        expect(!saveButton.disabled && component.querySelector('.charge-wizard__empty').hidden, 'Retry should recover category lookup without reopening form');
        const category = component.querySelector('[data-formula-category]'); category.value = '7'; category.dispatchEvent(new Event('change', { bubbles: true }));
        expect(form.elements.title.value === 'شارژ نظافت ساختمان', 'Title suggestion should be editable');
        saveButton.click(); expect(step() === '1', 'Category advances to method');
        saveButton.click(); expect(step() === '1', 'No implicit method for new formulas');
        choose('floor_coefficient'); saveButton.click(); expect(step() === '2', 'Method advances to parameters');
        const floor = component.querySelector('[data-formula-floor-wrap]');
        floor.querySelector(':scope > button').click();
        let rows = component.querySelectorAll('.charge-floor-row');
        rows[1].querySelector('input').value = '0'; saveButton.click();
        expect(step() === '2' && !component.querySelector('.charge-wizard__error').hidden, 'Duplicate floor must stay editable');
        rows[1].querySelector('input').value = '1'; rows[1].querySelectorAll('input')[1].value = '0'; saveButton.click();
        expect(step() === '2', 'Zero coefficient must be rejected');
        rows[1].querySelectorAll('input')[1].value = '1.25'; saveButton.click();
        expect(step() === '3', 'Corrected parameters must reach review');
        expect(component.querySelector('[name="is_active"]').closest('[data-charge-panel]').dataset.chargePanel === '3', 'Activation belongs to final review');
        saveButton.click(); expect(saved.builder.floor_coefficients['1'] === 1.25, 'Submitted payload must keep entered coefficient');
        component.setFormulaBusy(true); expect(!component.requestFormulaClose(), 'Do not close while saving');
        expect([...component.querySelectorAll('input')].every(input => input.disabled), 'Inputs must be locked while saving');
        component.setFormulaBusy(false);
        component.showFormulaErrors({ 'builder.financial_category_id': ['دسته هزینه تکراری است.'] });
        expect(step() === '0' && category.value === '7', 'Server error returns to visible field without clearing it');
        component.querySelectorAll('.charge-wizard__steps button')[1].click(); choose('area'); saveButton.click();
        expect(floor.hidden, 'Area method must not show floor controls'); saveButton.click();
        expect(step() === '3', 'A method without manual parameters can be reviewed');
        expect(!component.requestFormulaClose(), 'Unsaved edits need inline confirmation');
        component.querySelector('.charge-wizard__discard button').click();
        expect(component.querySelector('.charge-wizard__discard').hidden, 'Continue editing dismisses confirmation');
        const dialog = document.getElementById('crudDrawer');
        expect(dialog.scrollWidth <= dialog.clientWidth, 'Dialog must not overflow horizontally');
        await build({ id: 5, title: 'قاعده اختصاصی نظافت', calculation_type: 'fixed', is_active: false, items: [{ financial_category_id: 7, base_amount: 300000 }] });
        const editCategory = component.querySelector('[data-formula-category]'); editCategory.dispatchEvent(new Event('change', { bubbles: true }));
        expect(form.elements.title.value === 'قاعده اختصاصی نظافت', 'Editing must preserve custom saved title');
        component.querySelectorAll('.charge-wizard__steps button')[3].click();
        expect(step() === '3' && !form.elements.is_active.checked, 'Editing must preserve inactive status and allow review');
        expect(collectChargeFormulaBuilder(component).builder.fixed_amount === 300000, 'Editing must preserve the saved fixed tariff');
        component.markFormulaSaved();
        const result = document.getElementById('result'); result.dataset.ok = 'true'; result.textContent = JSON.stringify({ checks });
    })().catch(error => { document.getElementById('result').textContent = error.stack; });
    </script></html>`;
    const fixture = join(directory, 'fixture.html'); writeFileSync(fixture, html);
    try {
        const args = ['--headless', '--disable-gpu', '--no-sandbox', '--allow-file-access-from-files', '--no-first-run', '--hide-scrollbars', '--force-device-scale-factor=1', '--user-data-dir=' + join(directory, 'profile'), '--window-size=' + (process.env.P12_MOBILE ? '500,844' : '1280,960'), '--virtual-time-budget=3000', '--dump-dom'];
        if (process.env.P12_SCREENSHOT) args.push('--screenshot=' + resolve(process.env.P12_SCREENSHOT));
        const output = execFileSync(chrome, [...args, pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0] || output.slice(-1500);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        // This directory is created by this test directly inside the OS temp directory.
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-p12-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
