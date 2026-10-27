import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import { promisify } from 'node:util';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('building structure: real Vue forms, hierarchy, permissions, validation, errors and responsive layout', { skip: !existsSync(chrome) }, async () => {
    const configs = JSON.parse(execFileSync('php', ['-r', '$resources = (require "config/management_crud.php")["resources"]; echo json_encode(array_intersect_key($resources, array_flip(["complexes","buildings","blocks","floors","units"])));'], { cwd: project, encoding: 'utf8' }));
    const crud = readFileSync(join(project, 'public/js/buildino-crud.js'), 'utf8');
    const checkboxFactory = crud.slice(crud.indexOf('    const createFieldElement'), crud.indexOf('    const renderFields'));
    const bundle = await build({
        absWorkingDir: project, bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { contents: `import {createApp} from 'vue'; import Page from './resources/js/pages/management/operations/Resource.vue'; import {asMapPoint} from './resources/js/lib/location-picker'; window.asMapPoint = asMapPoint; let app; window.mountStructure = props => { app?.unmount(); app = createApp(Page, props); app.mount('#app'); };`, resolveDir: project },
        plugins: [{ name: 'vue-fixture', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'style-fixture' }));
            builder.onLoad({ filter: /.*/, namespace: 'style-fixture' }, () => ({ contents: '', loader: 'js' }));
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'fixture' }));
            builder.onLoad({ filter: /.*/, namespace: 'fixture' }, () => ({ contents: 'export const Head = {render:()=>null};' }));
            builder.onLoad({ filter: /\.vue$/ }, args => {
                const { descriptor } = parse(readFileSync(args.path, 'utf8'), { filename: args.path });
                return { contents: compileScript(descriptor, { id: 'structure-test-' + args.path.split('/').pop(), inlineTemplate: true, fs: { fileExists: existsSync, readFile: path => readFileSync(path, 'utf8') } }).content, loader: 'ts' };
            });
        } }],
    });
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    ${['/public/css/buildino-fonts.css','/public/css/buildino-management.css','/public/css/buildino-crud.css','/public/css/buildino-design-system.css','/public/css/buildino-unit-ownerships.css','/resources/css/management-inertia.css','/public/css/buildino-building-structure.css','/node_modules/leaflet/dist/leaflet.css','/resources/css/location-picker.css'].map(path => '<link rel="stylesheet" href="' + path + '">').join('')}
    <script type="application/json" id="buildino-map-config">{"tileUrl":"/test-tiles/{z}/{x}/{y}.svg","attribution":"Local test tiles","center":[32.4,53.7],"zoom":5,"maxZoom":19}</script>
    <div id="app" style="padding:16px"></div><pre id="result" hidden></pre>
    <script>/* Keep animation-frame scheduling deterministic under Chrome's virtual clock. */ window.requestAnimationFrame = callback => setTimeout(() => callback(performance.now()), 16); window.cancelAnimationFrame = clearTimeout;</script>
    <script src="/test.js"></script><script>
    window.makeLegacySwitch = async (mode = 'create') => { const escapeHtml = value => String(value); ${checkboxFactory} return createFieldElement({ name: 'is_active', label: 'فعال', type: 'checkbox', default: true, readonly_on_edit: true }, null, mode); };
    const configs = ${JSON.stringify(configs)}, keys = Object.keys(configs), abilities = { create: true, update: true, delete: true, childView: true };
    let posts = [], requests = [], failSave = false, holdSave = false, releaseSave, holdList = false, releaseList, activeKey = 'buildings';
    let geoMode = 'denied', geoRelease; Object.defineProperty(navigator, 'geolocation', { configurable: true, value: { getCurrentPosition: (success, error) => { if (geoMode === 'pending') { geoRelease = () => success({ coords: { latitude: 35.7, longitude: 51.4 } }); return; } setTimeout(() => geoMode === 'denied' ? error({ code: 1 }) : success({ coords: { latitude: 35.7, longitude: 51.4 } }), 0); } } });
    const samples = {
        buildings: { id: 11, complex_id: 4, code: 'B-1', title: 'ساختمان آزمایشی', is_active: true, complex: { title: 'مجتمع بهار' }, location: { address: 'نشانی قبلی', latitude: 12.5, longitude: 45.5 }, statistics: { parking_count: 4, storage_count: 2 } },
        complexes: { id: 11, code: 'C-1', title: 'مجتمع بهار', is_active: true, location: { province: 'تهران', city: 'تهران', address: 'نشانی مجتمع' } },
        blocks: { id: 11, title: 'بلوک اصلی', is_active: true }, floors: { id: 11, floor_number: 0, title: 'همکف' },
        units: { id: 11, unit_number: '۱۰۱', title: 'واحد نمونه', area: 75.5, usage_type: 'residential', is_active: true },
    };
    window.fetch = async (path, options = {}) => {
        const url = new URL(path, location.origin); requests.push(url.pathname + url.search);
        const reply = (payload, status = 200) => new Response(JSON.stringify(payload), { status, headers: { 'Content-Type': 'application/json' } });
        if (options.method === 'POST' || options.method === 'PATCH') {
            posts.push({ path: url.pathname, body: JSON.parse(options.body), method: options.method });
            if (holdSave) await new Promise(resolve => releaseSave = resolve);
            if (failSave) return reply({ message: 'کد تکراری است.', errors: { code: ['کد تکراری است.'] } }, 422);
            return reply({ data: samples[activeKey] }, 201);
        }
        if (holdList && url.pathname.includes('/1/')) await new Promise(resolve => releaseList = resolve);
        return reply({ data: [{ ...samples[activeKey], title: url.pathname.includes('/2/') ? 'محدوده دوم' : samples[activeKey].title }], meta: { current_page: 1, last_page: 1, total: 1 } });
    };
    const props = key => ({ resourceKey: key, resource: configs[key], groupTitle: 'ساختار ساختمان', operationsUrl: '/management/operations', lookupBase: '/management/lookups', csrfToken: 'test', siblingResources: keys.map(key => ({ key, title: configs[key].title, url: '/management/operations/' + key, inertia: true })), structureUi: {
        contexts: ['blocks','floors','units'].includes(key) ? [1,2].map(id => ({ id, label: 'ساختمان ' + id + ' / محدوده ' + id, building_id: id, block_id: id, complex_id: 4, ...abilities })) : [],
        abilities: { 11: abilities }, globalAbilities: abilities, parentAbilities: { 4: abilities }, canCreate: true, createParents: [{ value: 4, label: 'مجتمع بهار' }], canSetup: true, setupUrl: '/management/building-setup',
        locations: [{ name: 'تهران', cities: [{ name: 'تهران', label: 'تهران' }] }, { name: 'اصفهان', cities: [{ name: 'کاشان', label: 'کاشان' }] }],
    } });
    const $ = selector => document.querySelector(selector), until = async predicate => { for (let i=0; i<150; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve,10)); } throw Error('Timed out: ' + predicate + ' / overlays: ' + [...document.querySelectorAll('.ui-drawer-layer')].map(el => el.className + ' ' + el.querySelector('[role="dialog"]')?.inert).join(' | ') + ' / ' + $('#app').textContent); };
    const expect = (value, message) => { if (!value) throw Error(message); };
    const input = async (name, value) => { const el = $('[name="' + name + '"]'); el.value = value; el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true })); await tick(); };
    const submit = () => $('#structure-form').dispatchEvent(new Event('submit', { cancelable: true }));
    const tick = () => new Promise(resolve => setTimeout(resolve, 10));
    const mount = async (key, query = '') => { activeKey = key; history.replaceState({}, '', '/management/operations/' + key + query); window.mountStructure(props(key)); await tick(); };
    (async () => {
        expect(window.asMapPoint(0, 0)?.latitude === 0 && window.asMapPoint(91, 0) === null && window.asMapPoint('', 0) === null && window.asMapPoint(null, null) === null, 'Coordinate boundary validation failed');
        await mount('buildings'); await until(() => $('[data-structure-edit]'));
        expect(!$('.crud-sibling-nav') && !$('.ui-drawer'), 'Legacy tabs or modal remain');
        expect($('[data-structure-child]').href.includes('building_id=11'), 'Building context missing from child link');
        $('[data-structure-add]').click(); await tick(); expect(!$('.structure-record') && $('.structure-editor'), 'Editor must replace list');
        submit(); await tick(); expect($('[aria-invalid="true"]') && posts.length === 0, 'Required fields bypassed');
        await input('title','ساختمان جدید'); await input('code','B-2'); submit(); await tick();
        await input('address','نشانی جدید');
        expect(!$('[name="latitude"]') && !$('[name="longitude"]') && $('[data-location-open]'), 'Coordinate inputs were not replaced');
        $('[data-location-open]').click(); await tick(); await until(() => $('[data-location-center]') && !$('[data-location-center]').disabled);
        expect($('[data-location-confirm]').disabled, 'Default map center was accepted without selection');
        const mapBounds = $('.location-dialog__map').getBoundingClientRect();
        $('.location-dialog__map').dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: mapBounds.left + mapBounds.width * .55, clientY: mapBounds.top + mapBounds.height * .55 }));
        await until(() => $('.location-marker')); expect(!$('[data-location-confirm]').disabled, 'Clicking map did not select a point');
        $('[data-location-cancel]').click(); await until(() => !$('.ui-drawer-layer')); expect(!$('.location-picker__clear'), 'Cancel committed draft coordinates');
        $('[data-location-open]').click(); await tick(); await until(() => $('[data-location-center]') && !$('[data-location-center]').disabled); $('.location-dialog__map').focus(); $('.location-dialog__map').dispatchEvent(new KeyboardEvent('keydown', { key:'Enter', bubbles:true })); await tick(); expect(!$('[data-location-confirm]').disabled, 'Keyboard did not select map center: ' + $('.location-dialog__selection').textContent); $('[data-location-confirm]').click(); await until(() => !$('.ui-drawer-layer')); expect($('.location-picker__clear'), 'Keyboard selection was not committed');
        const switchInput = $('[role="switch"]'), switchRow = switchInput.closest('.buildino-switch');
        expect(switchRow.getBoundingClientRect().height <= 50 && switchRow.getBoundingClientRect().height >= 44, 'Switch must be compact with a usable click target');
        switchInput.focus(); expect(document.activeElement === switchInput, 'Switch is not keyboard focusable');
        switchRow.click(); await tick(); expect(!switchInput.checked && getComputedStyle(switchRow.querySelector('.buildino-switch__on')).display === 'none', 'Switch state is not synchronized');
        submit(); await tick(); expect($('.structure-review') && posts.length === 0, 'Review missing');
        failSave = true; submit(); await until(() => $('.structure-error')); expect($('[name="code"]').value === 'B-2', 'Validation error lost form data');
        failSave = false; submit(); await tick(); submit(); await tick(); holdSave = true; submit(); await until(() => releaseSave); const count = posts.length; submit(); expect(posts.length === count, 'Duplicate save'); releaseSave(); await until(() => $('[data-structure-edit]'));
        expect(posts.at(-1).body.address === 'نشانی جدید' && posts.at(-1).body.complex_id === 4 && posts.at(-1).body.is_active === false, 'Wrong building request or switch value'); holdSave = false;
        $('[data-structure-edit]').click(); await tick(); submit(); await tick(); expect($('[name="address"]').value === 'نشانی قبلی', 'Nested edit address lost');
        submit(); await tick(); submit(); await until(() => $('[data-structure-edit]')); expect(posts.at(-1).body.parking_count === 4 && posts.at(-1).body.latitude === 12.5 && posts.at(-1).body.longitude === 45.5 && posts.at(-1).method === 'PATCH', 'Edit changed nested values');
        $('[data-structure-edit]').click(); await tick(); submit(); await tick();
        $('[data-location-open]').click(); await tick(); await until(() => $('[data-location-center]') && !$('[data-location-center]').disabled);
        expect($('.location-dialog__selection').textContent.includes('12.500000, 45.500000'), 'Saved location not restored: ' + $('.location-dialog__selection').textContent + ' / ' + $('.location-picker__open').textContent);
        $('[data-location-locate]').click(); await until(() => $('.location-picker__error')?.textContent.includes('مجاز نیست'));
        expect($('.location-dialog__selection').textContent.includes('12.500000'), 'Denied geolocation changed draft');
        geoMode = 'pending'; $('[data-location-locate]').click(); await until(() => geoRelease); $('[data-location-center]').click(); await tick(); const manualPoint = $('.location-dialog__selection').textContent; geoRelease(); await tick(); expect($('.location-dialog__selection').textContent === manualPoint, 'Late GPS response replaced manual selection');
        geoMode = 'allowed'; $('[data-location-locate]').click(); await until(() => $('.location-dialog__selection').textContent.includes('35.700000') && !$('[data-location-confirm]').disabled); $('[data-location-confirm]').click(); await until(() => !$('.ui-drawer-layer'));
        submit(); await tick(); submit(); await until(() => $('[data-structure-edit]')); expect(posts.at(-1).body.latitude === 35.7 && posts.at(-1).body.longitude === 51.4, 'Geolocation not stored atomically');
        $('[data-structure-edit]').click(); await tick(); submit(); await tick();
        $('.location-picker__clear').click(); await tick(); expect(document.activeElement === $('[data-location-open]'), 'Clear lost keyboard focus'); submit(); await tick(); submit(); await until(() => $('[data-structure-edit]')); expect(posts.at(-1).body.latitude === null && posts.at(-1).body.longitude === null, 'Clearing location was converted to zero');
        const mapConfig = JSON.parse($('#buildino-map-config').textContent); $('#buildino-map-config').textContent = JSON.stringify({ ...mapConfig, tileUrl: '/test-tiles-fail/{z}/{x}/{y}.svg' });
        $('[data-structure-edit]').click(); await tick(); submit(); await tick(); $('[data-location-open]').click(); await tick(); await until(() => $('[data-location-retry]'));
        expect($('[data-location-confirm]').disabled && $('.location-dialog__error'), 'Map failure allows confirming an unseen location');
        $('#buildino-map-config').textContent = JSON.stringify(mapConfig); $('[data-location-retry]').click(); await until(() => !$('[data-location-center]').disabled);
        const dialogBounds = $('.ui-drawer--dialog').getBoundingClientRect(); expect(dialogBounds.left >= 0 && dialogBounds.right <= innerWidth, 'Map dialog overflows viewport');
        $('[data-location-cancel]').click(); await until(() => !$('.ui-drawer-layer')); submit(); await tick(); submit(); await until(() => $('[data-structure-edit]'));
        await mount('complexes'); await until(() => $('[data-structure-edit]')); $('[data-structure-edit]').click(); await tick(); submit(); await tick();
        expect($('[name="city"]').value === 'تهران', 'Existing city lost'); await input('province','اصفهان'); await tick(); expect($('[name="city"]').value === '', 'City did not reset with province'); submit(); await tick(); expect(!$('.structure-review'), 'City validation skipped'); await input('city','کاشان'); submit(); await tick(); submit(); await until(() => $('[data-structure-edit]')); expect(posts.at(-1).body.city === 'کاشان', 'City name not submitted');
        for (const key of ['blocks','floors','units']) {
            const before = requests.length; await mount(key); expect(!$('[data-structure-add]') && requests.length === before, 'Unselected context issued request');
            { const el = $('.structure-page select'); el.value = '1'; el.dispatchEvent(new Event('change', { bubbles: true })); }
            await until(() => $('[data-structure-edit]')); expect(!requests.at(-1).includes('{'), 'Context placeholder not interpolated');
            if (key !== 'units') expect($('[data-structure-child]').href.includes(key === 'blocks' ? 'block_id=11' : 'floor_id=11'), 'Child scope missing');
            $('[data-structure-add]').click(); await tick();
            if (key === 'blocks') await input('title','بلوک جدید');
            if (key === 'floors') await input('floor_number','-1');
            if (key === 'units') { await input('unit_number','۱۰۲'); await input('area','75.5'); await input('usage_type','residential'); }
            submit(); await tick(); expect($('.structure-review'), 'Child form cannot advance: ' + key); submit(); await until(() => $('[data-structure-edit]')); expect(posts.at(-1).path.includes('/1/'), 'Saved under wrong parent');
        }
        await mount('blocks','?building_id=1'); await until(() => $('[data-structure-edit]'));
        holdList = true; $('.structure-tools button').click(); await until(() => releaseList); const select = $('.structure-page select'); select.value = '2'; select.dispatchEvent(new Event('change', { bubbles: true })); await until(() => $('.structure-record h4')?.textContent.includes('دوم')); releaseList(); await tick(); expect($('.structure-record h4').textContent.includes('دوم'), 'Stale response replaced new scope');
        const limited = props('blocks'); limited.structureUi.contexts[0] = { ...limited.structureUi.contexts[0], create:false, update:false, delete:false }; history.replaceState({},'', '/management/operations/blocks?building_id=1'); holdList = false; window.mountStructure(limited); await until(() => $('.structure-record')); expect(!$('[data-structure-add]') && !$('[data-structure-edit]'), 'Read-only context exposed write actions');
        await mount('buildings'); await until(() => $('[data-structure-edit]')); $('[data-structure-edit]').click(); await tick(); await input('title','تغییر ذخیره‌نشده'); $('.structure-toolbar button').click(); await tick(); expect($('.structure-discard'), 'Unsaved changes discarded silently');
        expect(document.documentElement.scrollWidth <= innerWidth, 'Layout overflow');
        const legacy = await window.makeLegacySwitch(); document.body.append(legacy);
        const legacyInput = legacy.querySelector('input'), legacyRow = legacy.querySelector('.buildino-switch');
        expect(legacyInput.checked && legacyInput.getAttribute('role') === 'switch' && legacyRow.getBoundingClientRect().height <= 50 && legacy.getBoundingClientRect().height <= 50, 'Generic switch does not match Vue switch');
        legacy.click(); expect(!legacyInput.checked, 'Generic switch cannot toggle');
        legacyInput.checked = true; expect(getComputedStyle(legacy.querySelector('.buildino-switch__off')).display === 'none', 'Programmatic state has stale label'); legacy.remove();
        const readonly = await window.makeLegacySwitch('edit'); document.body.append(readonly); readonly.querySelector('input').click(); expect(readonly.querySelector('input').checked && readonly.querySelector('input').disabled, 'Readonly switch is interactive'); readonly.remove();
        if (['editor', 'switch', 'map'].includes(${JSON.stringify(process.env.STRUCTURE_CAPTURE || '')})) { $('.structure-discard button').click(); if (['switch', 'map'].includes(${JSON.stringify(process.env.STRUCTURE_CAPTURE || '')})) { submit(); await tick(); } if (${JSON.stringify(process.env.STRUCTURE_CAPTURE || '')} === 'map') { $('[data-location-open]').click(); await tick(); await until(() => !$('[data-location-center]').disabled); $('[data-location-center]').click(); await new Promise(resolve => setTimeout(resolve, 300)); } }
        else { $('.structure-discard button:last-child').click(); }
        $('#result').dataset.ok = 'true'; $('#result').textContent = 'Passed';
    })().catch(error => { $('#result').textContent = error.stack; });
    </script></html>`;
    const server = createServer((request, response) => {
        const path = new URL(request.url, 'http://localhost').pathname;
        if (path === '/test.js') { response.setHeader('Content-Type','application/javascript'); response.end(bundle.outputFiles[0].text); return; }
        if (path.startsWith('/test-tiles-fail/')) { response.writeHead(503).end(); return; }
        if (path.startsWith('/test-tiles/')) { response.setHeader('Content-Type','image/svg+xml'); response.end('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#e5ece4"/><path d="M0 100H256M140 0V256" stroke="#fff" stroke-width="12"/></svg>'); return; }
        if (path.startsWith('/public/') || path.startsWith('/resources/css/') || path.startsWith('/node_modules/leaflet/dist/')) {
            try { const file = join(project, path.slice(1)); response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'font/woff2'); response.end(readFileSync(file)); } catch { response.writeHead(404).end(); } return;
        }
        response.setHeader('Content-Type','text/html; charset=utf-8'); response.end(html);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        for (const width of [1280, 500]) {
            const capture = process.env.STRUCTURE_CAPTURE ? ['--screenshot=' + join(tmpdir(), 'buildino-structure-' + process.env.STRUCTURE_CAPTURE + '-' + width + '.png')] : [];
            const { stdout } = await promisify(execFile)(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--force-device-scale-factor=1', '--window-size=' + width + ',960', '--virtual-time-budget=10000', '--dump-dom', ...capture, 'http://127.0.0.1:' + server.address().port + '/management/operations/buildings'], { encoding:'utf8', timeout:30000, maxBuffer:3*1024*1024, windowsHide:true });
            const result = stdout.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0] || stdout.slice(-1000);
            assert.match(result, /data-ok="true"/, width + ': ' + result);
        }
    } finally { await new Promise(resolve => server.close(resolve)); }
});
