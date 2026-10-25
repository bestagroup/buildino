import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

for (const width of [1280, 500]) test(`invitation workflow at ${width}px: validation, review, request, recovery and stale lists`, { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-invitation-'));
    const asset = name => pathToFileURL(join(project, 'public', name)).href;
    const local = name => pathToFileURL(join(project, name)).href;
    const select2 = readFileSync(join(project, 'resources/js/buildino-select2.js'), 'utf8').replace(/^import .*;\r?\n/gm, '');
    const markup = readFileSync(join(project, 'resources/views/management/partials/invitation-page.blade.php'), 'utf8')
        .replace(/@include\([^\n]*?\)/g, '').replace(/@if\([^\n]*?\)\)/g, '').replace(/@endif/g, '');
    const fixture = join(directory, 'fixture.html');
    writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="${asset('css/buildino-fonts.css')}"><link rel="stylesheet" href="${asset('css/buildino-management.css')}"><link rel="stylesheet" href="${asset('css/buildino-crud.css')}"><link rel="stylesheet" href="${asset('css/buildino-unit-ownerships.css')}"><link rel="stylesheet" href="${asset('css/buildino-design-system.css')}"><link rel="stylesheet" href="${asset('css/buildino-invitations.css')}">
    <link rel="stylesheet" href="${local('node_modules/select2/dist/css/select2.css')}"><link rel="stylesheet" href="${local('resources/css/buildino-select2.css')}">
    <div id="buildinoCrudApp" class="crud-page ownership-page invitation-page" style="margin-block-start:100px">${markup}
    <aside id="crudDrawer" class="crud-drawer invitation-editor" data-inline-editor hidden aria-hidden="true"><div class="crud-drawer__header"><div><span id="crudDrawerEyebrow"></span><h3 id="crudDrawerTitle"></h3></div><button id="crudDrawerClose" class="crud-close-button" type="button">بازگشت</button></div><form id="crudForm" class="crud-form"><input id="crudRecordId" type="hidden"><div id="crudFormFields" class="crud-form-grid"></div><div id="crudFormError"></div><div class="crud-form-actions"><button id="crudCancelButton" class="crud-button crud-button--soft" type="button">انصراف</button><button id="crudSaveButton" class="crud-button crud-button--primary" type="submit">ادامه</button></div></form></aside><div id="crudToastStack" aria-live="polite"></div></div><pre id="result" hidden></pre>
    <script>
    let empty = false, failLookup = false, failSave = false, holdSave = false, releaseSave, holdList = false, releaseList, posts = [];
    window.BuildinoCoreFlows = { allowed: () => true, parentValue: () => null, mount: async () => {}, defaults: () => ({}), enhanceForm: async () => {}, contextChanged: () => {}, setRows: () => {}, showErrors: () => {}, saved: () => {}, labels: { invitations: 'دعوت' } };
    window.BuildinoCrud = { resourceKey: 'invitations', lookupBase: 'http://localhost/management/lookups', resource: {
        title: 'دعوت کاربران', context: [{ name: 'unit_id', label: 'واحد', required: true }],
        list: { url: '/api/v1/units/{unit_id}/invitations' }, create: { url: '/api/v1/units/{unit_id}/invitations', method: 'POST' },
        columns: [{ key: 'mobile', label: 'موبایل' }, { key: 'status', label: 'وضعیت' }],
        fields: [{ name: 'user_id', label: 'دریافت‌کننده', type: 'select', lookup: 'invitable_users', required: true, auto_select_single: true }, { name: 'expires_in_hours', label: 'اعتبار', type: 'number', default: 72, required: true }],
        actions: [{ key: 'resend', title: 'ارسال مجدد', method: 'POST', url: '/api/v1/unit-invitations/{id}/resend' }, { key: 'cancel', title: 'لغو دعوت', method: 'POST', url: '/api/v1/unit-invitations/{id}/cancel' }]
    } };
    window.fetch = async (path, options = {}) => {
        const url = new URL(path, 'http://localhost');
        const reply = (data, status = 200) => new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } });
        if (url.pathname.endsWith('/lookups/units')) return reply({ data: [{ id: 1, label: 'واحد ۱۰۱' }, { id: 2, label: 'واحد ۲۰۲' }] });
        if (url.pathname.endsWith('/lookups/invitable_users')) return failLookup ? reply({ message: 'خطای اتصال' }, 503) : reply({ data: empty ? [] : [{ id: 9, label: 'علی آزمایشی — 09121111111' }] });
        if (options.method === 'POST') {
            posts.push({ path: url.pathname, body: JSON.parse(options.body) });
            if (holdSave) await new Promise(resolve => releaseSave = resolve);
            return failSave ? reply({ message: 'دعوت فعال دیگری وجود دارد.', errors: { user_id: ['دعوت تکراری'] } }, 422) : reply({ data: [], meta: { sent_count: 1 } }, 201);
        }
        if (holdList && url.pathname.includes('/units/1/')) await new Promise(resolve => releaseList = resolve);
        return reply({ data: [{ id: url.pathname.includes('/units/1/') ? 11 : 22, mobile: url.pathname.includes('/units/1/') ? 'unit-one' : 'unit-two', status: 'sent', expires_at: '2099-01-01' }, { id: 33, status: 'accepted' }, { id: 44, status: 'expired' }], meta: {} });
    };
    </script><script src="${local('node_modules/jquery/dist/jquery.js')}"></script><script src="${local('node_modules/select2/dist/js/select2.full.js')}"></script><script>(() => { const $ = window.jQuery; ${select2} })();</script><script src="${asset('js/buildino-invitations.js')}"></script><script src="${asset('js/buildino-crud.js')}"></script><script>
    const $ = selector => document.querySelector(selector), app = $('#buildinoCrudApp'), form = $('#crudForm');
    const until = async predicate => { for (let i = 0; i < 100; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 10)); } throw Error('Timed out: ' + predicate + ' / ' + $('#crudState').textContent + ' / ' + $('#crudFormError').textContent); };
    const expect = (value, message) => { if (!value) throw Error(message); };
    const only = page => expect(['location', 'directory'].filter(key => !$('[data-invitation-' + key + ']').hidden).concat(!$('#crudDrawer').hidden ? ['editor'] : []).join() === page, 'Wrong visible panel: ' + page);
    const submit = () => form.dispatchEvent(new Event('submit', { cancelable: true }));
    const unit = $('[data-context-name="unit_id"]');
    const selectUnit = id => { unit.value = id; unit.dispatchEvent(new Event('change', { bubbles: true })); };
    const open = async () => { $('#crudCreateButton').click(); await until(() => $('.invitation-step-title')); only('editor'); };
    (async () => {
        await until(() => app.dataset.coreReady); only('location');
        selectUnit('1'); await until(() => $('#crudTableBody').textContent.includes('unit-one')); only('directory');
        expect($('#crudTableBody').querySelectorAll('[data-special-action]').length === 2, 'Accepted or expired invitations expose actions');
        await open(); expect(form.elements.user_id.value === '9', 'Single recipient not selected');
        await until(() => window.jQuery(form.elements.user_id).hasClass('select2-hidden-accessible'));
        window.jQuery(form.elements.user_id).select2('open');
        const control = window.jQuery(form.elements.user_id).data('select2').$container[0].getBoundingClientRect();
        const dropdown = document.querySelector('.select2-dropdown').getBoundingClientRect();
        expect(Math.min(Math.abs(dropdown.top - control.bottom), Math.abs(dropdown.bottom - control.top)) < 8 && Math.abs(dropdown.left - control.left) < 8, 'Recipient dropdown is detached from its field');
        window.jQuery(form.elements.user_id).select2('close');
        form.elements.expires_in_hours.value = '0'; submit(); expect($('.invitation-review').hidden && posts.length === 0, 'Invalid validity advanced');
        form.elements.expires_in_hours.value = '72'; submit(); expect(!$('.invitation-review').hidden && posts.length === 0, 'Review must precede request');
        expect($('.invitation-review').textContent.includes('علی آزمایشی'), 'Review missing recipient');
        failSave = true; submit(); await until(() => $('#crudFormError').textContent.includes('دعوت فعال'));
        only('editor'); expect(form.elements.user_id.value === '9' && $('.invitation-review').hidden, 'Error lost data or failed to return to fields');
        failSave = false; holdSave = true; submit(); submit(); await until(() => releaseSave); const before = posts.length; submit(); $('#crudDrawerClose').click(); only('editor'); expect(posts.length === before, 'Duplicate submit');
        releaseSave(); await until(() => $('#crudDrawer').hidden); only('directory');
        expect(posts[0].body.user_id === '9' && !('user_ids' in posts[0].body) && posts[0].body.expires_in_hours === 72, 'Wrong actual request payload');
        empty = true; await open(); expect($('#crudSaveButton').disabled && $('#crudFormFields').textContent.includes('واجد شرایط'), 'Empty recipients not explained'); $('#crudCancelButton').click();
        empty = false; failLookup = true; await open(); expect($('#crudSaveButton').disabled && $('#crudFormFields').textContent.includes('دریافت نشد'), 'Lookup failure not explained');
        failLookup = false; $('#crudFormFields .invitation-note button').click(); await until(() => $('#crudFormFields select')?.value === '9'); expect(!$('#crudSaveButton').disabled, 'Retry did not recover'); $('#crudCancelButton').click();
        holdList = true; $('#crudRefreshButton').click(); await until(() => releaseList); $('[data-invitation-change-unit]').click(); only('location'); selectUnit('2'); await until(() => $('#crudTableBody').textContent.includes('unit-two')); releaseList(); await new Promise(resolve => setTimeout(resolve, 30)); expect(!$('#crudTableBody').textContent.includes('unit-one'), 'Stale list replaced current unit');
        expect(document.documentElement.scrollWidth <= innerWidth, 'Page overflows viewport');
        await open(); submit(); expect(document.documentElement.scrollWidth <= innerWidth, 'Review overflows viewport');
        $('#result').dataset.ok = 'true'; $('#result').textContent = 'Passed';
    })().catch(error => { $('#result').textContent = error.stack; });
    </script></html>`);
    try {
        const screenshot = process.env.INVITATION_SCREENSHOTS ? ['--screenshot=' + join(tmpdir(), 'buildino-invitation-' + width + '.png')] : [];
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--allow-file-access-from-files', '--no-first-run', '--force-device-scale-factor=1', '--user-data-dir=' + join(directory, 'profile'), '--window-size=' + width + ',960', '--virtual-time-budget=5000', '--dump-dom', ...screenshot, pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0] || output.slice(-1500);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-invitation-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
