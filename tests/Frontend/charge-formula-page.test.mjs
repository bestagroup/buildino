import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('formula page reuses CRUD with inline stages, scoped actions, keyboard focus and safe save recovery', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-formula-page-'));
    try {
        const read = path => readFileSync(join(project, path), 'utf8');
        let markup = read('resources/views/management/partials/charge-formula-page.blade.php')
            .replace(/@foreach[^]*?@endforeach/, '<option value="4">ساختمان آزمایشی</option><option value="5">ساختمان مشاهده</option>')
            .replace(/@if[^]*?@endif/g, '')
            .replace("@include('management.partials.charge-formula-guide')", read('resources/views/management/partials/charge-formula-guide.blade.php').replace(/@if[^\n]*|@endif/g, ''));
        const view = read('resources/views/management/operations/resource.blade.php');
        let editor = view.slice(view.indexOf('    <aside'), view.indexOf('@endsection'))
            .replace(/@if \(\$resourceKey === 'charge-formulas'\)([^]*?)@else[^]*?@endif/g, '$1')
            .replace(/@if[^]*?@endif/g, '');
        const clean = html => html.replace(/@include\([^]*?\)/g, '◇').replace(/\{\{([^]*?)\}\}/g, (_, expression) => expression.includes('charge-formula-dialog') ? ' charge-formula-dialog' : expression.includes('بازگشت') ? 'بازگشت به فرمول‌ها' : expression.includes('انصراف') ? 'انصراف و بازگشت' : '#');
        markup = clean(markup); editor = clean(editor);
        assert.doesNotMatch(markup + editor, /@foreach|@if|@else|\{\{/);
        const source = path => read(path).replaceAll('window.location.origin', "'http://localhost'").replaceAll('location.origin', "'http://localhost'");
        const asset = path => pathToFileURL(join(project, 'public', path)).href;
        const fixture = join(directory, 'fixture.html'), screenshot = process.env.FORMULA_PAGE_SCREENSHOT;
        writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        ${['css/buildino-fonts.css', 'css/buildino-management.css', 'css/buildino-crud.css', 'css/buildino-unit-ownerships.css', 'css/buildino-charge-formulas.css'].map(path => '<link rel="stylesheet" href="' + asset(path) + '">').join('')}
        <style>body{margin:16px}*{box-sizing:border-box}</style>
        <div class="crud-page ownership-page charge-formula-page" id="buildinoCrudApp">${markup}<div id="crudDrawerBackdrop" class="crud-drawer-backdrop"></div>${editor}</div>
        <button id="after-page">خارج از فرم</button><pre id="result" hidden></pre><script>
        const $ = selector => document.querySelector(selector);
        let failSave = true, writes = 0, failList = false, releaseList, holdList = false;
        const errors = []; window.addEventListener('error', event => errors.push(event.error?.stack || event.message));
        const row = { id: 31, title: 'شارژ نظافت', calculation_type: 'fixed', configuration: {}, is_active: true, items: [{ financial_category_id: 7, title: 'نظافت', base_amount: 5000 }] };
        window.BuildinoCrud = { resourceKey: 'charge-formulas', lookupBase: 'http://localhost/management/lookups',
            formulaBuildings: [{ id: 4, canCreate: true, canUpdate: true, canViewPeriods: true }, { id: 5 }],
            resource: { title: 'فرمول شارژ', context: [{name:'building_id',label:'ساختمان',required:true}],
                list: {url:'/api/v1/buildings/{building_id}/charge-formulas'}, create:{url:'/api/v1/buildings/{building_id}/charge-formulas',method:'POST'}, update:{url:'/api/v1/charge-formulas/{id}',method:'PATCH'},
                columns:[{key:'title',label:'عنوان'},{key:'calculation_type',label:'روش تقسیم'},{key:'is_active',label:'وضعیت'}],
                fields:[{name:'title',label:'عنوان',type:'text',required:true},{name:'builder',label:'روش تقسیم',type:'charge_formula_builder'},{name:'is_active',label:'فعال در محاسبات',type:'checkbox',default:true}]
            } };
        window.fetch = async (raw, options = {}) => {
            const url = new URL(raw, 'http://localhost'), path = url.pathname;
            let data, ok = true;
            if (options.method && options.method !== 'GET') {
                writes++; ok = !failSave;
                if (ok) { const payload = JSON.parse(options.body); row.title = payload.title; row.calculation_type = payload.builder.calculation_type; row.is_active = payload.is_active; }
                data = ok ? { data: row } : { message:'خطای اعتبارسنجی', errors:{'builder.financial_category_id':['برای این دسته هزینه فرمول دیگری وجود دارد.']} };
            } else if (path.includes('/lookups/')) data = {data:[{id:7,label:'نظافت ساختمان'}]};
            else { if (holdList) await new Promise(resolve => releaseList = resolve); if (failList) throw Error('Network failed'); data = {data:[row],meta:{current_page:Number(url.searchParams.get('page')||1),last_page:2}}; }
            return {ok,status:ok?200:422,headers:{get:()=> 'application/json'},json:async()=>data};
        };
        </script><script>${source('public/js/buildino-charge-formula-ui.js')}</script><script>${source('public/js/buildino-crud.js')}</script><script>
        const expect = (condition, text) => { if (!condition) throw Error(text + '; ' + errors.join('; ')); };
        const until = async predicate => { for (let i=0;i<200;i++) { if(predicate()) return; await new Promise(resolve=>setTimeout(resolve,10)); } throw Error('Timed out; '+errors.join('; ')+'; form error: '+$('#crudFormError').textContent+'; drawer: '+$('#crudDrawer').outerHTML.slice(0,500)); };
        const building = $('[data-context-name="building_id"]');
        const chooseBuilding = id => { building.value=id; building.dispatchEvent(new Event('change',{bubbles:true})); };
        const component = () => $('[data-charge-formula-builder]');
        const form = $('#crudForm'), save = $('#crudSaveButton'), drawer = $('#crudDrawer');
        const step = () => component()?.dataset.formulaStep;
        (async()=>{
            await until(()=>$('#buildinoCrudApp').dataset.coreReady==='1');
            expect(!$('[data-charge-location]').hidden && $('[data-charge-directory]').hidden, 'Building selection must be separate');
            chooseBuilding('4'); await until(()=>$('[data-row-action="edit"]'));
            expect($('[data-charge-location]').hidden && !$('[data-charge-directory]').hidden, 'List must replace selection');
            expect($('[data-row-action="edit"]').getAttribute('aria-label').includes(row.title), 'Edit buttons need unique accessible names');
            expect($('#coreRemotePagination').querySelectorAll('button').length===2, 'All server pages must be reachable');
            $('#crudCreateButton').focus(); $('#crudCreateButton').click(); await until(()=>component() && !save.disabled);
            expect(!drawer.hidden && $('[data-charge-directory]').hidden && !$('body').classList.contains('crud-overlay-open'), 'Editor must replace directory without modal scroll lock');
            expect(drawer.getAttribute('role')==='region' && !drawer.hasAttribute('aria-modal'), 'Inline editor must not pretend to be a modal');
            expect(document.activeElement.classList.contains('charge-wizard__title'), 'Opening must focus the visible step heading');
            save.click(); expect(step()==='0', 'Missing category must block advance');
            const category = component().querySelector('[data-formula-category]');
            expect(category.getAttribute('aria-invalid')==='true' && category.getAttribute('aria-describedby'), 'Validation must expose field and error association');
            category.value='7'; category.dispatchEvent(new Event('change',{bubbles:true})); save.click(); expect(step()==='1','Continue should replace category with method');
            expect(component().querySelector('[role="radiogroup"]'), 'Methods must be an accessible radio group');
            const method=component().querySelector('[data-formula-method][value="fixed"]'); method.checked=true; method.dispatchEvent(new Event('change',{bubbles:true}));
            save.click(); expect(step()==='2','Method must advance to parameters'); component().querySelector('[data-formula-fixed-amount]').value='5000'; save.click(); expect(step()==='3','Parameters must advance to review');
            expect(component().querySelector('[role="status"]').textContent.includes('۴'), 'Screen reader should receive current step');
            save.focus(); const tab=new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}); save.dispatchEvent(tab); expect(!tab.defaultPrevented,'Inline form must not trap Tab');
            save.click(); await until(()=>!save.disabled); expect(step()==='0' && form.elements.title.value==='شارژ نظافت ساختمان','Server errors must return to field and retain title');
            component().querySelectorAll('.charge-wizard__steps button')[3].click(); failSave=false; const before=writes; save.click(); save.click();
            await until(()=>drawer.hidden && $('[data-row-action="edit"]'));
            expect(writes===before+1,'Duplicate save must be blocked'); expect(document.activeElement.id==='charge-directory-title','After saving, focus must return to refreshed directory');
            $('[data-row-action="edit"]').focus(); $('[data-row-action="edit"]').click(); await until(()=>component() && !save.disabled && !drawer.hidden);
            form.elements.title.value='پیش‌نویس محفوظ'; form.elements.title.dispatchEvent(new Event('input',{bubbles:true}));
            $('#crudCancelButton').click(); expect(!drawer.hidden && !component().querySelector('.charge-wizard__discard').hidden,'Dirty close must require a decision');
            component().querySelectorAll('.charge-wizard__discard button')[1].click(); expect(drawer.hidden && !$('[data-charge-directory]').hidden,'Discard must restore directory');
            $('[data-charge-change-building]').click(); expect(!$('[data-charge-location]').hidden,'Change building must restore selection');
            document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})); expect(!$('[data-charge-location]').hidden,'Escape outside editor must not change the current view');
            chooseBuilding('5'); await until(()=>!$('#crudState').hidden || $('#crudTableBody').children.length);
            await new Promise(resolve=>setTimeout(resolve,20));
            expect($('#crudCreateButton').hidden && !$('[data-row-action="edit"]'),'Viewer must not see mutations');
            chooseBuilding('4'); await until(()=>$('[data-row-action="edit"]'));
            holdList=true; $('#crudRefreshButton').click(); await until(()=>releaseList); chooseBuilding('5'); holdList=false; releaseList();
            await new Promise(resolve=>setTimeout(resolve,30)); expect(!$('[data-row-action="edit"]'),'Stale list must not restore foreign-context actions');
            expect(document.documentElement.scrollWidth<=innerWidth,'Page must fit viewport'); expect(!errors.length,'No browser runtime errors');
            if (${Boolean(screenshot)}) { chooseBuilding('4'); await until(()=>$('[data-row-action="edit"]')); $('#crudCreateButton').click(); await until(()=>component() && !save.disabled && !drawer.hidden); const activeCategory=component().querySelector('[data-formula-category]'); activeCategory.value='7'; activeCategory.dispatchEvent(new Event('change',{bubbles:true})); save.click(); await document.fonts.ready; window.scrollTo(0,0); }
            if (${Boolean(screenshot)}) { window.scrollTo({top:0,left:0,behavior:'instant'}); await new Promise(resolve=>setTimeout(resolve,450)); }
            const bounds=$('#buildinoCrudApp').getBoundingClientRect(); expect(bounds.left>=0 && bounds.right<=innerWidth,'Page bounds: '+JSON.stringify({left:bounds.left,right:bounds.right,width:innerWidth,scrollX}));
            $('#result').dataset.ok='true';
        })().catch(error=>$('#result').textContent=error.stack);
        </script></html>`);
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', '--force-device-scale-factor=1', '--window-size=' + (process.env.FORMULA_PAGE_VIEWPORT || '500,1000'), '--user-data-dir=' + join(directory, 'profile'), '--virtual-time-budget=7000', ...(screenshot ? ['--screenshot=' + resolve(screenshot)] : []), '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || output.slice(-1000);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-formula-page-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
