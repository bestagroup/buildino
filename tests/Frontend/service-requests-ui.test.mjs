import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFile } from 'node:child_process';
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
test('services: actual Vue stages, assignment, quotes, wallet confirmation, lifecycle and responsive UI', { skip: !existsSync(chrome) }, async () => {
    const bundle = await build({ absWorkingDir:project,bundle:true,write:false,format:'iife',define:{'process.env.NODE_ENV':'"production"'},
        stdin:{contents:`import {createApp} from 'vue'; import Page from './resources/js/pages/management/services/Requests.vue'; import './resources/js/buildino-jalali-datepicker.js'; let app; window.mountServices=props=>{app?.unmount();app=createApp(Page,props);app.mount('#app');};`,resolveDir:project},
        plugins:[{name:'vue-fixture',setup(builder){
            builder.onResolve({filter:/\.css$/},args=>({path:args.path,namespace:'style'}));builder.onLoad({filter:/.*/,namespace:'style'},()=>({contents:'',loader:'js'}));
            builder.onResolve({filter:/^@inertiajs\/vue3$/},()=>({path:'head',namespace:'stub'}));builder.onLoad({filter:/.*/,namespace:'stub'},()=>({contents:'export const Head={render:()=>null};'}));
            builder.onLoad({filter:/\.vue$/},args=>{const {descriptor}=parse(readFileSync(args.path,'utf8'),{filename:args.path});return{contents:compileScript(descriptor,{id:'service-'+args.path.split('/').pop(),inlineTemplate:true,fs:{fileExists:existsSync,readFile:path=>readFileSync(path,'utf8')}}).content,loader:'ts'};});
        }}]});
    const html=`<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    ${['/public/css/buildino-fonts.css','/public/css/buildino-management.css','/public/css/buildino-crud.css','/public/css/buildino-design-system.css','/public/css/buildino-unit-ownerships.css','/resources/css/management-inertia.css','/resources/css/finance-workspace.css','/resources/css/service-requests.css','/node_modules/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css'].map(path=>'<link rel="stylesheet" href="'+path+'">').join('')}
    <div id="app" style="padding:16px;max-width:1200px;margin:auto"></div><pre id="result" hidden></pre><script>window.requestAnimationFrame=cb=>setTimeout(()=>cb(performance.now()),16);window.cancelAnimationFrame=clearTimeout;window.addEventListener('error',event=>document.querySelector('#result').textContent=event.message);</script><script src="/test.js"></script><script>
    let writes=[],reads=[],hold=false,release,failSave=false,failLookup=false,loseSave=false,holdRead=false,releaseRead;
    const quote={id:31,provider_user_id:2,amount:1000,provider_amount:900,commission_amount:100,status:'pending',valid_until:'2031-12-01T14:00:00Z',notes:'هزینه قطعات و اجرا'};
    const row={id:11,building_id:1,unit_id:101,request_number:'SR-11',requested_by:{id:1,first_name:'مدیر',last_name:'ساختمان'},assigned_to:null,title:'تعمیر روشنایی راه‌پله',type:'برق',priority:'high',status:'open',description:'چراغ طبقه اول روشن نمی‌شود.',quotes:[],wallet_payment:null};
    window.fetch=async(path,options={})=>{const url=new URL(path,location.origin);const reply=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
        if(options.method&&options.method!=='GET'){
            writes.push({path:url.pathname,method:options.method,body:options.body?JSON.parse(options.body):{}});if(hold)await new Promise(resolve=>release=resolve);
            if(failSave)return reply({message:'عنوان نامعتبر است.',errors:{title:['عنوان نامعتبر است.']}},422);if(loseSave)throw Error('Connection lost');
            const body=writes.at(-1).body;
            if(url.pathname.endsWith('/assign')){row.assigned_to={id:body.assigned_to,first_name:'ارائه‌دهنده',last_name:'نمونه'};row.status='assigned';}
            else if(url.pathname.endsWith('/quotes'))row.quotes=[{...quote,amount:body.amount,notes:body.notes,valid_until:body.valid_until}];
            else if(url.pathname.endsWith('/accept')){row.quotes[0].status='accepted';row.wallet_payment={status:'locked',payer_source:body.payer_source,amount:1000,provider_amount:900,commission_amount:100};}
            else if(url.pathname.endsWith('/start'))row.status='in_progress';else if(url.pathname.endsWith('/finish'))row.status='awaiting_confirmation';else if(url.pathname.endsWith('/confirm')){row.status='completed';row.wallet_payment.status='settled';}
            else if(url.pathname.endsWith('/cancel-financial')){row.status='cancelled';if(row.wallet_payment)row.wallet_payment.status='released';}
            else if(options.method!=='DELETE')Object.assign(row,body);
            return reply({data:row},201);
        }
        reads.push(url.pathname+url.search);
        if(url.pathname==='/management/lookups/users')return failLookup?reply({message:'فهرست دریافت نشد.'},500):reply({data:[{id:2,label:'ارائه‌دهنده نمونه — 09120000000'}]});
        if(url.pathname==='/api/v1/service-requests/11')return reply({data:row});
        if(holdRead)await new Promise(resolve=>releaseRead=resolve);
        return reply({data:[row],current_page:Number(url.searchParams.get('page')||1),last_page:2,total:21});
    };
    const props=()=>({csrfToken:'test',workspace:{userId:1,initial:{},buildings:[1,2].map(id=>({id,title:'ساختمان '+id,currency:'IRR',canCreate:true,abilities:{'service-requests.create':true,'service-requests.update':true,'service-requests.delete':true,'service-finance.quote':true,'service-finance.manage':true,'service-finance.settle':true},finance:{active:true,user_wallet:true,unit_wallet:true}})),units:[1,2].map(id=>({id:100+id,building_id:id,label:'بلوک اصلی / طبقه اول / واحد '+id,canCreate:true,canUpdate:true,canUseWallet:true}))}});
    const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],tick=()=>new Promise(r=>setTimeout(r,10)),expect=(value,message)=>{if(!value)throw Error(message);};
    const until=async predicate=>{for(let i=0;i<160;i++){if(predicate())return;await tick();}throw Error('Timed out '+predicate+' / '+$('#app').textContent);};
    const click=async selector=>{expect($(selector),'Missing '+selector);$(selector).click();await tick();};
    const input=async(name,value)=>{let el=$('[name="'+name+'"]');expect(el,'Missing field '+name);if(el.type==='hidden')el=el.previousElementSibling;el.value=value;el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true}));await tick();};
    const submit=async()=>{$('#service-request-form').dispatchEvent(new Event('submit',{cancelable:true}));await tick();};
    const mount=async data=>{window.mountServices(data||props());await until(()=>$('[data-service-open]'));};
    const action=key=>'[data-service-action="'+key+'"]';
    const confirm=async key=>{const before=writes.length;await click(action(key));expect($('[role="alertdialog"]')&&writes.length===before,'Action bypassed confirmation '+key);await click('[role="alertdialog"] .ui-dialog__actions button:last-child');await until(()=>!$('[role="alertdialog"]'));};
    (async()=>{
        await mount();expect(!$('.crud-sibling-nav')&&!$('.crud-drawer'),'Legacy UI remains');await click('[data-service-create]');expect(!$('.service-record'),'Editor did not replace list');await submit();expect($('[aria-invalid="true"]')&&writes.length===0,'Missing building accepted');
        await input('building_id','1');expect($('[name="unit_id"]').options.length===3,'Unit options cross building boundaries');await input('unit_id','101');await submit();expect(!$('[name="building_id"]')&&$('[name="title"]'),'Stages stacked');await input('title','تعمیر چراغ');await input('type','برق');await submit();expect($('.finance-review')&&writes.length===0,'Review missing');
        hold=true;await submit();await until(()=>release);const n=writes.length;await submit();expect(writes.length===n,'Duplicate create');hold=false;release();await until(()=>$(action('assign')));expect(writes.at(-1).body.building_id===1&&writes.at(-1).body.unit_id===101,'Create scope payload wrong');
        expect(!$(action('start'))&&!$('[data-service-accept]'),'Start/accept available before funding');
        await click(action('assign'));await until(()=>$('[name="assigned_to"]').options.length===2);await input('assigned_to','2');await submit();await submit();await until(()=>$(action('quote')));expect($('#app').textContent.includes('ارائه‌دهنده نمونه'),'Loaded Eloquent relation was not normalized');
        await click(action('quote'));await input('amount','0');await submit();expect($('[data-service-error]')&&!$('.finance-review'),'Zero quote accepted');await input('amount','1000');await input('valid_until','۱۴۱۰/۰۹/۱۰ ۱۴:۳۰');await submit();expect($('.finance-review'),'Quote review missing');await submit();await until(()=>$('[data-service-accept]'));expect(writes.at(-1).body.valid_until.includes('T14:30'),'Quote time not converted');
        await click('[data-service-accept]');expect($('[name="payer_source"]').options.length===3,'Wallet choices missing');await input('payer_source','user_wallet');await submit();expect($('.finance-review').textContent.includes('شخصی'),'Wallet review missing');await submit();await until(()=>$(action('start')));expect(!$(action('assign'))&&!$(action('edit'))&&!$('[data-service-accept]'),'Funded request allows changing financial context');
        await confirm('start');await until(()=>$(action('finish')));expect(!$(action('confirm')),'Settlement before finishing');await confirm('finish');await until(()=>$(action('confirm')));await confirm('confirm');await until(()=>$('.service-state')?.dataset.state==='completed');expect(!$(action('cancel-financial'))&&!$(action('start')),'Closed lifecycle exposes mutations');
        row.status='assigned';row.wallet_payment=null;row.quotes=[{...quote}];const limited=props();limited.workspace.userId=9;for(const b of limited.workspace.buildings){b.canCreate=false;for(const key of Object.keys(b.abilities))b.abilities[key]=false;}await mount(limited);await click('[data-service-open]');expect(!$('[data-service-accept]')&&!$(action('assign'))&&!$(action('confirm')),'Read-only or other requester received actions');
        const provider=props();provider.workspace.userId=2;for(const b of provider.workspace.buildings)for(const key of Object.keys(b.abilities))b.abilities[key]=false;row.wallet_payment={status:'locked',payer_source:'user_wallet',amount:1000,provider_amount:900,commission_amount:100};await mount(provider);await click('[data-service-open]');expect($(action('start'))&&!$(action('confirm')),'Assigned provider permissions not respected');
        row.status='open';row.assigned_to=null;row.wallet_payment=null;row.quotes=[];await mount();await click('[data-service-create]');await input('building_id','1');await submit();await input('title','اطلاعات حفظ شود');await input('type','نظافت');await submit();failSave=true;await submit();await until(()=>$('[data-service-error]'));expect($('[name="title"]').value==='اطلاعات حفظ شود','422 discarded data');failSave=false;
        await submit();loseSave=true;await submit();await until(()=>$('[data-service-save]').disabled);expect($('#app').textContent.includes('نتیجه ثبت مشخص نیست'),'Unknown outcome permits repeated write');loseSave=false;const recover=$$('button').find(el=>el.textContent==='بررسی وضعیت درخواست');recover.click();await until(()=>$('[data-service-open]'));await click('[data-service-create]');expect($('#service-request-form'),'Recovery left form permanently blocked');await input('building_id','1');await click('.finance-editor .ownership-card__heading button');expect($('.finance-editor [role="alert"]'),'Dirty form discarded silently');$('.finance-editor [role="alert"] button:last-child').click();await tick();
        expect(document.documentElement.scrollWidth<=innerWidth,'Layout overflows');
        const capture=${JSON.stringify(process.env.SERVICE_CAPTURE||'list')};await mount();if(capture==='detail'){row.assigned_to={id:2,first_name:'ارائه‌دهنده',last_name:'نمونه'};row.status='assigned';row.quotes=[{...quote}];await click('[data-service-open]');}else if(capture==='editor'){await click('[data-service-create]');await input('building_id','1');await submit();await input('title','تعمیر روشنایی راه‌پله');await input('type','برق');}
        window.scrollTo(0,0);expect(document.documentElement.scrollWidth<=innerWidth,'Capture layout overflows');$('#result').dataset.ok='true';$('#result').textContent='Passed';
    })().catch(error=>$('#result').textContent=error.stack);
    </script></html>`;
    const server=createServer((req,res)=>{const path=new URL(req.url,'http://localhost').pathname;if(path==='/test.js'){res.setHeader('Content-Type','application/javascript');res.end(bundle.outputFiles[0].text);return;}if(path.startsWith('/public/')||path.startsWith('/resources/css/')||path.startsWith('/node_modules/')||path.startsWith('/fonts/')){try{res.setHeader('Content-Type',path.endsWith('.css')?'text/css':'font/woff2');res.end(readFileSync(join(project,path.startsWith('/fonts/')?'public'+path:path.slice(1))));}catch{res.writeHead(404).end();}return;}res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);});
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{for(const width of [1280,500]){const capture=process.env.SERVICE_CAPTURE?['--screenshot='+join(tmpdir(),'buildino-services-'+process.env.SERVICE_CAPTURE+'-'+width+'.png')]:[];const {stdout}=await promisify(execFile)(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+width+',1000','--virtual-time-budget=15000','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+'/management/operations/service-requests'],{encoding:'utf8',timeout:30000,maxBuffer:3*1024*1024,windowsHide:true});const result=stdout.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0]||stdout.slice(-1000);assert.match(result,/data-ok="true"/,width+': '+result);}}finally{await new Promise(resolve=>server.close(resolve));}
});
