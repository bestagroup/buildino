import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('actual registration/setup UI keeps standalone, block, complex and legacy journeys distinct', { skip: !existsSync(chrome) }, async () => {
    const pages = Object.fromEntries(['register', 'standalone', 'block', 'complex', 'legacy'].map(mode => [mode,
        execFileSync('php', ['tests/Frontend/fixtures/onboarding-page.php', mode], { cwd: project, encoding: 'utf8' })
            .replace(/(?:https?:\/\/[^/"'\s]+)(?=\/(?:css|images|fonts|js)\/)/g, '')]));
    const bootstrap = `<script>
    window.fixtureMode=location.pathname.slice(1);window.fixtureErrors=[];
    if(window.BuildinoBuildingSetupConfig)window.BuildinoBuildingSetupConfig.apiBase=location.origin+'/api/v1';
    window.addEventListener('error',e=>fixtureErrors.push(e.message));
    const $=s=>document.querySelector(s), expect=(value,message)=>{if(!value)throw Error(message);};
    const field=(name,value)=>{const el=document.querySelector('[name="'+name+'"]');el.value=value;el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true}));};
    const pause=()=>new Promise(r=>setTimeout(r,20));
    const db={complexes:[{id:10}],buildings:fixtureMode==='complex'?[]:[{id:1,complex_id:10,title:'existing',location:{},statistics:{}}],blocks:[],floors:[],units:[]};
    let confirmationAttempts=0,lastPatch=null;
    window.fetch=async(path,options={})=>{
        const url=new URL(path,location.origin),parts=url.pathname.split('/').filter(Boolean),table=parts.at(-1);
        if(table==='complete'){confirmationAttempts++;return new Response(JSON.stringify({completed:true}),{status:confirmationAttempts===1?500:200,headers:{'Content-Type':'application/json'}});}
        let data;
        if(options.method==='PATCH'){data=db.buildings.find(r=>r.id===Number(table));lastPatch=JSON.parse(options.body);Object.assign(data,lastPatch);}
        else if(options.method==='POST'){data={...JSON.parse(options.body),id:db[table].length+1};const parent={blocks:'building_id',floors:'block_id',units:'floor_id'}[table];if(parent)data[parent]=Number(parts.at(-2));db[table].push(data);}
        else if(/^\\d+$/.test(table))data=db[parts.at(-2)].find(r=>r.id===Number(table));
        else {data=db[table];const parent={blocks:'building_id',floors:'block_id',units:'floor_id'}[table];if(parent)data=data.filter(r=>r[parent]===Number(parts.at(-2)));if(url.searchParams.has('search'))data=data.filter(r=>r.code===url.searchParams.get('search'));}
        return new Response(JSON.stringify({data,links:{next:null}}),{status:200,headers:{'Content-Type':'application/json'}});
    };
    </script>`;
    const registrationChecks = `<script>
    (async()=>{
        expect($('[data-registration-persona]').value==='block_manager','Selected persona was lost');
        expect($('[data-registration-building-label]').textContent.includes('بلوک'),'Block label wrong');
        field('first_name','علی');field('last_name','رضایی');field('mobile','09123456789');field('password','password123');field('password_confirmation','password123');
        for(const persona of ['building_manager','block_manager','complex_manager']){
            field('persona',persona);const form=$('[data-registration-form]');
            expect(form.elements.complex_title.disabled===(persona==='building_manager'),'Complex field required for standalone');
            expect(form.elements.building_title.disabled===(persona==='complex_manager'),'Complex manager forced to invent an initial building');
            if(persona==='building_manager')expect(getComputedStyle(form.elements.complex_title.closest('label')).display==='none','Hidden complex name still displayed');
            if(persona==='complex_manager')expect(getComputedStyle(form.elements.building_title.closest('label')).display==='none','Hidden initial building still displayed');
        }
        field('persona','block_manager');field('complex_title','مجتمع بهار');field('building_title','بلوک A');
        $('[data-registration-next]').click();expect(!$('[data-registration-step="1"]').hidden,'Step 1 failed');
        expect($('[data-registration-step="1"] [name="password"]'),'Credentials must share the identity step');
        field('password_confirmation','mismatch123');$('[data-registration-next]').click();expect(!$('[data-registration-step="1"]').hidden,'Password mismatch bypassed validation');field('password_confirmation','password123');
        $('[data-registration-next]').click();expect(!$('[data-registration-step="2"]').hidden,'Step 2 failed');
        expect(document.querySelectorAll('[data-registration-step]').length===3,'Registration must have three steps');
        $('[data-registration-previous]').click();expect($('[name="building_title"]').value==='بلوک A','Back lost context');
        expect(!fixtureErrors.length,fixtureErrors.join(';'));document.querySelector('#result').dataset.ok='true';
    })().catch(e=>document.querySelector('#result').textContent=e.stack);
    </script>`;
    const setupChecks = `<script>
    (async()=>{
        expect($('[data-building-setup]').dataset.setupReady==='true','Setup failed to initialize');
        expect(document.documentElement.scrollWidth<=innerWidth,'First setup stage overflows');
        if(fixtureMode==='complex'){
            expect(!$('[data-setup-new]').hidden,'Complex did not start with block creation');
            expect($('[data-setup-new-title-label]').textContent.includes('بلوک'),'New block mislabelled building');
            expect($('[name="complexId"]').value==='10','Complex selection was lost');field('title','بلوک دوم');
        }else{
            expect(!$('[data-setup-existing]').hidden,'Existing workspace asked to create again');
            expect($('[name="existingTitle"]').value,'Existing name not prefilled');
            expect($('[name="existingAddress"]').value,'Existing address not prefilled');
        }
        if(fixtureMode==='block'){expect($('[data-setup-context]').textContent.includes('تأیید نشده'),'Unlinked block pretends to be connected');field('existingTitle','بلوک ویرایش‌شده');}
        $('[data-setup-next]').click();await pause();
        expect(!$('[data-setup-step="1"]').hidden,'Cannot reach floors: '+$('[data-setup-error]').textContent+' '+fixtureErrors.join(';'));
        expect(document.documentElement.scrollWidth<=innerWidth,'Floors stage overflows');
        expect([...document.querySelectorAll('.setup-counter')].every(el=>el.getBoundingClientRect().width<=166),'Counters stretch beyond their compact size');
        if(new URLSearchParams(location.search).get('preview')==='floors'){$('#result').dataset.ok='true';return;}
        expect($('[data-setup-add-block]').hidden===(fixtureMode!=='legacy'),'Extra hierarchy exposed or legacy hidden');
        $('[data-setup-next]').click();await pause();expect(!$('[data-setup-step="2"]').hidden,'Cannot reach units');
        expect(document.documentElement.scrollWidth<=innerWidth,'Units stage overflows');
        expect(document.querySelectorAll('.setup-unit-card').length===6,'Unit cards missing');
        expect([...document.querySelectorAll('.setup-unit-card input')].every(el=>el.getBoundingClientRect().height>=(innerWidth>=1000?38:44)),'Unit inputs are too small to use');
        const unitNumber=$('.setup-unit-card [data-unit-field="unit_number"]');unitNumber.value='11';unitNumber.dispatchEvent(new Event('input',{bubbles:true}));
        expect($('.setup-unit-card__title').textContent==='واحد 11','Unit card title did not follow edited number');
        $('[data-setup-previous]').click();await pause();$('[data-setup-next]').click();await pause();
        expect($('.setup-unit-card [data-unit-field="unit_number"]').value==='11','Returning to unit cards lost entered data');
        const resizeFloor=async count=>{const el=$('[data-floor-count]');el.value=String(count);el.dispatchEvent(new Event('change',{bubbles:true}));await pause();};
        await resizeFloor(4);
        let cards=[...document.querySelectorAll('.setup-floor[open] .setup-unit-card')];
        expect(cards.length===4&&!$('.setup-unit-grid').classList.contains('setup-unit-grid--list'),'Four units should use cards');
        if(innerWidth>=1000)expect(cards.every(el=>Math.abs(el.getBoundingClientRect().top-cards[0].getBoundingClientRect().top)<1),'Four desktop units are not in one row');
        await resizeFloor(5);cards=[...document.querySelectorAll('.setup-floor[open] .setup-unit-card')];
        expect(cards.length===5&&$('.setup-unit-grid').classList.contains('setup-unit-grid--list'),'Five units should switch to list');
        if(innerWidth>=1000)expect(cards.every((el,index)=>!index||el.getBoundingClientRect().top>cards[index-1].getBoundingClientRect().top),'Desktop list rows overlap');
        await resizeFloor(2);expect($('.setup-unit-card [data-unit-field="unit_number"]').value==='11','Layout switching lost entered unit number');
        if(new URLSearchParams(location.search).get('preview')==='units'){$('#result').dataset.ok='true';return;}
        expect(!$('[data-setup-step="2"]').hidden&&$('[data-setup-review]').textContent,'Unit stage must include the final review');
        $('[data-setup-next]').click();await pause();await pause();expect($('[data-setup-success]').hidden,'Failed final confirmation announced success');
        $('[data-setup-next]').click();await pause();await pause();expect(!$('[data-setup-success]').hidden,'Explicit confirmation retry failed');
        expect(db.buildings.length===1&&db.blocks.length===1&&db.units.length===6,'Registration workspace duplicated on setup/retry');
        expect(db.complexes.length===1,'Complex duplicated during block creation');
        expect(fixtureMode==='block' ? lastPatch?.title==='بلوک ویرایش‌شده'&&Object.keys(lastPatch).length===1 : lastPatch===null,'Untouched metadata was overwritten');
        for(const selector of ['[data-setup-ownership]','[data-setup-invite]','[data-setup-unit-link]']){const url=new URL($(selector).href);expect(url.searchParams.get('building_id')==='1','Completion link lost workspace');}
        expect(fixtureMode!=='complex'||!$('[data-setup-another]').hidden,'Complex manager cannot add another block');
        expect(document.documentElement.scrollWidth<=innerWidth,'Setup overflows mobile');
        expect(!fixtureErrors.length,fixtureErrors.join(';'));$('#result').dataset.ok='true';
    })().catch(e=>$('#result').textContent=e.stack);
    </script>`;
    const server = createServer((req, res) => {
        const path = new URL(req.url, 'http://localhost').pathname;
        if (/^\/(css|js|images|fonts)\//.test(path)) {
            try { res.setHeader('Content-Type', path.endsWith('.js') ? 'application/javascript' : path.endsWith('.css') ? 'text/css' : path.endsWith('.svg') ? 'image/svg+xml' : 'application/octet-stream'); res.end(readFileSync(join(project, 'public', path))); }
            catch { res.writeHead(404).end(); } return;
        }
        const mode = path.slice(1), content = pages[mode];
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        if (mode === 'register') res.end(content.replace('</body>', bootstrap + '<pre id="result" hidden></pre><script src="/js/buildino-registration.js"></script>' + registrationChecks + '</body>'));
        else res.end('<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/css/buildino-fonts.css"><link rel="stylesheet" href="/css/buildino-management.css"><link rel="stylesheet" href="/css/buildino-design-system.css"><link rel="stylesheet" href="/css/buildino-building-setup.css">' + content + bootstrap + '<pre id="result" hidden></pre><script src="/js/buildino-building-setup-engine.js"></script><script src="/js/buildino-building-setup.js"></script>' + setupChecks + '</html>');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        for (const [width, mode] of [[1280, 'register'], [500, 'register'], [1280, 'standalone'], [500, 'block'], [500, 'complex'], [1280, 'legacy']]) {
            const capture = process.env.SETUP_CAPTURE && mode !== 'register' ? ['--screenshot=' + join(process.env.SETUP_CAPTURE, 'setup-' + mode + '-' + width + '.png')] : [];
            const preview = process.env.SETUP_PREVIEW && mode !== 'register' ? '?preview=' + process.env.SETUP_PREVIEW : '';
            const { stdout } = await promisify(execFile)(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--force-device-scale-factor=1', '--window-size=' + width + ',1000', ...capture, '--virtual-time-budget=6000', '--dump-dom', 'http://127.0.0.1:' + server.address().port + '/' + mode + preview], { timeout: 30000, maxBuffer: 4 * 1024 * 1024, windowsHide: true });
            const result = stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || stdout.slice(-1000);
            assert.match(result, /data-ok="true"/, width + ' ' + mode + ': ' + result);
        }
    } finally { await new Promise(resolve => server.close(resolve)); }
});
