import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import { promisify } from 'node:util';

const project=fileURLToPath(new URL('../../',import.meta.url));
const chrome=process.env.CHROME_BIN||'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('at a glance: real Blade list and unit modal, Persian search, combined filters, scope links and responsive layout', {skip:!existsSync(chrome)}, async()=>{
    const render=mode=>execFileSync('php',['tests/Frontend/fixtures/glance-page.php',mode],{cwd:project,encoding:'utf8'});
    const content=render('normal'),empty=render('empty');
    assert.match(content,/data-glance-unit/);assert.doesNotMatch(content,/@foreach|@if|@php/);
    const styles=['/public/css/buildino-fonts.css','/public/css/buildino-management.css','/public/css/buildino-design-system.css','/public/css/buildino-unit-ownerships.css','/public/css/buildino-at-a-glance.css'];
    const html=`<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles.map(path=>'<link rel="stylesheet" href="'+path+'">').join('')}<div id="fixture" style="padding:16px;max-width:1200px;margin:auto">${content}</div><pre id="result" hidden></pre><script src="/public/js/buildino-at-a-glance.js"></script><script>
    const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],expect=(value,message)=>{if(!value)throw Error(message);};
    const input=(selector,value)=>{const el=$(selector);el.value=value;el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true}));};
    const shown=()=>$$('[data-glance-unit]').filter(el=>!el.hidden),floors=()=>$$('[data-glance-floor]').filter(el=>!el.hidden);
    (async()=>{
        expect(!$('[data-glance-filters]').hidden&&shown().length===3,'Cards/filter initialization failed');
        expect(!window.glanceXss&&!$('.glance-unit img'),'Report names injected HTML');
        expect(!$('.glance-scroll')&&!$('table'),'Legacy horizontal table remains');
        expect($('[data-glance-open]') && !$('.glance-unit-row .glance-unit-metrics'),'List rows must stay compact');
        if(innerWidth>1000){const boxes=$$('.glance-stat').map(el=>el.getBoundingClientRect()),grid=$('.glance-stats').getBoundingClientRect();expect(boxes.slice(0,3).every(box=>Math.abs(box.top-boxes[0].top)<1)&&boxes.slice(3).every(box=>Math.abs(box.top-boxes[3].top)<1)&&boxes[3].top>boxes[0].bottom,'Summary must have three cards then four');for(const row of [boxes.slice(0,3),boxes.slice(3)])expect(Math.abs(Math.min(...row.map(box=>box.left))-grid.left)<1&&Math.abs(Math.max(...row.map(box=>box.right))-grid.right)<1,'Both summary rows must fill the available width');}
        input('[data-glance-search]','101');expect(shown().length===1&&shown()[0].dataset.search.includes('۱۰۱'),'English search did not match Persian digits');
        input('[data-glance-search]','۱۰۲');expect(shown().length===1,'Persian search did not match English digits');
        input('[data-glance-search]','علي رضايي');expect(shown().length===1,'Arabic/Persian letter search failed');
        input('[data-glance-block]','2');expect(shown().length===0&&!$('[data-glance-empty]').hidden&&floors().length===0,'Combined filters or empty groups wrong');
        $('[data-glance-reset]').click();expect(shown().length===3&&document.activeElement===$('[data-glance-search]'),'Reset failed');
        input('[data-glance-status]','shortfall');expect(shown().length===2,'Shortfalls offset by another unit credit');
        input('[data-glance-status]','surplus');expect(shown().length===1&&shown()[0].dataset.surplus==='9000','Surplus filter wrong');
        input('[data-glance-status]','vacant');expect(shown().length===1&&shown()[0].dataset.occupied==='0','Vacancy filter wrong');
        input('[data-glance-status]','inactive');expect(shown().length===1&&shown()[0].dataset.active==='0','Inactive filter wrong');
        $('[data-glance-reset]').click();input('[data-glance-block]','2');expect(floors().length===1&&floors()[0].querySelector('[data-glance-floor-count]').textContent==='۱','Floor count not updated');
        $('[data-glance-reset]').click();
        const origin=location.href, dialog=$('[data-glance-dialog]');
        const until=async predicate=>{for(let i=0;i<150;i++){if(predicate())return;await new Promise(r=>setTimeout(r,10));}throw Error('Timed out '+JSON.stringify({open:dialog.open,discard:$('[data-glance-discard]').hidden,disabled:$('[data-glance-close]').disabled,focus:document.activeElement.outerHTML.slice(0,100)}));};
        const open=$('[data-glance-open]');open.focus();open.click();expect(dialog.open&&$('[data-glance-overview] .glance-wallet'),'Unit summary modal missing');
        expect(document.activeElement.id==='glance-unit-title','Dialog heading focus');expect(!window.glanceXss&&!$('[data-glance-overview] img'),'Modal must escape report data');
        expect($('[data-glance-overview] .glance-payable').textContent.includes('۶٬۰۰۰'),'Payable summary changed the amount');
        expect($('[data-glance-overview] .glance-financial').textContent.includes('مانده بستانکاری'),'Financial summary omitted credit');
        for(const el of $$('[data-glance-overview] .glance-person-grid > div, [data-glance-overview] .glance-unit-metrics > div'))expect(el.scrollWidth<=el.clientWidth+1,'Summary sections overlap');
        expect(dialog.scrollWidth<=dialog.clientWidth,'Unit modal overflows');
        $('.glance-wallet summary').click();expect($('.glance-wallet').open,'Wallet breakdown unavailable');
        $('[data-glance-section="ownerships"]').click();await until(()=>!$('[data-glance-loading]').hidden===false);
        await until(()=>document.querySelector('iframe')?.contentDocument?.querySelector('[data-glance-embedded]'));
        const frame=document.querySelector('iframe'), doc=frame.contentDocument;
        expect(new URL(frame.src).searchParams.get('unit_id')==='101'&&new URL(frame.src).searchParams.get('building_id')==='1','Embedded scope lost');
        const field=doc.querySelector('input');field.value='Draft';field.dispatchEvent(new frame.contentWindow.Event('input',{bubbles:true}));
        await new Promise(r=>setTimeout(r,40));
        $('[data-glance-section="overview"]').click();$('[data-glance-section="ownerships"]').click();expect(doc.querySelector('input').value==='Draft','Tab switch discarded draft');
        $('[data-glance-close]').click();expect(dialog.open&&!$('[data-glance-discard]').hidden,'Dirty close must ask');
        $('[data-glance-keep]').click();expect($('[data-glance-discard]').hidden,'Keep editing failed');
        doc.querySelector('button').click();await until(()=>$('[data-glance-close]').disabled);$('[data-glance-close]').click();expect(dialog.open,'Pending write must keep modal open');frame.contentWindow.releaseWrite();await until(()=>doc.querySelector('[data-saved]'));
        await new Promise(r=>setTimeout(r,50));
        $('[data-glance-section="overview"]').click();await until(()=>!$('[data-glance-modal-refresh-error]').hidden);$('[data-glance-modal-refresh-error] button').click();await until(()=>$('[data-glance-overview]').textContent.includes('Updated owner'));
        expect(location.href===origin,'Modal navigated parent');
        $('[data-glance-section="occupancies"]').click();await until(()=>!$('[data-glance-frame-error]').hidden);
        $('[data-glance-retry]').click();await until(()=>!$('[data-glance-frames] iframe:last-child').hidden);
        $('[data-glance-close]').click();await until(()=>!dialog.open&&document.activeElement.matches('[data-glance-open]'));
        expect(!document.documentElement.style.overflow&&$$('iframe').length===0,'Close must clean up frames and scroll lock');
        $('[data-glance-reset]').click();expect(document.documentElement.scrollWidth<=innerWidth,'Page overflows');
        if(${Boolean(process.env.GLANCE_MODAL_CAPTURE)})$('[data-glance-open]').click();window.scrollTo({top:0,left:0,behavior:'instant'});await new Promise(r=>setTimeout(r,250));$('#result').dataset.ok='true';$('#result').textContent='Passed';
    })().catch(error=>$('#result').textContent=error.stack);
    </script></html>`;
    const emptyHtml=`<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">${empty}<pre id="result" hidden></pre><script src="/public/js/buildino-at-a-glance.js"></script><script>document.querySelector('#result').dataset.ok=String(document.querySelector('#glance-building').disabled&&!document.querySelector('[data-glance-filters]'));</script></html>`;
    let saves=0, occupancyReads=0, refreshFails=1;
    const embedded=`<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body data-glance-embedded><form class="finance-editor"><input name="title"><button type="submit">Save</button></form><script>fetchOriginal=window.fetch.bind(window);window.fetch=(...args)=>new Promise(resolve=>{window.releaseWrite=()=>fetchOriginal(...args).then(resolve)});document.querySelector('form').addEventListener('submit',async event=>{event.preventDefault();await fetch('/api/v1/unit-ownerships',{method:'POST'});document.querySelector('form').remove();document.body.insertAdjacentHTML('beforeend','<p data-saved>Saved</p>');});</script><script src="/public/js/buildino-unit-workspace.js"></script></body></html>`;
    const server=createServer((req,res)=>{const address=new URL(req.url,'http://localhost');
    if(address.pathname.startsWith('/api/')){saves++;res.setHeader('Content-Type','application/json');res.end('{}');return;}
    if(address.pathname.startsWith('/management/operations/')){res.setHeader('Content-Type','text/html; charset=utf-8');if(address.pathname.endsWith('/occupancies')&&++occupancyReads%2===1){res.writeHead(403).end('Forbidden');return;}res.end(embedded);return;}
const path=new URL(req.url,'http://localhost').pathname;if(path==='/'&&saves&&refreshFails-- >0){res.writeHead(503).end('Unavailable');return;}if(path.startsWith('/public/')||path.startsWith('/fonts/')){try{res.setHeader('Content-Type',path.endsWith('.css')?'text/css':path.endsWith('.js')?'application/javascript':'font/woff2');res.end(readFileSync(join(project,path.startsWith('/fonts/')?'public'+path:path.slice(1))));}catch{res.writeHead(404).end();}return;}res.setHeader('Content-Type','text/html; charset=utf-8');res.end((path==='/empty'?emptyHtml:html).replace(/http:\/\/localhost(?=\/management)/g,'http://127.0.0.1:'+server.address().port).replaceAll('\u0639\u0644\u06cc \u0631\u0636\u0627\u06cc\u06cc',saves?'Updated owner':'\u0639\u0644\u06cc \u0631\u0636\u0627\u06cc\u06cc'));});
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{for(const [width,path]of[[1280,'/'],[500,'/'],[500,'/empty']]){saves=0;occupancyReads=0;refreshFails=1;const capture=process.env.GLANCE_CAPTURE&&path==='/'?['--screenshot='+join(tmpdir(),'buildino-glance-'+width+'.png')]:[];const {stdout}=await promisify(execFile)(chrome,['--headless','--force-prefers-reduced-motion','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+width+',1000','--virtual-time-budget=10000','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+path],{encoding:'utf8',timeout:30000,maxBuffer:2*1024*1024,windowsHide:true});const result=stdout.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0]||stdout.slice(-1000);assert.match(result,/data-ok="true"/,width+path+': '+result);}}finally{await new Promise(resolve=>server.close(resolve));}
});
