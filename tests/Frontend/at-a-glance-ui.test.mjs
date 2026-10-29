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
test('at a glance: real Blade cards, Persian search, combined filters, scope links and responsive layout', {skip:!existsSync(chrome)}, async()=>{
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
        for(const link of $$('[data-glance-unit] footer a')){const url=new URL(link.href);expect(url.searchParams.get('building_id')==='1'&&url.searchParams.has('unit_id'),'Row link lost scope');}
        $('[data-glance-reset]').click();const summary=$('.glance-wallet summary');summary.focus();expect(document.activeElement===summary,'Wallet details not keyboard accessible');summary.click();expect($('.glance-wallet').open,'Wallet breakdown not available');
        $('[data-glance-reset]').click();expect(document.documentElement.scrollWidth<=innerWidth,'Page overflows');
        window.scrollTo({top:0,left:0,behavior:'instant'});await new Promise(r=>setTimeout(r,250));$('#result').dataset.ok='true';$('#result').textContent='Passed';
    })().catch(error=>$('#result').textContent=error.stack);
    </script></html>`;
    const emptyHtml=`<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">${empty}<pre id="result" hidden></pre><script src="/public/js/buildino-at-a-glance.js"></script><script>document.querySelector('#result').dataset.ok=String(document.querySelector('#glance-building').disabled&&!document.querySelector('[data-glance-filters]'));</script></html>`;
    const server=createServer((req,res)=>{const path=new URL(req.url,'http://localhost').pathname;if(path.startsWith('/public/')||path.startsWith('/fonts/')){try{res.setHeader('Content-Type',path.endsWith('.css')?'text/css':path.endsWith('.js')?'application/javascript':'font/woff2');res.end(readFileSync(join(project,path.startsWith('/fonts/')?'public'+path:path.slice(1))));}catch{res.writeHead(404).end();}return;}res.setHeader('Content-Type','text/html; charset=utf-8');res.end(path==='/empty'?emptyHtml:html);});
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{for(const [width,path]of[[1280,'/'],[500,'/'],[500,'/empty']]){const capture=process.env.GLANCE_CAPTURE&&path==='/'?['--screenshot='+join(tmpdir(),'buildino-glance-'+width+'.png')]:[];const {stdout}=await promisify(execFile)(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+width+',1000','--virtual-time-budget=5000','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+path],{encoding:'utf8',timeout:30000,maxBuffer:2*1024*1024,windowsHide:true});const result=stdout.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0]||stdout.slice(-1000);assert.match(result,/data-ok="true"/,width+path+': '+result);}}finally{await new Promise(resolve=>server.close(resolve));}
});
